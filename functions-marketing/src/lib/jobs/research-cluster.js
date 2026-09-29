const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingNumber } = require('../mkt/settings');
const { runPrompt, extractJson, extractArrayObjects } = require('../mkt/ai');
const { loadTaxonomy, matchVocabulary, oneLine } = require('../mkt/taxonomy');

const PROMPT_KEY = 'research.cluster_topics';
const MIN_NEW_ITEMS = 8;
const MIN_ITEMS_PER_TOPIC = 2;
const OPEN_TOPIC_DAYS = 60;
const MAX_OPEN_TOPICS = 80;
const ITEM_INTEREST_MIN = 0.5;

/**
 * Recompute trend signals for topics from their items (dates use PublishedAt, falling back to FetchedAt).
 * TrendScore favours recent volume, week-over-week growth, source diversity and hard evidence.
 * Keep in step with mkt_topic_refresh_metrics() in includes/marketing-topics.php.
 */
async function refreshTopicMetrics(pool, topicIds) {
  const ids = [...new Set(topicIds.map(Number).filter(Number.isInteger))];
  if (ids.length === 0) return;
  await pool.request().query(`
    WITH stats AS (
      SELECT ti.TopicID,
             COUNT(*) AS ItemCount,
             SUM(CASE WHEN COALESCE(h.PublishedAt, h.FetchedAt) >= DATEADD(DAY, -7, SYSUTCDATETIME()) THEN 1 ELSE 0 END) AS Items7d,
             SUM(CASE WHEN COALESCE(h.PublishedAt, h.FetchedAt) >= DATEADD(DAY, -14, SYSUTCDATETIME())
                       AND COALESCE(h.PublishedAt, h.FetchedAt) < DATEADD(DAY, -7, SYSUTCDATETIME()) THEN 1 ELSE 0 END) AS ItemsPrior7d,
             COUNT(DISTINCT h.Domain) AS SourceDiversity,
             SUM(CASE WHEN h.EvidenceType IN (N'peer_reviewed', N'regulatory') THEN 1 ELSE 0 END) AS EvidenceCount,
             MIN(COALESCE(h.PublishedAt, h.FetchedAt)) AS FirstItemAt,
             MAX(COALESCE(h.PublishedAt, h.FetchedAt)) AS LastItemAt
      FROM dbo.MktTopicItem ti
      INNER JOIN dbo.MktHarvestedItem h ON h.ItemID = ti.ItemID
      WHERE ti.TopicID IN (${ids.join(',')})
      GROUP BY ti.TopicID
    )
    UPDATE t
    SET ItemCount = s.ItemCount, Items7d = s.Items7d, ItemsPrior7d = s.ItemsPrior7d,
        SourceDiversity = s.SourceDiversity, EvidenceCount = s.EvidenceCount,
        FirstItemAt = s.FirstItemAt, LastItemAt = s.LastItemAt,
        TrendScore = s.Items7d * 2
          + CASE WHEN s.Items7d > s.ItemsPrior7d THEN s.Items7d - s.ItemsPrior7d ELSE 0 END
          + s.SourceDiversity * 1.5 + s.EvidenceCount
    FROM dbo.MktTopic t
    INNER JOIN stats s ON s.TopicID = t.TopicID
  `);
}

async function unclusteredItems(pool, lookbackDays, maxItems) {
  const items = (await pool.request()
    .input('days', sql.Int, lookbackDays)
    .input('max', sql.Int, maxItems)
    .query(`
      SELECT TOP (@max) h.ItemID, h.Title, h.AiSummary, h.EvidenceType, h.Domain,
             COALESCE(h.PublishedAt, h.FetchedAt) AS ItemDate, h.RelevanceMax, h.ScoredAt
      FROM dbo.MktHarvestedItem h
      WHERE h.Status = N'scored'
        AND h.FetchedAt >= DATEADD(DAY, -@days, SYSUTCDATETIME())
        AND NOT EXISTS (SELECT 1 FROM dbo.MktTopicItem ti WHERE ti.ItemID = h.ItemID)
      ORDER BY h.RelevanceMax DESC, h.FetchedAt DESC
    `)).recordset;
  if (items.length === 0) return items;

  const scores = (await pool.request().input('min', sql.Decimal(4, 3), ITEM_INTEREST_MIN).query(`
    SELECT sc.ItemID, i.Name
    FROM dbo.MktItemScore sc
    INNER JOIN dbo.MktInterest i ON i.InterestID = sc.InterestID
    WHERE sc.Relevance >= @min AND sc.ItemID IN (${items.map((row) => Number(row.ItemID)).join(',')})
    ORDER BY sc.ItemID, sc.Relevance DESC
  `)).recordset;
  const names = new Map();
  for (const row of scores) {
    const list = names.get(row.ItemID) || [];
    if (list.length < 2) list.push(row.Name);
    names.set(row.ItemID, list);
  }
  return items.map((row) => ({ ...row, interestNames: names.get(row.ItemID) || [] }));
}

