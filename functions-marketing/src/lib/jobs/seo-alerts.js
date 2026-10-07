const crypto = require('crypto');
const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingLines, settingNumber } = require('../mkt/settings');
const { sendMail } = require('../mkt/mail');

const RULES = ['job_failed', 'traffic_drop', 'legacy_brand', 'site_error', 'escalation_overdue', 'ai_budget', 'rank_drop'];
const SEVERITY_ORDER = { high: 0, medium: 1, low: 2 };
const WEBHOOK_TIMEOUT_MS = 15000;

const fingerprint = (rule, key) => crypto.createHash('sha256').update(`alert:${rule}:${key}`).digest('hex');
const clip = (text, max) => (text == null ? null : String(text).slice(0, max));
const pct = (value) => `${Math.round(value)}%`;

function portalUrl(path) {
  const base = String(process.env.PORTAL_URL || process.env.SITE_URL || 'https://nutraaxisweb.azurewebsites.net').replace(/\/+$/, '');
  return `${base}${path}`;
}

/** Latest scheduled run of each marketing job in the last 7 days, when it failed. Manual runs report their own errors. */
async function jobFailed(pool, jobs) {
  const codes = Object.keys(jobs);
  const rows = (await pool.request().input('codes', sql.NVarChar(sql.MAX), JSON.stringify(codes)).query(`
    SELECT ProcessCode, Status, ErrorMessage, ResultMessage, CONVERT(varchar(16), StartedAt, 120) AS StartedAt
    FROM (
      SELECT ProcessCode, Status, ErrorMessage, ResultMessage, StartedAt,
             ROW_NUMBER() OVER (PARTITION BY ProcessCode ORDER BY StartedAt DESC) AS rn
      FROM dbo.ProcessExecutionLog
      WHERE ProcessCode IN (SELECT value FROM OPENJSON(@codes)) AND TriggerType = N'Scheduled'
        AND Status <> N'Running' AND StartedAt >= DATEADD(DAY, -7, SYSUTCDATETIME())
    ) latest
    WHERE rn = 1 AND Status IN (N'Failed', N'Abandoned')
  `)).recordset;
  return rows.map((row) => ({
    rule: 'job_failed',
    key: row.ProcessCode,
    subject: row.ProcessCode,
    severity: 'medium',
    title: `Scheduled job failed: ${jobs[row.ProcessCode] || row.ProcessCode}`,
    detail: `Last scheduled run ${row.StartedAt} UTC was ${row.Status.toLowerCase()}: ${row.ErrorMessage || row.ResultMessage || 'no error recorded'}`,
    href: `/process-log/?process_code=${encodeURIComponent(row.ProcessCode)}`,
  }));
}

/**
 * Last 7 days vs the weekly average of the 4 weeks before, anchored on the newest ingested day.
 * Needs all 35 days and a baseline big enough to judge; otherwise it stays quiet.
 */
