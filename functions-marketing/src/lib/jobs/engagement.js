const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingLines, settingNumber } = require('../mkt/settings');
const { runPrompt, extractJson } = require('../mkt/ai');
const { oneLine } = require('../mkt/taxonomy');
const { redact } = require('../mkt/redact');

const TRIAGE_PROMPT_KEY = 'engagement.response_triage';
const DIGEST_PROMPT_KEY = 'engagement.weekly_digest';
const LABELS = new Set(['question', 'praise', 'complaint', 'claims_risk', 'adverse_event', 'spam', 'other']);
const ESCALATE = new Set(['claims_risk', 'adverse_event']);
const SENTIMENTS = new Set(['positive', 'neutral', 'negative']);
const TASK_ROLES = new Set(['coordinator', 'writer', 'editorial', 'compliance']);
const REC_TYPES = new Set([
  'double_down', 'follow_up_series', 'new_campaign', 'retire_interest', 'add_source', 'fix_page', 'map_keyword', 'reply_backlog', 'other',
]);
const REF_TYPES = ['asset', 'campaign', 'topic', 'interest', 'content', 'page', 'query'];
const TRIAGE_BATCH = 20;
const MAX_TRIAGE_ATTEMPTS = 3;
const LOCAL_TZ = 'America/Chicago';

// metric key on a score row → points key in engagement.weights
const POINT_METRICS = [
  ['sessions', 'session'], ['engaged', 'engaged_session'], ['keyEvents', 'key_event'], ['transactions', 'transaction'],
  ['leads', 'lead'], ['likes', 'like'], ['comments', 'comment'], ['shares', 'share'], ['saves', 'save'],
  ['linkClicks', 'link_click'], ['emailOpens', 'email_open'], ['emailClicks', 'email_click'], ['emailReplies', 'email_reply'],
  ['searchClicks', 'search_click'], ['positive', 'positive_response'],
];
const SUM_FIELDS = [
  'sessions', 'engaged', 'keyEvents', 'transactions', 'revenue', 'impressions', 'interactions', 'emailOpens', 'emailClicks',
  'leads', 'searchClicks', 'responses', 'positive', 'risk',
];

const n = (value) => Number(value) || 0;
const round1 = (value) => Math.round(value * 10) / 10;

function userIdFrom(params) {
  return Number(params.triggered_by_user_id || params.user_id || 0) || null;
}

function termHits(settings, key, text) {
  const haystack = String(text || '').toLowerCase();
  return settingLines(settings, key).filter((term) => {
    const escaped = term.toLowerCase().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return new RegExp(`(^|[^a-z0-9])${escaped}(?=[^a-z0-9]|$)`).test(haystack);
  });
}

function localDate(date = new Date()) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: LOCAL_TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
}