async function itemsScoredSinceLastCluster(pool, items) {
  const result = await pool.request().query(`
    SELECT MAX(StartedAt) AS LastRun
    FROM dbo.ProcessExecutionLog
    WHERE ProcessCode = N'research-cluster-topics' AND Status = N'Success'
      AND (ResultJson IS NULL OR ResultJson NOT LIKE N'%"skipped":true%')
  `);
  const lastRun = result.recordset[0]?.LastRun ? new Date(result.recordset[0].LastRun).getTime() : 0;
  return items.filter((row) => row.ScoredAt && new Date(row.ScoredAt).getTime() > lastRun).length;
}

async function openTopics(pool) {
  return (await pool.request()
    .input('days', sql.Int, OPEN_TOPIC_DAYS)
    .input('max', sql.Int, MAX_OPEN_TOPICS)
    .query(`
      SELECT TOP (@max) t.TopicID, t.Title, t.Status, i.Name AS InterestName
      FROM dbo.MktTopic t
      LEFT JOIN dbo.MktInterest i ON i.InterestID = t.InterestID
      WHERE t.Status IN (N'proposed', N'accepted', N'parked') AND t.UpdatedAt >= DATEADD(DAY, -@days, SYSUTCDATETIME())
      ORDER BY t.UpdatedAt DESC
    `)).recordset;
}

function itemLine(row) {
  const date = row.ItemDate ? new Date(row.ItemDate).toISOString().slice(0, 10) : 'undated';
  const interests = row.interestNames.length ? row.interestNames.join('; ') : 'general';
  const summary = row.AiSummary ? ` — ${oneLine(row.AiSummary, 220)}` : '';
  return `#${row.ItemID} [${row.EvidenceType || 'unknown'}] [${interests}] ${oneLine(row.Title, 160)}${summary} (${row.Domain || 'unknown'}, ${date})`;
}

/** Models sometimes echo ids in the "#123" form used in the item lines. */
function parseItemId(value) {
  const digits = String(value ?? '').replace(/[^0-9]/g, '');
  return digits ? Number(digits) : NaN;
}

async function attachItems(pool, topicId, itemIds) {
  const list = itemIds.map(Number).join(',');
  await pool.request().input('topic', sql.Int, topicId).query(`
    INSERT INTO dbo.MktTopicItem (TopicID, ItemID)
    SELECT @topic, h.ItemID FROM dbo.MktHarvestedItem h
    WHERE h.ItemID IN (${list})
      AND NOT EXISTS (SELECT 1 FROM dbo.MktTopicItem ti WHERE ti.TopicID = @topic AND ti.ItemID = h.ItemID);
    UPDATE dbo.MktHarvestedItem SET Status = N'clustered' WHERE ItemID IN (${list}) AND Status = N'scored';
  `);
}

async function createTopic(pool, topic, context) {
  const suggested = topic.emerging && topic.suggested_interest && typeof topic.suggested_interest === 'object'
    ? topic.suggested_interest : null;
  const terms = suggested && Array.isArray(suggested.include_terms)
    ? suggested.include_terms.map((term) => String(term || '').trim()).filter(Boolean).slice(0, 8) : [];
  const interestId = context.taxonomy.interestIds.has(Number(topic.interest_id)) ? Number(topic.interest_id) : null;
  const inserted = await pool.request()
    .input('title', sql.NVarChar(300), oneLine(topic.title, 300))
    .input('summary', sql.NVarChar(2000), topic.summary ? String(topic.summary).slice(0, 2000) : null)
    .input('why', sql.NVarChar(1000), topic.why_it_matters ? String(topic.why_it_matters).slice(0, 1000) : null)
    .input('area', sql.NVarChar(100), matchVocabulary([topic.therapeutic_area], context.taxonomy.areas)[0] || null)
    .input('interest', sql.Int, interestId)
    .input('emerging', sql.Bit, suggested ? 1 : 0)
    .input('suggested_name', sql.NVarChar(200), suggested?.name ? oneLine(suggested.name, 200) : null)
    .input('suggested_terms', sql.NVarChar(2000), terms.length ? JSON.stringify(terms) : null)
    .input('log_id', sql.Int, context.processLogId)
    .input('version', sql.Int, context.promptVersion)
    .query(`
      INSERT INTO dbo.MktTopic (Title, Summary, WhyItMatters, TherapeuticArea, InterestID, IsEmerging,
        SuggestedInterestName, SuggestedTermsJson, ClusterLogID, PromptVersion)
      OUTPUT INSERTED.TopicID
      VALUES (@title, @summary, @why, @area, @interest, @emerging, @suggested_name, @suggested_terms, @log_id, @version)
    `);
  return inserted.recordset[0].TopicID;
}