async function trafficDrop(pool, settings) {
  const dropPct = settingNumber(settings, 'alerts.traffic_drop_pct', 30);
  const sources = [
    { key: 'ga4_sessions', label: 'GA4 sessions', table: 'dbo.MktGa4Daily', grain: 'channel', metric: 'Sessions', min: settingNumber(settings, 'alerts.traffic_min_sessions', 100), href: '/marketing/performance/' },
    { key: 'gsc_clicks', label: 'Search Console clicks', table: 'dbo.MktGscDaily', grain: 'site', metric: 'Clicks', min: settingNumber(settings, 'alerts.traffic_min_clicks', 50), href: '/marketing/performance/' },
  ];
  const alerts = [];
  const notes = [];
  for (const source of sources) {
    const row = (await pool.request().input('grain', sql.NVarChar(10), source.grain).query(`
      DECLARE @end DATE = (SELECT MAX(MetricDate) FROM ${source.table} WHERE Grain = @grain);
      SELECT CONVERT(varchar(10), @end, 23) AS EndDate,
             SUM(CASE WHEN MetricDate > DATEADD(DAY, -7, @end) THEN ${source.metric} ELSE 0 END) AS Recent,
             SUM(CASE WHEN MetricDate <= DATEADD(DAY, -7, @end) THEN ${source.metric} ELSE 0 END) / 4.0 AS Baseline,
             COUNT(DISTINCT MetricDate) AS Days
      FROM ${source.table}
      WHERE Grain = @grain AND MetricDate > DATEADD(DAY, -35, @end)
    `)).recordset[0];
    if (!row?.EndDate || row.Days < 35) {
      notes.push(`${source.label}: ${row?.Days || 0} of 35 days — no baseline yet`);
      continue;
    }
    const baseline = Number(row.Baseline);
    const recent = Number(row.Recent);
    if (baseline < source.min) {
      notes.push(`${source.label}: baseline ${Math.round(baseline)}/week is under ${source.min} — too small to judge`);
      continue;
    }
    const change = ((recent - baseline) / baseline) * 100;
    notes.push(`${source.label}: ${recent} vs ${Math.round(baseline)}/week (${change >= 0 ? '+' : ''}${pct(change)})`);
    if (change <= -dropPct) {
      alerts.push({
        rule: 'traffic_drop',
        key: source.key,
        subject: source.key,
        severity: change <= -2 * dropPct ? 'high' : 'medium',
        title: `${source.label} down ${pct(-change)} week over baseline`,
        detail: `${recent} in the 7 days to ${row.EndDate}, against an average of ${Math.round(baseline)} a week over the 4 weeks before (threshold ${dropPct}%).`,
        href: source.href,
      });
    }
  }
  return { alerts, notes };
}

async function legacyBrand(pool) {
  const rows = (await pool.request().query(`
    SELECT u.IssueUrlID, u.Url, u.Detail, i.IssueID
    FROM dbo.MktIssueUrl u JOIN dbo.MktIssue i ON i.IssueID = u.IssueID
    WHERE i.Code = N'legacy_brand' AND u.Status = N'open' AND i.Status <> N'ignored'
  `)).recordset;
  return rows.map((row) => ({
    rule: 'legacy_brand',
    key: row.Url,
    subject: clip(row.Url, 300),
    severity: 'high',
    title: `Legacy brand name on a live page`,
    detail: `${row.Url} — ${row.Detail || 'legacy name found'}`,
    href: `/marketing/issues/view.php?id=${row.IssueID}`,
  }));
}

async function siteError(pool) {
  const rows = (await pool.request().query(`
    SELECT IssueID, Code, Title, OpenUrlCount FROM dbo.MktIssue
    WHERE Severity = N'high' AND Status IN (N'new', N'open') AND Code <> N'legacy_brand' AND OpenUrlCount > 0
  `)).recordset;
  return rows.map((row) => ({
    rule: 'site_error',
    key: row.Code,
    subject: row.Code,
    severity: 'high',
    title: `Site issue: ${row.Title}`,
    detail: `${row.OpenUrlCount} page${row.OpenUrlCount === 1 ? '' : 's'} affected.`,
    href: `/marketing/issues/view.php?id=${row.IssueID}`,
  }));
}

async function escalationOverdue(pool) {
  const rows = (await pool.request().query(`
    SELECT ResponseID, Triage, Channel, CONVERT(varchar(16), EscalationDueAt, 120) AS DueAt
    FROM dbo.MktResponse
    WHERE Status = N'escalated' AND EscalationDueAt < SYSUTCDATETIME()
  `)).recordset;
  return rows.map((row) => ({
    rule: 'escalation_overdue',
    key: String(row.ResponseID),
    subject: `response ${row.ResponseID}`,
    severity: 'high',
    title: `Compliance review overdue: response #${row.ResponseID}`,
    detail: `${row.Triage || 'escalated'} on ${row.Channel}, due ${row.DueAt} UTC and not yet cleared.`,
    href: `/marketing/performance/response.php?id=${row.ResponseID}`,
  }));
}

/**
 * AI spend against ai.monthly_budget_usd for the UTC month. Warns at alerts.budget_warn_pct of budget or when the
 * forecast (month-to-date + trailing 7-day rate × days left) passes it; reaching the budget is a separate, high alert.
 */