function addDays(ymd, days) {
  const date = new Date(`${ymd}T00:00:00Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

/* ---------- Scoring ---------- */

function loadWeights(settings) {
  const weights = {};
  for (const line of settingLines(settings, 'engagement.weights')) {
    const [key, value] = line.split('|').map((part) => (part || '').trim());
    if (key && Number.isFinite(Number(value))) weights[key] = Number(value);
  }
  return weights;
}

function pointsFor(metrics, weights) {
  return POINT_METRICS.reduce((sum, [metric, key]) => sum + n(metrics[metric]) * n(weights[key]), 0);
}

function curve(points, half) {
  return points > 0 ? round1((100 * points) / (points + half)) : 0;
}

function leafConfidence(row, mature) {
  const hasData = row.hasMetrics || row.sessions > 0 || row.searchClicks > 0;
  if (row.ageDays < mature || !hasData) return 'low';
  return row.sessions + row.interactions + row.emailOpens + row.searchClicks >= 20 ? 'high' : 'medium';
}

/** Parent score = mean of child scores; metrics are summed. */
function rollup(children) {
  const out = { score: 0, points: 0, childCount: children.length, confidence: 'low' };
  for (const field of SUM_FIELDS) out[field] = 0;
  if (children.length === 0) return out;
  let scoreSum = 0;
  let solid = 0;
  for (const child of children) {
    scoreSum += child.score;
    out.points += child.points;
    for (const field of SUM_FIELDS) out[field] += n(child[field]);
    if (child.confidence !== 'low') solid += 1;
  }
  out.score = round1(scoreSum / children.length);
  out.confidence = solid >= 3 ? 'high' : solid >= 1 ? 'medium' : 'low';
  return out;
}

async function loadAssetRows(pool) {
  const result = await pool.request().query(`
    WITH ga AS (
      SELECT AssetID, SUM(Sessions) AS Sessions, SUM(EngagedSessions) AS Engaged, SUM(KeyEvents) AS KeyEvents,
             SUM(Transactions) AS Transactions, SUM(Revenue) AS Revenue
      FROM dbo.MktGa4Daily WHERE Grain = N'utm' AND AssetID IS NOT NULL GROUP BY AssetID
    ), m AS (
      SELECT *, ROW_NUMBER() OVER (PARTITION BY AssetID ORDER BY AsOfDate DESC,
               CASE Source WHEN N'ghl' THEN 0 WHEN N'platform' THEN 1 WHEN N'import' THEN 2 ELSE 3 END) AS rn
      FROM dbo.MktAssetMetric
    ), r AS (
      SELECT AssetID, COUNT(*) AS Responses,
             SUM(CASE WHEN Triage IN (N'praise', N'question') THEN 1 ELSE 0 END) AS Positive,
             SUM(CASE WHEN Triage IN (N'claims_risk', N'adverse_event', N'complaint') THEN 1 ELSE 0 END) AS Risk
      FROM dbo.MktResponse WHERE AssetID IS NOT NULL AND (Triage IS NULL OR Triage <> N'spam') GROUP BY AssetID
    )
    SELECT a.AssetID, a.CampaignID, c.TopicID, t.InterestID,
           DATEDIFF(day, COALESCE(a.PostedAt, a.ScheduledAt), SYSUTCDATETIME()) AS AgeDays,
           COALESCE(ga.Sessions, 0) AS Sessions, COALESCE(ga.Engaged, 0) AS Engaged, COALESCE(ga.KeyEvents, 0) AS KeyEvents,
           COALESCE(ga.Transactions, 0) AS Transactions, COALESCE(ga.Revenue, 0) AS Revenue,
           CASE WHEN m.MetricID IS NULL THEN 0 ELSE 1 END AS HasMetrics,
           COALESCE(m.Impressions, m.Reach, 0) AS Impressions, COALESCE(m.Likes, 0) AS Likes, COALESCE(m.Comments, 0) AS Comments,
           COALESCE(m.Shares, 0) AS Shares, COALESCE(m.Saves, 0) AS Saves, COALESCE(m.LinkClicks, 0) AS LinkClicks,
           COALESCE(m.EmailOpens, 0) AS EmailOpens, COALESCE(m.EmailClicks, 0) AS EmailClicks,
           COALESCE(m.EmailReplies, 0) AS EmailReplies, COALESCE(m.Leads, 0) AS Leads,
           COALESCE(r.Responses, 0) AS Responses, COALESCE(r.Positive, 0) AS Positive, COALESCE(r.Risk, 0) AS Risk
    FROM dbo.MktAsset a
    INNER JOIN dbo.MktCampaign c ON c.CampaignID = a.CampaignID
    LEFT JOIN dbo.MktTopic t ON t.TopicID = c.TopicID
    LEFT JOIN ga ON ga.AssetID = a.AssetID
    LEFT JOIN m ON m.AssetID = a.AssetID AND m.rn = 1
    LEFT JOIN r ON r.AssetID = a.AssetID
    WHERE a.Status = N'posted' OR ga.AssetID IS NOT NULL
  `);
  return result.recordset.map((row) => ({
    id: row.AssetID,
    campaignId: row.CampaignID,
    topicId: row.TopicID,
    interestId: row.InterestID,
    ageDays: n(row.AgeDays),
    hasMetrics: Boolean(row.HasMetrics),
    sessions: n(row.Sessions),
    engaged: n(row.Engaged),
    keyEvents: n(row.KeyEvents),
    transactions: n(row.Transactions),
    revenue: n(row.Revenue),
    impressions: n(row.Impressions),
    likes: n(row.Likes),
    comments: n(row.Comments),
    shares: n(row.Shares),
    saves: n(row.Saves),
    linkClicks: n(row.LinkClicks),
    interactions: n(row.Likes) + n(row.Comments) + n(row.Shares) + n(row.Saves),
    emailOpens: n(row.EmailOpens),
    emailClicks: n(row.EmailClicks),
    emailReplies: n(row.EmailReplies),
    leads: n(row.Leads),
    searchClicks: 0,
    responses: n(row.Responses),
    positive: n(row.Positive),
    risk: n(row.Risk),
  }));
}

async function loadContentRows(pool) {
  const result = await pool.request().query(`
    SELECT c.ContentID, c.TopicID, t.InterestID, DATEDIFF(day, c.PublishedAt, SYSUTCDATETIME()) AS AgeDays,
           COALESCE(g.Sessions, 0) AS Sessions, COALESCE(g.Engaged, 0) AS Engaged, COALESCE(g.KeyEvents, 0) AS KeyEvents,
           COALESCE(g.Transactions, 0) AS Transactions, COALESCE(g.Revenue, 0) AS Revenue,
           COALESCE(s.Clicks, 0) AS SearchClicks, COALESCE(s.Impressions, 0) AS Impressions
    FROM dbo.MktContent c
    LEFT JOIN dbo.MktTopic t ON t.TopicID = c.TopicID
    OUTER APPLY (
      SELECT SUM(x.Sessions) AS Sessions, SUM(x.EngagedSessions) AS Engaged, SUM(x.KeyEvents) AS KeyEvents,
             SUM(x.Transactions) AS Transactions, SUM(x.Revenue) AS Revenue
      FROM dbo.MktGa4Daily x INNER JOIN dbo.MktPage p ON p.PageID = x.PageID
      WHERE p.ContentID = c.ContentID AND x.Grain = N'landing' AND x.MetricDate >= CAST(c.PublishedAt AS date)
    ) g
    OUTER APPLY (
      SELECT SUM(q.Clicks) AS Clicks, SUM(q.Impressions) AS Impressions
      FROM dbo.MktGscDaily q INNER JOIN dbo.MktPage p ON p.PageID = q.PageID
      WHERE p.ContentID = c.ContentID AND q.Grain = N'page' AND q.MetricDate >= CAST(c.PublishedAt AS date)
    ) s
    WHERE c.Stage IN (N'published', N'monitoring') AND c.PublishedAt IS NOT NULL
  `);
  return result.recordset.map((row) => ({
    id: row.ContentID,
    topicId: row.TopicID,
    interestId: row.InterestID,
    ageDays: n(row.AgeDays),
    hasMetrics: false,
    sessions: n(row.Sessions),
    engaged: n(row.Engaged),
    keyEvents: n(row.KeyEvents),
    transactions: n(row.Transactions),
    revenue: n(row.Revenue),
    impressions: n(row.Impressions),
    interactions: 0,
    emailOpens: 0,
    emailClicks: 0,
    leads: 0,
    searchClicks: n(row.SearchClicks),
    responses: 0,
    positive: 0,
    risk: 0,
  }));
}

const SCORE_COLUMNS = [
  ['Level', 'nvarchar(10)'], ['RefID', 'int'], ['Score', 'decimal(5, 1)'], ['Points', 'decimal(12, 2)'],
  ['Confidence', 'nvarchar(10)'], ['ChildCount', 'int'], ['Sessions', 'int'], ['EngagedSessions', 'int'], ['KeyEvents', 'int'],
  ['Transactions', 'int'], ['Revenue', 'decimal(14, 2)'], ['Impressions', 'int'], ['Interactions', 'int'], ['EmailOpens', 'int'],
  ['EmailClicks', 'int'], ['Leads', 'int'], ['SearchClicks', 'int'], ['Responses', 'int'], ['PositiveResponses', 'int'],
  ['RiskResponses', 'int'],
];

function scoreRecord(level, id, row) {
  return {
    Level: level,
    RefID: id,
    Score: row.score,
    Points: Math.round(row.points * 100) / 100,
    Confidence: row.confidence,
    ChildCount: row.childCount || 0,
    Sessions: row.sessions,
    EngagedSessions: row.engaged,
    KeyEvents: row.keyEvents,
    Transactions: row.transactions,
    Revenue: Math.round(n(row.revenue) * 100) / 100,
    Impressions: row.impressions,
    Interactions: row.interactions,
    EmailOpens: row.emailOpens,
    EmailClicks: row.emailClicks,
    Leads: row.leads,
    SearchClicks: row.searchClicks,
    Responses: row.responses,
    PositiveResponses: row.positive,
    RiskResponses: row.risk,
  };
}

function groupBy(rows, key) {
  const map = new Map();
  for (const row of rows) {
    const value = row[key];
    if (value === null || value === undefined) continue;
    if (!map.has(value)) map.set(value, []);
    map.get(value).push(row);
  }
  return map;
}

/**
 * Nightly: score posted assets and published content, roll up to campaign → topic → interest, then move each
 * interest's relevance weight (bounded) and suggested priority from how it compares with the others.
 */
async function score(params = {}) {
  const processLogId = Number(params.log_id || 0) || null;
  const pool = await connectPool(getProductionDatabase());
  try {
    const settings = await loadSettings(pool);
    const weights = loadWeights(settings);
    const half = Math.max(1, settingNumber(settings, 'engagement.half_points', 40));
    const mature = settingNumber(settings, 'engagement.mature_days', 7);
    const minAssets = settingNumber(settings, 'engagement.min_assets_for_weight', 3);
    const range = Math.min(0.5, Math.max(0, settingNumber(settings, 'engagement.weight_range', 0.2)));

    const leaf = (row) => {
      row.points = pointsFor(row, weights);
      row.score = curve(row.points, half);
      row.confidence = leafConfidence(row, mature);
      return row;
    };
    const assets = (await loadAssetRows(pool)).map(leaf);
    const content = (await loadContentRows(pool)).map(leaf);

    const campaigns = [...groupBy(assets, 'campaignId')].map(([id, children]) => ({
      id, topicId: children[0].topicId, interestId: children[0].interestId, ...rollup(children),
    }));
    const topicChildren = groupBy([...campaigns, ...content], 'topicId');
    const topicInterest = new Map([...campaigns, ...content].filter((row) => row.topicId).map((row) => [row.topicId, row.interestId]));
    const topics = [...topicChildren].map(([id, children]) => ({ id, interestId: topicInterest.get(id), ...rollup(children) }));
    const interests = [...groupBy(topics, 'interestId')].map(([id, children]) => ({ id, ...rollup(children) }));

    const records = [
      ...assets.map((row) => scoreRecord('asset', row.id, row)),
      ...content.map((row) => scoreRecord('content', row.id, row)),
      ...campaigns.map((row) => scoreRecord('campaign', row.id, row)),
      ...topics.map((row) => scoreRecord('topic', row.id, row)),
      ...interests.map((row) => scoreRecord('interest', row.id, row)),
    ];

    const solidByInterest = new Map();
    for (const row of assets) {
      if (row.interestId && row.confidence !== 'low') solidByInterest.set(row.interestId, (solidByInterest.get(row.interestId) || 0) + 1);
    }
    const eligible = interests.filter((row) => (solidByInterest.get(row.id) || 0) >= minAssets);
    const baseline = eligible.length >= 2 ? eligible.reduce((sum, row) => sum + row.score, 0) / eligible.length : null;
    const plan = new Map();
    for (const row of interests) {
      const isEligible = baseline !== null && eligible.includes(row);
      const weight = isEligible ? Math.min(1 + range, Math.max(1 - range, 1 + ((row.score - baseline) / 50) * range)) : 1;
      plan.set(row.id, { score: row.score, weight: Math.round(weight * 100) / 100, diff: isEligible ? row.score - baseline : null });
    }

    const payload = JSON.stringify(records);
    const withClause = SCORE_COLUMNS.map(([name, type]) => `${name} ${type} '$.${name}'`).join(', ');
    const columns = SCORE_COLUMNS.map(([name]) => name).join(', ');
    const interestPlan = JSON.stringify([...plan].map(([id, row]) => ({ id, score: row.score, weight: row.weight, diff: row.diff })));
    const result = await pool.request()
      .input('rows', sql.NVarChar(sql.MAX), payload)
      .input('plan', sql.NVarChar(sql.MAX), interestPlan)
      .input('log', sql.Int, processLogId)
      .query(`
        SET XACT_ABORT ON;
        BEGIN TRANSACTION;
        DECLARE @today DATE = CAST(SYSUTCDATETIME() AS date);
        DELETE FROM dbo.MktEngagementScore WHERE ScoreDate = @today;
        INSERT INTO dbo.MktEngagementScore (ScoreDate, ProcessLogID, ${columns})
        SELECT @today, @log, ${columns} FROM OPENJSON(@rows) WITH (${withClause});

        DECLARE @before TABLE (InterestID INT, RelevanceWeight DECIMAL(4, 2));
        INSERT INTO @before SELECT InterestID, RelevanceWeight FROM dbo.MktInterest;
        UPDATE i SET
          RelevanceWeight = COALESCE(p.weight, 1.00),
          PerformanceScore = p.score,
          SuggestedPriority = CASE
            WHEN p.diff IS NULL THEN NULL
            WHEN p.diff >= 15 AND i.Priority < 5 THEN i.Priority + 1
            WHEN p.diff <= -15 AND i.Priority > 1 THEN i.Priority - 1
            ELSE i.Priority END,
          PerformanceAt = SYSUTCDATETIME()
        FROM dbo.MktInterest i
        LEFT JOIN OPENJSON(@plan) WITH (id INT '$.id', score DECIMAL(5, 1) '$.score', weight DECIMAL(4, 2) '$.weight', diff DECIMAL(6, 1) '$.diff') p
          ON p.id = i.InterestID;
        SELECT COUNT(*) AS Changed FROM dbo.MktInterest i INNER JOIN @before b ON b.InterestID = i.InterestID
        WHERE b.RelevanceWeight <> i.RelevanceWeight;
        COMMIT TRANSACTION;
      `);

    return {
      ok: true,
      assets: assets.length,
      content: content.length,
      campaigns: campaigns.length,
      topics: topics.length,
      interests: interests.length,
      weighted_interests: eligible.length >= 2 ? eligible.length : 0,
      weights_changed: n(result.recordset[0]?.Changed),
    };
  } finally {
    await pool.close();
  }
}

/* ---------- Response triage ---------- */

async function complianceUserId(pool) {
  const rows = (await pool.request().query(`
    SELECT u.UserID FROM dbo.[User] u INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
    WHERE r.MarketingCompliance LIKE N'%U%'
  `)).recordset;
  return rows.length === 1 ? rows[0].UserID : null;
}

/** Escalation task text — must match mkt_response_escalation_task() in includes/marketing-engagement.php. */
function escalationTask(responseId, label, hours) {
  const what = label === 'adverse_event' ? 'possible adverse event' : 'claims-risk response';
  return {
    title: `Compliance review (${hours}-hour): ${what} — response #${responseId}`,
    detail: label === 'adverse_event'
      ? 'Someone may be describing a side effect or reaction. Decide whether it must be reported, record the decision, and clear it before anyone replies.'
      : 'A reply could make a disease or unapproved claim. Record what may (and may not) be said, then clear it so the coordinator can reply.',
  };
}