/**
 * Group recent scored items into Topic Board candidates. Items can extend an open topic instead of
 * creating a duplicate. Skips when too few new items are waiting unless params.force is set.
 */
async function run(params = {}) {
  const processLogId = Number(params.log_id || 0) || null;
  const force = params.force === true || params.force === 1 || params.force === '1';
  const pool = await connectPool(getProductionDatabase());
  const totals = { items: 0, topics_returned: 0, topics_created: 0, topics_extended: 0, items_assigned: 0, emerging: 0, cost_usd: 0 };

  try {
    const settings = await loadSettings(pool);
    const items = await unclusteredItems(
      pool,
      settingNumber(settings, 'research.cluster_lookback_days', 21),
      settingNumber(settings, 'research.cluster_max_items', 250)
    );
    totals.items = items.length;
    const fresh = await itemsScoredSinceLastCluster(pool, items);
    if (items.length < MIN_ITEMS_PER_TOPIC || (!force && fresh < MIN_NEW_ITEMS)) {
      return {
        ok: true, error: null, skipped: true,
        message: `${fresh} newly scored items since the last clustering run — waits for ${MIN_NEW_ITEMS}.`,
        ...totals,
      };
    }

    const taxonomy = await loadTaxonomy(pool, settings);
    const topics = await openTopics(pool);
    const response = await runPrompt(pool, settings, {
      promptKey: PROMPT_KEY,
      operation: PROMPT_KEY,
      processLogId,
      vars: {
        brand_name: settings['brand.name'] || 'NutraAxis',
        interests: taxonomy.interestNameLines,
        therapeutic_areas: taxonomy.areas.join('\n'),
        open_topics: topics.length
          ? topics.map((row) => `${row.TopicID} | ${row.Status} | ${row.InterestName || '-'} | ${row.Title}`).join('\n')
          : '(none yet)',
        items: items.map(itemLine).join('\n'),
      },
    });
    totals.cost_usd = Math.round(response.costUsd * 10000) / 10000;

    const parsed = extractJson(response.text);
    const list = parsed && !Array.isArray(parsed) && Array.isArray(parsed.topics)
      ? parsed.topics
      : extractArrayObjects(response.text, 'topics');
    if (list.length === 0) {
      throw new Error(`Model did not return {"topics": [...]} (stop: ${response.stopReason}). `
        + `Reply began: "${String(response.text || '').replace(/\s+/g, ' ').slice(0, 160)}"`);
    }
    totals.topics_returned = list.length;
    totals.truncated = response.stopReason === 'max_tokens';
    const available = new Set(items.map((row) => Number(row.ItemID)));
    const openIds = new Set(topics.map((row) => row.TopicID));
    const touched = [];
    const context = { taxonomy, processLogId, promptVersion: response.promptVersion };

    for (const topic of list) {
      if (!topic || typeof topic !== 'object') continue;
      const itemIds = [...new Set((Array.isArray(topic.item_ids) ? topic.item_ids : []).map(parseItemId))]
        .filter((id) => available.has(id));
      const existingId = parseItemId(topic.existing_topic_id) || 0;
      if (openIds.has(existingId)) {
        if (itemIds.length === 0) continue;
        await attachItems(pool, existingId, itemIds);
        await pool.request().input('id', sql.Int, existingId)
          .query('UPDATE dbo.MktTopic SET UpdatedAt = SYSUTCDATETIME() WHERE TopicID = @id');
        totals.topics_extended += 1;
        touched.push(existingId);
      } else {
        if (itemIds.length < MIN_ITEMS_PER_TOPIC || !String(topic.title || '').trim()) continue;
        const topicId = await createTopic(pool, topic, context);
        await attachItems(pool, topicId, itemIds);
        totals.topics_created += 1;
        if (topic.emerging && topic.suggested_interest) totals.emerging += 1;
        touched.push(topicId);
      }
      for (const id of itemIds) available.delete(id);
      totals.items_assigned += itemIds.length;
    }

    await refreshTopicMetrics(pool, touched);
    return { ok: true, error: null, ...totals };
  } finally {
    await pool.close();
  }
}

module.exports = { run, refreshTopicMetrics };