async function aiBudget(pool, settings) {
  const budget = settingNumber(settings, 'ai.monthly_budget_usd', 0);
  if (!(budget > 0)) return [];
  const warnPct = settingNumber(settings, 'alerts.budget_warn_pct', 80);
  const row = (await pool.request().query(`
    DECLARE @monthStart DATETIME2 = DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1);
    SELECT CONVERT(varchar(7), @monthStart, 23) AS MonthKey,
           DATEDIFF(SECOND, @monthStart, SYSUTCDATETIME()) / 86400.0 AS Elapsed,
           DAY(EOMONTH(@monthStart)) AS DaysInMonth,
           COALESCE(SUM(CASE WHEN CreatedAt >= @monthStart THEN CostUsd END), 0) AS MonthCost,
           COALESCE(SUM(CASE WHEN CreatedAt >= DATEADD(DAY, -7, SYSUTCDATETIME()) THEN CostUsd END), 0) AS Last7Cost
    FROM dbo.MktApiUsage
    WHERE Provider IN (N'anthropic', N'openai') AND CreatedAt >= LEAST(@monthStart, DATEADD(DAY, -7, SYSUTCDATETIME()))
  `)).recordset[0];
  const spent = Number(row.MonthCost);
  const forecast = spent + (Number(row.Last7Cost) / 7) * Math.max(0, Number(row.DaysInMonth) - Number(row.Elapsed));
  const usd = (value) => `$${value.toFixed(2)}`;
  const base = {
    rule: 'ai_budget',
    subject: `ai budget ${row.MonthKey}`,
    href: '/marketing/admin/?tab=usage',
  };
  if (spent >= budget) {
    return [{
      ...base,
      key: `${row.MonthKey}:reached`,
      severity: 'high',
      title: `AI budget reached for ${row.MonthKey}: ${usd(spent)} of ${usd(budget)}`,
      detail: 'All AI calls (scheduled and on demand) are refused until the next UTC month. Raise ai.monthly_budget_usd in Admin & Jobs → Settings to resume.',
    }];
  }
  const usedPct = (spent / budget) * 100;
  if (usedPct >= warnPct || forecast >= budget) {
    return [{
      ...base,
      key: `${row.MonthKey}:warn`,
      severity: 'medium',
      title: `AI spend at ${pct(usedPct)} of the ${row.MonthKey} budget`,
      detail: `${usd(spent)} of ${usd(budget)} spent; month-end forecast ${usd(forecast)} at the last 7 days' pace (warns at ${warnPct}% or a forecast over budget).`,
    }];
  }
  return [];
}

/**
 * Tracked keywords whose average position over the 7 days to the newest observation is rank.drop_places worse than
 * the same 7 days four weeks earlier, or that left page 1. Both windows need a position.
 */
async function rankDrop(pool, settings) {
  const places = settingNumber(settings, 'rank.drop_places', 5);
  const rows = (await pool.request().input('places', sql.Decimal(6, 2), places).query(`
    DECLARE @a DATE = (SELECT MAX(ObsDate) FROM dbo.MktRankObservation WHERE Position IS NOT NULL);
    WITH w AS (
      SELECT KeywordID,
             AVG(CASE WHEN ObsDate BETWEEN DATEADD(DAY, -6, @a) AND @a THEN Position END) AS Cur,
             AVG(CASE WHEN ObsDate BETWEEN DATEADD(DAY, -34, @a) AND DATEADD(DAY, -28, @a) THEN Position END) AS Prev
      FROM dbo.MktRankObservation
      WHERE Position IS NOT NULL AND ObsDate BETWEEN DATEADD(DAY, -34, @a) AND @a
      GROUP BY KeywordID
    )
    SELECT k.KeywordID, k.Keyword, w.Cur, w.Prev
    FROM w JOIN dbo.MktKeyword k ON k.KeywordID = w.KeywordID
    WHERE k.TrackRank = 1 AND k.Status = N'active' AND w.Cur IS NOT NULL AND w.Prev IS NOT NULL
      AND ((w.Prev <= 10 AND w.Cur > 10) OR w.Cur - w.Prev >= @places)
  `)).recordset;
  return rows.map((row) => {
    const cur = Number(row.Cur);
    const prev = Number(row.Prev);
    const leftPageOne = prev <= 10 && cur > 10;
    return {
      rule: 'rank_drop',
      key: String(row.KeywordID),
      subject: clip(row.Keyword, 300),
      severity: leftPageOne ? 'high' : 'medium',
      title: `Rank drop: ${row.Keyword}`,
      detail: `Position ${cur.toFixed(1)}, was ${prev.toFixed(1)} four weeks earlier` + (leftPageOne ? ' — left page 1.' : '.'),
      href: '/marketing/ranks/?tab=movers',
    };
  });
}