async function openEscalationTask(pool, responseId, label, dueAt, assignee, hours) {
  const task = escalationTask(responseId, label, hours);
  await pool.request()
    .input('key', sql.NVarChar(240), `response:${responseId}:compliance`)
    .input('title', sql.NVarChar(600), task.title)
    .input('detail', sql.NVarChar(4000), task.detail)
    .input('id', sql.Int, responseId)
    .input('href', sql.NVarChar(1000), `/marketing/performance/response.php?id=${responseId}`)
    .input('due', sql.DateTime2, dueAt)
    .input('user', sql.Int, assignee)
    .query(`
      IF NOT EXISTS (SELECT 1 FROM dbo.MktTask WHERE AutoKey = @key AND Status = N'open')
        INSERT INTO dbo.MktTask (Title, Detail, TaskType, AssigneeRole, AssigneeUserID, RefType, RefID, Href, DueDate, Priority, AutoKey)
        VALUES (@title, @detail, N'response_compliance', N'compliance', @user, N'response', @id, @href,
                CAST(CAST(@due AS DATETIMEOFFSET) AT TIME ZONE 'Central Standard Time' AS date), N'high', @key);
    `);
}

function triageContext(row) {
  if (!row.AssetID) return row.CampaignName ? `campaign "${oneLine(row.CampaignName, 120)}"` : '(not linked to a post)';
  const body = oneLine(String(row.AssetBody || '').replace(/\[LINK\]/g, ''), 300);
  return `${row.CampaignName ? `campaign "${oneLine(row.CampaignName, 120)}", ` : ''}post: ${oneLine(row.AssetTitle || row.AssetSubject || '', 150)} — ${body}`;
}

function parseTriage(text) {
  const parsed = extractJson(text);
  if (!parsed || typeof parsed !== 'object' || !LABELS.has(parsed.label)) return null;
  const confidence = Number(parsed.confidence);
  return {
    label: parsed.label,
    confidence: Number.isFinite(confidence) ? Math.min(1, Math.max(0, confidence)) : null,
    sentiment: SENTIMENTS.has(parsed.sentiment) ? parsed.sentiment : null,
    reason: oneLine(parsed.reason || '', 1000),
    action: oneLine(parsed.suggested_action || '', 1000),
  };
}

/**
 * AI-label responses (one given, or the untriaged queue). Rules then override: adverse-event words force
 * adverse_event; claims flag terms force at least claims_risk. Both escalate to compliance with a due time.
 */
async function triage(params = {}) {
  const responseId = Number(params.response_id || 0) || null;
  const processLogId = Number(params.log_id || 0) || null;
  const pool = await connectPool(getProductionDatabase());
  try {
    const settings = await loadSettings(pool);
    const hours = Math.max(1, settingNumber(settings, 'engagement.escalation_hours', 24));
    const request = pool.request().input('max', sql.Int, TRIAGE_BATCH).input('attempts', sql.Int, MAX_TRIAGE_ATTEMPTS);
    let where = `r.Status = N'new' AND r.TriageAttempts < @attempts`;
    if (responseId) {
      request.input('id', sql.Int, responseId);
      where = `r.ResponseID = @id AND r.Status IN (N'new', N'open')`;
    }
    const rows = (await request.query(`
      SELECT TOP (@max) r.ResponseID, r.AssetID, r.CampaignID, r.Channel, r.Kind, r.BodyText, r.Status,
             a.Title AS AssetTitle, a.Subject AS AssetSubject, a.Body AS AssetBody, c.Name AS CampaignName
      FROM dbo.MktResponse r
      LEFT JOIN dbo.MktAsset a ON a.AssetID = r.AssetID
      LEFT JOIN dbo.MktCampaign c ON c.CampaignID = COALESCE(r.CampaignID, a.CampaignID)
      WHERE ${where}
      ORDER BY r.ReceivedAt
    `)).recordset;
    if (rows.length === 0) {
      return { ok: true, skipped: true, message: responseId ? 'That response is already escalated or closed.' : 'No responses waiting for triage.' };
    }

    const assignee = await complianceUserId(pool);
    const totals = { triaged: 0, escalated: 0, failed: 0, cost_usd: 0 };
    const labels = {};
    let budgetError = null;
    for (const row of rows) {
      const text = redact(row.BodyText).text;
      let outcome = null;
      let error = budgetError;
      if (!budgetError) {
        try {
          const result = await runPrompt(pool, settings, {
            promptKey: TRIAGE_PROMPT_KEY,
            vars: { brand_name: settings['brand.name'] || 'NutraAxis', channel: row.Channel, kind: row.Kind, context: triageContext(row), text },
            operation: TRIAGE_PROMPT_KEY,
            processLogId,
            refType: 'response',
            refId: row.ResponseID,
          });
          totals.cost_usd += result.costUsd;
          outcome = parseTriage(result.text);
          if (!outcome) error = 'The AI reply could not be read.';
          else outcome.source = 'ai';
        } catch (err) {
          error = err.message;
          if (err.name === 'BudgetExceededError') budgetError = err.message;
        }
      }

      const adverse = termHits(settings, 'engagement.adverse_terms', text);
      const flags = termHits(settings, 'claims.flag_terms', text);
      if (adverse.length && outcome?.label !== 'adverse_event') {
        outcome = {
          ...(outcome || { confidence: null, sentiment: null, action: '' }),
          label: 'adverse_event',
          source: 'rule',
          reason: `Mentions "${adverse[0]}" — treated as a possible adverse event.${outcome?.reason ? ` AI: ${outcome.reason}` : ''}`,
        };
      } else if (flags.length && (!outcome || !ESCALATE.has(outcome.label))) {
        outcome = {
          ...(outcome || { confidence: null, sentiment: null, action: '' }),
          label: 'claims_risk',
          source: 'rule',
          reason: `Mentions "${flags[0]}" (a claims flag term).${outcome?.reason ? ` AI: ${outcome.reason}` : ''}`,
        };
      }

      if (!outcome) {
        totals.failed += 1;
        await pool.request().input('id', sql.Int, row.ResponseID).input('error', sql.NVarChar(500), String(error || 'failed').slice(0, 500))
          .query('UPDATE dbo.MktResponse SET TriageAttempts = TriageAttempts + 1, TriageError = @error, UpdatedAt = SYSUTCDATETIME() WHERE ResponseID = @id');
        continue;
      }

      const escalate = ESCALATE.has(outcome.label);
      const dueAt = escalate ? new Date(Date.now() + hours * 3600000) : null;
      const saved = await pool.request()
        .input('id', sql.Int, row.ResponseID)
        .input('label', sql.NVarChar(20), outcome.label)
        .input('source', sql.NVarChar(10), outcome.source)
        .input('conf', sql.Decimal(4, 3), outcome.confidence)
        .input('sentiment', sql.NVarChar(10), outcome.sentiment)
        .input('reason', sql.NVarChar(1000), String(outcome.reason || '').slice(0, 1000))
        .input('action', sql.NVarChar(1000), String(outcome.action || '').slice(0, 1000) || null)
        .input('status', sql.NVarChar(20), escalate ? 'escalated' : 'open')
        .input('due', sql.DateTime2, dueAt)
        .query(`
          UPDATE dbo.MktResponse
          SET Triage = @label, TriageSource = @source, TriageConfidence = @conf, Sentiment = @sentiment, TriageReason = @reason,
              SuggestedAction = @action, TriagedAt = SYSUTCDATETIME(), TriagedBy = NULL, TriageError = NULL,
              TriageAttempts = TriageAttempts + 1, Status = @status,
              EscalatedAt = CASE WHEN @due IS NULL THEN NULL ELSE SYSUTCDATETIME() END, EscalationDueAt = @due,
              UpdatedAt = SYSUTCDATETIME()
          WHERE ResponseID = @id AND Status IN (N'new', N'open');
          SELECT @@ROWCOUNT AS Updated;
        `);
      if (!n(saved.recordset[0]?.Updated)) continue;
      totals.triaged += 1;
      labels[outcome.label] = (labels[outcome.label] || 0) + 1;
      if (escalate) {
        totals.escalated += 1;
        await openEscalationTask(pool, row.ResponseID, outcome.label, dueAt, assignee, hours);
      }
    }
    return { ok: true, ...totals, labels, cost_usd: Math.round(totals.cost_usd * 1e6) / 1e6 };
  } finally {
    await pool.close();
  }
}