/** Insert new alerts, refresh ones still firing, resolve the rest (including rules switched off). */
async function reconcile(pool, desired, processLogId) {
  const payload = desired.map((a) => ({
    fp: fingerprint(a.rule, a.key),
    rule: a.rule,
    subject: clip(a.subject, 300),
    severity: a.severity,
    title: clip(a.title, 300),
    detail: clip(a.detail, 2000),
    href: clip(a.href, 1000),
  }));
  const result = await pool.request()
    .input('alerts', sql.NVarChar(sql.MAX), JSON.stringify(payload))
    .input('log', sql.Int, processLogId)
    .query(`
      SET XACT_ABORT ON;
      BEGIN TRANSACTION;
      DECLARE @out TABLE (Act NVARCHAR(10));
      SELECT CONVERT(BINARY(32), fp, 2) AS Fingerprint, [rule], subject, severity, title, detail, href
      INTO #a
      FROM OPENJSON(@alerts) WITH (fp CHAR(64), [rule] NVARCHAR(40), subject NVARCHAR(300), severity NVARCHAR(10),
                                   title NVARCHAR(300), detail NVARCHAR(2000), href NVARCHAR(1000));

      UPDATE t SET Title = a.title, Detail = a.detail, Href = a.href, Severity = a.severity, LastAt = SYSUTCDATETIME(), ProcessLogID = @log
      OUTPUT N'kept' INTO @out
      FROM dbo.MktAlert t JOIN #a a ON a.Fingerprint = t.Fingerprint
      WHERE t.Status IN (N'open', N'acknowledged');

      INSERT INTO dbo.MktAlert (RuleKey, Fingerprint, Subject, Severity, Title, Detail, Href, ProcessLogID)
      OUTPUT N'new' INTO @out
      SELECT a.[rule], a.Fingerprint, a.subject, a.severity, a.title, a.detail, a.href, @log
      FROM #a a
      WHERE NOT EXISTS (SELECT 1 FROM dbo.MktAlert t WHERE t.Fingerprint = a.Fingerprint AND t.Status IN (N'open', N'acknowledged'));

      UPDATE t SET Status = N'resolved', ResolvedAt = SYSUTCDATETIME(), ProcessLogID = @log
      OUTPUT N'resolved' INTO @out
      FROM dbo.MktAlert t
      WHERE t.Status IN (N'open', N'acknowledged') AND NOT EXISTS (SELECT 1 FROM #a a WHERE a.Fingerprint = t.Fingerprint);

      COMMIT;
      SELECT
        SUM(CASE WHEN Act = N'new' THEN 1 ELSE 0 END) AS NewCount,
        SUM(CASE WHEN Act = N'kept' THEN 1 ELSE 0 END) AS KeptCount,
        SUM(CASE WHEN Act = N'resolved' THEN 1 ELSE 0 END) AS ResolvedCount
      FROM @out;
    `);
  const row = result.recordset[0] || {};
  return { new: row.NewCount || 0, kept: row.KeptCount || 0, resolved: row.ResolvedCount || 0 };
}

async function recipients(pool, settings) {
  const listed = settingLines(settings, 'alerts.recipients').filter((email) => email.includes('@'));
  if (listed.length) return listed;
  return (await pool.request().query(`
    SELECT u.UserLogin FROM dbo.[User] u JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
    WHERE r.Marketing LIKE N'%C%' AND r.Marketing LIKE N'%U%' AND r.Marketing LIKE N'%D%'
  `)).recordset.map((row) => String(row.UserLogin || '').trim()).filter((email) => email.includes('@'));
}

async function postWebhook(text) {
  const url = process.env.ALERTS_WEBHOOK_URL;
  if (!url) return null;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), WEBHOOK_TIMEOUT_MS);
  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text }),
      signal: controller.signal,
    });
    return response.ok ? { ok: true } : { ok: false, error: `Webhook HTTP ${response.status}` };
  } catch (error) {
    return { ok: false, error: `Webhook: ${error.message}` };
  } finally {
    clearTimeout(timer);
  }
}