/* ---------- Monday digest ---------- */

function lastWeek(params) {
  if (params.period_end && /^\d{4}-\d{2}-\d{2}$/.test(params.period_end)) {
    return { start: addDays(params.period_end, -6), end: params.period_end };
  }
  const today = localDate();
  const dow = new Date(`${today}T00:00:00Z`).getUTCDay();
  const end = addDays(today, -(dow === 0 ? 7 : dow));
  return { start: addDays(end, -6), end };
}

async function query(pool, text, inputs = {}) {
  const request = pool.request();
  for (const [name, value] of Object.entries(inputs)) request.input(name, value);
  return (await request.query(text)).recordset;
}

async function gatherFacts(pool, start, end) {
  const priorStart = addDays(start, -7);
  const priorEnd = addDays(end, -7);
  const range = { s: start, e: end, ps: priorStart, pe: priorEnd };

  const [search] = await query(pool, `
    SELECT SUM(CASE WHEN MetricDate BETWEEN @s AND @e THEN Clicks ELSE 0 END) AS clicks,
           SUM(CASE WHEN MetricDate BETWEEN @s AND @e THEN Impressions ELSE 0 END) AS impressions,
           SUM(CASE WHEN MetricDate BETWEEN @ps AND @pe THEN Clicks ELSE 0 END) AS prior_clicks,
           SUM(CASE WHEN MetricDate BETWEEN @ps AND @pe THEN Impressions ELSE 0 END) AS prior_impressions,
           CONVERT(varchar(10), MAX(MetricDate), 23) AS latest_day
    FROM dbo.MktGscDaily WHERE Grain = N'site'`, range);
  const channels = await query(pool, `
    SELECT Dim1 AS channel,
           SUM(CASE WHEN MetricDate BETWEEN @s AND @e THEN Sessions ELSE 0 END) AS sessions,
           SUM(CASE WHEN MetricDate BETWEEN @ps AND @pe THEN Sessions ELSE 0 END) AS prior_sessions,
           SUM(CASE WHEN MetricDate BETWEEN @s AND @e THEN EngagedSessions ELSE 0 END) AS engaged,
           SUM(CASE WHEN MetricDate BETWEEN @s AND @e THEN KeyEvents ELSE 0 END) AS key_events,
           SUM(CASE WHEN MetricDate BETWEEN @s AND @e THEN Transactions ELSE 0 END) AS purchases
    FROM dbo.MktGa4Daily WHERE Grain = N'channel' AND MetricDate BETWEEN @ps AND @e
    GROUP BY Dim1 ORDER BY sessions DESC`, range);
  const [ga4Latest] = await query(pool, `SELECT CONVERT(varchar(10), MAX(MetricDate), 23) AS latest_day FROM dbo.MktGa4Daily`);
  const ga4 = channels.reduce((acc, row) => {
    acc.sessions += n(row.sessions);
    acc.prior_sessions += n(row.prior_sessions);
    acc.engaged += n(row.engaged);
    acc.key_events += n(row.key_events);
    acc.purchases += n(row.purchases);
    return acc;
  }, { sessions: 0, prior_sessions: 0, engaged: 0, key_events: 0, purchases: 0 });
  ga4.latest_day = ga4Latest?.latest_day || null;
  ga4.by_channel = channels.filter((row) => n(row.sessions) || n(row.prior_sessions))
    .map((row) => ({ channel: row.channel, sessions: n(row.sessions), prior_sessions: n(row.prior_sessions) }));

  const latestScore = `(SELECT MAX(ScoreDate) FROM dbo.MktEngagementScore)`;
  const assets = await query(pool, `
    SELECT TOP 15 s.RefID AS id, COALESCE(NULLIF(a.Title, N''), a.Subject) AS title, a.Channel AS channel, a.CampaignID AS campaign_id,
           s.Score AS score, s.Confidence AS confidence, s.Sessions AS sessions, s.Interactions AS interactions,
           s.EmailClicks AS email_clicks, s.KeyEvents AS key_events, s.Responses AS responses,
           CONVERT(varchar(10), a.PostedAt, 23) AS posted
    FROM dbo.MktEngagementScore s INNER JOIN dbo.MktAsset a ON a.AssetID = s.RefID
    WHERE s.Level = N'asset' AND s.ScoreDate = ${latestScore}
    ORDER BY s.Score DESC`);
  const campaigns = await query(pool, `
    SELECT s.RefID AS id, c.Name AS name, c.TopicID AS topic_id, s.Score AS score, s.Confidence AS confidence,
           s.ChildCount AS assets, s.Sessions AS sessions
    FROM dbo.MktEngagementScore s INNER JOIN dbo.MktCampaign c ON c.CampaignID = s.RefID
    WHERE s.Level = N'campaign' AND s.ScoreDate = ${latestScore}
    ORDER BY s.Score DESC`);
  const content = await query(pool, `
    SELECT s.RefID AS id, c.Title AS title, s.Score AS score, s.Confidence AS confidence, s.Sessions AS sessions,
           s.SearchClicks AS search_clicks
    FROM dbo.MktEngagementScore s INNER JOIN dbo.MktContent c ON c.ContentID = s.RefID
    WHERE s.Level = N'content' AND s.ScoreDate = ${latestScore}`);
  const topics = await query(pool, `
    SELECT t.TopicID AS id, t.Title AS title, t.Status AS status, t.InterestID AS interest_id, s.Score AS score, s.Confidence AS confidence,
           (SELECT COUNT(*) FROM dbo.MktCampaign c WHERE c.TopicID = t.TopicID) AS campaigns,
           (SELECT COUNT(*) FROM dbo.MktContent c WHERE c.TopicID = t.TopicID) AS content_pieces,
           t.IsEmerging AS emerging, t.SuggestedInterestName AS suggested_interest
    FROM dbo.MktTopic t
    LEFT JOIN dbo.MktEngagementScore s ON s.Level = N'topic' AND s.RefID = t.TopicID AND s.ScoreDate = ${latestScore}
    WHERE s.ScoreID IS NOT NULL OR t.Status = N'accepted' OR (t.IsEmerging = 1 AND t.Status = N'proposed')
    ORDER BY s.Score DESC, t.UpdatedAt DESC`);
  const interests = await query(pool, `
    SELECT i.InterestID AS id, i.Name AS name, i.Priority AS priority, i.PerformanceScore AS score, i.RelevanceWeight AS weight,
           i.SuggestedPriority AS suggested_priority,
           (SELECT COUNT(*) FROM dbo.MktHarvestedItem h WHERE h.PrimaryInterestID = i.InterestID AND h.Status <> N'discarded'
              AND h.ScoredAt >= DATEADD(day, -30, SYSUTCDATETIME())) AS relevant_items_30d,
           (SELECT COUNT(*) FROM dbo.MktTopic t WHERE t.InterestID = i.InterestID AND t.Status = N'accepted') AS accepted_topics
    FROM dbo.MktInterest i WHERE i.Status = N'active' ORDER BY i.Priority DESC, i.Name`);
  const [responses] = await query(pool, `
    SELECT SUM(CASE WHEN ReceivedAt >= @s AND ReceivedAt < DATEADD(day, 1, CAST(@e AS date)) THEN 1 ELSE 0 END) AS received,
           SUM(CASE WHEN Status = N'open' AND Triage IN (N'question', N'complaint') THEN 1 ELSE 0 END) AS awaiting_reply,
           SUM(CASE WHEN Status = N'escalated' THEN 1 ELSE 0 END) AS escalated_open,
           SUM(CASE WHEN Status = N'escalated' AND EscalationDueAt < SYSUTCDATETIME() THEN 1 ELSE 0 END) AS escalated_overdue,
           SUM(CASE WHEN Triage IN (N'claims_risk', N'adverse_event') AND ReceivedAt >= @s THEN 1 ELSE 0 END) AS escalations_this_week
    FROM dbo.MktResponse`, range);
  const pages = await query(pool, `
    SELECT TOP 6 p.PageID AS id, p.Path AS path, p.Issues AS issues,
           (SELECT COALESCE(SUM(g.Sessions), 0) FROM dbo.MktGa4Daily g WHERE g.PageID = p.PageID AND g.Grain = N'landing'
              AND g.MetricDate > DATEADD(day, -28, CAST(@e AS date))) AS sessions_28d,
           (SELECT COALESCE(SUM(q.Impressions), 0) FROM dbo.MktGscDaily q WHERE q.PageID = p.PageID AND q.Grain = N'page'
              AND q.MetricDate > DATEADD(day, -28, CAST(@e AS date))) AS impressions_28d
    FROM dbo.MktPage p
    WHERE p.Status = N'active' AND p.Issues IS NOT NULL AND p.Issues <> N''
    ORDER BY sessions_28d DESC, impressions_28d DESC`, range);
  const queries = await query(pool, `
    SELECT TOP 6 q.Query AS id, SUM(q.Impressions) AS impressions, SUM(q.Clicks) AS clicks,
           CAST(SUM(q.Position * q.Impressions) / NULLIF(SUM(q.Impressions), 0) AS DECIMAL(9, 1)) AS position,
           MAX(q.PageID) AS top_page_id
    FROM dbo.MktGscDaily q
    WHERE q.Grain = N'query' AND q.MetricDate > DATEADD(day, -28, CAST(@e AS date))
      AND NOT EXISTS (SELECT 1 FROM dbo.MktPageKeyword k WHERE k.Keyword = q.Query)
    GROUP BY q.Query
    ORDER BY SUM(q.Impressions) DESC`, range);

  return {
    period: { start, end, prior_start: priorStart, prior_end: priorEnd },
    search: { clicks: n(search?.clicks), impressions: n(search?.impressions), prior_clicks: n(search?.prior_clicks), prior_impressions: n(search?.prior_impressions), latest_day: search?.latest_day || null },
    site_visits: ga4,
    assets: assets.map((row) => ({ ...row, title: oneLine(row.title || '', 90), score: n(row.score) })),
    campaigns: campaigns.map((row) => ({ ...row, name: oneLine(row.name || '', 90), score: n(row.score) })),
    content: content.map((row) => ({ ...row, title: oneLine(row.title || '', 90), score: n(row.score) })),
    topics: topics.slice(0, 15).map((row) => ({
      ...row, title: oneLine(row.title || '', 90), score: row.score === null ? null : n(row.score), emerging: Boolean(row.emerging),
    })),
    interests: interests.map((row) => ({ ...row, score: row.score === null ? null : n(row.score), weight: n(row.weight) })),
    responses: Object.fromEntries(Object.entries(responses || {}).map(([key, value]) => [key, n(value)])),
    pages_with_issues: pages,
    unmapped_queries: queries.map((row) => ({ ...row, impressions: n(row.impressions), clicks: n(row.clicks), position: row.position === null ? null : n(row.position) })),
  };
}

function factIds(facts) {
  const ids = Object.fromEntries(REF_TYPES.map((type) => [type, new Set()]));
  facts.assets.forEach((row) => ids.asset.add(String(row.id)));
  facts.campaigns.forEach((row) => ids.campaign.add(String(row.id)));
  facts.content.forEach((row) => ids.content.add(String(row.id)));
  facts.topics.forEach((row) => ids.topic.add(String(row.id)));
  facts.interests.forEach((row) => ids.interest.add(String(row.id)));
  facts.pages_with_issues.forEach((row) => ids.page.add(String(row.id)));
  facts.unmapped_queries.forEach((row) => ids.query.add(String(row.id)));
  return ids;
}

function refHref(ref) {
  switch (ref.type) {
    case 'asset': return `/marketing/campaigns/asset.php?id=${ref.id}`;
    case 'campaign': return `/marketing/campaigns/campaign.php?id=${ref.id}`;
    case 'topic': return `/marketing/topics/view.php?id=${ref.id}`;
    case 'interest': return `/marketing/interests/edit.php?id=${ref.id}`;
    case 'content': return `/marketing/content/view.php?id=${ref.id}`;
    case 'page': return `/marketing/pages/view.php?id=${ref.id}`;
    case 'query': return '/marketing/performance/?tab=search';
    default: return '/marketing/performance/';
  }
}