/** One message listing every open alert nobody has been told about yet. */
async function notify(pool, settings) {
  const pending = (await pool.request().query(`
    SELECT AlertID, RuleKey, Severity, Title, Detail, Href FROM dbo.MktAlert
    WHERE Status = N'open' AND NotifiedAt IS NULL
  `)).recordset.sort((a, b) => SEVERITY_ORDER[a.Severity] - SEVERITY_ORDER[b.Severity] || a.AlertID - b.AlertID);
  if (pending.length === 0) return { pending: 0, notified: 0, error: null };

  const lines = pending.map((a) => `[${a.Severity.toUpperCase()}] ${a.Title}\n  ${a.Detail || ''}\n  ${portalUrl(a.Href || '/marketing/issues/?tab=alerts')}`);
  const text = `${pending.length} new marketing alert${pending.length === 1 ? '' : 's'}:\n\n${lines.join('\n\n')}\n\n`
    + `All alerts: ${portalUrl('/marketing/issues/?tab=alerts')}\nAcknowledge an alert there to keep it from being counted as new; it resolves on its own once the cause is gone.`;
  const high = pending.filter((a) => a.Severity === 'high').length;
  const subject = `Marketing alerts: ${pending.length} new${high ? ` (${high} high)` : ''} — ${pending[0].Title}`.slice(0, 200);

  const to = await recipients(pool, settings);
  const mail = await sendMail(to, subject, text);
  const hook = await postWebhook(text);
  const delivered = mail.ok || hook?.ok;
  const error = [mail.ok ? null : mail.error, hook && !hook.ok ? hook.error : null].filter(Boolean).join('; ') || null;
  await pool.request()
    .input('ids', sql.NVarChar(sql.MAX), JSON.stringify(pending.map((a) => a.AlertID)))
    .input('ok', sql.Bit, delivered ? 1 : 0)
    .input('error', sql.NVarChar(500), clip(error, 500))
    .query(`
      UPDATE dbo.MktAlert SET NotifiedAt = CASE WHEN @ok = 1 THEN SYSUTCDATETIME() ELSE NotifiedAt END, NotifyError = @error
      WHERE AlertID IN (SELECT CAST(value AS INT) FROM OPENJSON(@ids))
    `);
  return { pending: pending.length, notified: delivered ? pending.length : 0, recipients: mail.ok ? mail.sent : 0, webhook: Boolean(hook?.ok), error };
}

/**
 * seo-alerts: evaluate the rules switched on in alerts.rules, keep MktAlert in step, and email what is new.
 * @param {object} params  notify=false skips email/webhook (alerts stay un-notified for the next run)
 * @param {Record<string,string>} jobs  marketing job code → display name, for job_failed
 */
async function run(params = {}, jobs = {}) {
  const processLogId = Number(params.log_id || 0) || null;
  const pool = await connectPool(getProductionDatabase());
  try {
    const settings = await loadSettings(pool);
    const enabled = settingLines(settings, 'alerts.rules').filter((rule) => RULES.includes(rule));
    const desired = [];
    let trafficNotes = [];
    for (const rule of enabled) {
      if (rule === 'job_failed') desired.push(...await jobFailed(pool, jobs));
      if (rule === 'traffic_drop') {
        const traffic = await trafficDrop(pool, settings);
        desired.push(...traffic.alerts);
        trafficNotes = traffic.notes;
      }
      if (rule === 'legacy_brand') desired.push(...await legacyBrand(pool));
      if (rule === 'site_error') desired.push(...await siteError(pool));
      if (rule === 'escalation_overdue') desired.push(...await escalationOverdue(pool));
      if (rule === 'ai_budget') desired.push(...await aiBudget(pool, settings));
      if (rule === 'rank_drop') desired.push(...await rankDrop(pool, settings));
    }
    const counts = await reconcile(pool, desired, processLogId);
    const sent = params.notify === false || params.notify === 'false' ? null : await notify(pool, settings);
    return { ok: true, rules: enabled.length, firing: desired.length, ...counts, traffic: trafficNotes, notify: sent };
  } finally {
    await pool.close();
  }
}

module.exports = { run, RULES };