function refLabel(ref, facts) {
  const find = (list) => list.find((row) => String(row.id) === String(ref.id));
  const scored = (row) => (row && row.score !== null && row.score !== undefined ? ` (score ${row.score}${row.confidence ? `, ${row.confidence} confidence` : ''})` : '');
  switch (ref.type) {
    case 'asset': return `asset #${ref.id}${scored(find(facts.assets))}`;
    case 'campaign': { const row = find(facts.campaigns); return `campaign "${row?.name || ref.id}"${scored(row)}`; }
    case 'topic': { const row = find(facts.topics); return `topic "${row?.title || ref.id}"${scored(row)}`; }
    case 'interest': { const row = find(facts.interests); return `interest "${row?.name || ref.id}"${scored(row)}`; }
    case 'content': { const row = find(facts.content); return `content "${row?.title || ref.id}"${scored(row)}`; }
    case 'page': { const row = find(facts.pages_with_issues); return `page ${row?.path || ref.id}`; }
    case 'query': return `search query "${ref.id}"`;
    default: return `${ref.type} ${ref.id}`;
  }
}

/** Keep recommendations whose type is known and that cite at least one item actually in the facts. */
function validateRecommendations(parsed, facts, maxTasks) {
  const ids = factIds(facts);
  const kept = [];
  const dropped = [];
  for (const rec of Array.isArray(parsed?.recommendations) ? parsed.recommendations : []) {
    const refs = (Array.isArray(rec?.refs) ? rec.refs : [])
      .map((ref) => ({ type: String(ref?.type || '').toLowerCase(), id: String(ref?.id ?? '').trim() }))
      .filter((ref) => ids[ref.type]?.has(ref.id));
    const title = oneLine(rec?.title || '', 200);
    let reason = null;
    if (!REC_TYPES.has(rec?.type)) reason = 'unknown type';
    else if (!title) reason = 'no title';
    else if (refs.length === 0) reason = 'cites nothing in the facts';
    else if (kept.length >= maxTasks) reason = 'over the task limit';
    if (reason) {
      dropped.push({ ...rec, reason });
      continue;
    }
    kept.push({
      type: rec.type,
      title,
      detail: oneLine(rec.detail || '', 1500),
      role: TASK_ROLES.has(rec.role) ? rec.role : 'coordinator',
      refs,
    });
  }
  return { kept, dropped };
}

/**
 * Monday: gather last week's facts, ask for a short narrative plus ≤ N recommendations, keep only the ones that
 * trace to facts, and open one task per recommendation. Sparse weeks get a plain note and no AI call.
 */
async function digest(params = {}) {
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  const force = params.force === true || params.force === 1 || params.force === '1';
  const { start, end } = lastWeek(params);
  const pool = await connectPool(getProductionDatabase());
  try {
    const settings = await loadSettings(pool);
    const maxTasks = Math.max(0, Math.min(10, settingNumber(settings, 'engagement.digest_max_tasks', 5)));
    const existing = (await query(pool, 'SELECT DigestID FROM dbo.MktDigest WHERE PeriodEnd = @e', { e: end }))[0];
    if (existing && !force) {
      return { ok: true, skipped: true, digest_id: existing.DigestID, message: `The digest for the week ending ${end} already exists.` };
    }

    const facts = await gatherFacts(pool, start, end);
    const sparse = facts.assets.length === 0 && facts.content.length === 0
      && facts.site_visits.sessions === 0 && facts.search.impressions === 0;
    let narrative;
    let kept = [];
    let dropped = [];
    let ai = null;
    if (sparse) {
      narrative = `Not enough data for a digest this week (${start} to ${end}): no posted campaign assets or published content have been scored, and the site recorded no visits or search impressions.`;
    } else {
      ai = await runPrompt(pool, settings, {
        promptKey: DIGEST_PROMPT_KEY,
        vars: {
          brand_name: settings['brand.name'] || 'NutraAxis',
          period_start: start,
          period_end: end,
          max_tasks: maxTasks,
          facts: JSON.stringify(facts),
        },
        operation: DIGEST_PROMPT_KEY,
        processLogId,
        refType: 'digest',
      });
      const parsed = extractJson(ai.text);
      if (!parsed || typeof parsed !== 'object' || !parsed.narrative) {
        throw new Error('The digest reply could not be read — try again.');
      }
      narrative = String(parsed.narrative).slice(0, 8000);
      ({ kept, dropped } = validateRecommendations(parsed, facts, maxTasks));
    }

    const dueDate = addDays(localDate(), 7);
    const tasks = kept.map((rec) => ({
      Title: rec.title,
      Detail: `${rec.detail}\n\nEvidence: ${rec.refs.map((ref) => refLabel(ref, facts)).join('; ')}.\nFrom the digest for ${start} – ${end}.`.slice(0, 4000),
      TaskType: `digest_${rec.type}`.slice(0, 80),
      AssigneeRole: rec.role,
      Href: refHref(rec.refs[0]),
    }));

    const result = await pool.request()
      .input('start', sql.Date, start)
      .input('end', sql.Date, end)
      .input('status', sql.NVarChar(20), sparse ? 'sparse' : 'ready')
      .input('narrative', sql.NVarChar(sql.MAX), narrative)
      .input('facts', sql.NVarChar(sql.MAX), JSON.stringify(facts))
      .input('recs', sql.NVarChar(sql.MAX), JSON.stringify(kept))
      .input('dropped', sql.NVarChar(sql.MAX), dropped.length ? JSON.stringify(dropped) : null)
      .input('count', sql.Int, tasks.length)
      .input('model', sql.NVarChar(100), ai?.model || null)
      .input('version', sql.Int, ai?.promptVersion || null)
      .input('cost', sql.Decimal(12, 6), ai?.costUsd ?? null)
      .input('log', sql.Int, processLogId)
      .input('user', sql.Int, userId)
      .input('tasks', sql.NVarChar(sql.MAX), JSON.stringify(tasks))
      .input('due', sql.Date, dueDate)
      .query(`
        SET XACT_ABORT ON;
        BEGIN TRANSACTION;
        DECLARE @old INT = (SELECT DigestID FROM dbo.MktDigest WHERE PeriodEnd = @end);
        IF @old IS NOT NULL
        BEGIN
          UPDATE dbo.MktTask SET Status = N'cancelled', UpdatedAt = SYSUTCDATETIME(),
                 CompletionNote = N'Replaced by a regenerated digest.'
          WHERE RefType = N'digest' AND RefID = @old AND Status = N'open';
          DELETE FROM dbo.MktDigest WHERE DigestID = @old;
        END
        INSERT INTO dbo.MktDigest (PeriodStart, PeriodEnd, Status, Narrative, FactsJson, RecommendationsJson, DroppedJson, TaskCount,
                                   Model, PromptVersion, CostUsd, ProcessLogID, CreatedBy)
        VALUES (@start, @end, @status, @narrative, @facts, @recs, @dropped, @count, @model, @version, @cost, @log, @user);
        DECLARE @id INT = SCOPE_IDENTITY();
        INSERT INTO dbo.MktTask (Title, Detail, TaskType, AssigneeRole, RefType, RefID, Href, DueDate, Priority, CreatedBy)
        SELECT Title, Detail, TaskType, AssigneeRole, N'digest', @id, Href, @due, N'normal', @user
        FROM OPENJSON(@tasks) WITH (Title NVARCHAR(600) '$.Title', Detail NVARCHAR(4000) '$.Detail', TaskType NVARCHAR(80) '$.TaskType',
                                   AssigneeRole NVARCHAR(20) '$.AssigneeRole', Href NVARCHAR(1000) '$.Href');
        COMMIT TRANSACTION;
        SELECT @id AS DigestID;
      `);

    return {
      ok: true,
      digest_id: result.recordset[0].DigestID,
      period_start: start,
      period_end: end,
      sparse,
      tasks: tasks.length,
      dropped: dropped.length,
      cost_usd: ai?.costUsd || 0,
    };
  } finally {
    await pool.close();
  }
}

module.exports = { score, triage, digest };
