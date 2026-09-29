const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingNumber, recordUsage } = require('../mkt/settings');
const { harvest } = require('../mkt/adapters');
const store = require('../mkt/store');

const SCHEDULE_HOURS = { hourly: 1, daily: 24, weekly: 168 };
const MAX_SOURCES_PER_RUN = 40;
const MAX_RUN_MS = 8 * 60 * 1000;

async function dueSources(pool, sourceId) {
  const request = pool.request();
  let where = `Status = N'active' AND (NextRunAt IS NULL OR NextRunAt <= SYSUTCDATETIME())`;
  if (sourceId) {
    request.input('source_id', sql.Int, sourceId);
    where = 'SourceID = @source_id';
  }
  const result = await request.query(`
    SELECT TOP (${MAX_SOURCES_PER_RUN}) SourceID, Name, SourceType, Url, Query, ConfigJson, Schedule, ConsecutiveFailures
    FROM dbo.MktSource
    WHERE ${where}
    ORDER BY COALESCE(NextRunAt, '2000-01-01') ASC, SourceID ASC
  `);
  return result.recordset;
}

async function recordSourceSuccess(pool, source, inserted) {
  await pool.request()
    .input('id', sql.Int, source.SourceID)
    .input('hours', sql.Int, SCHEDULE_HOURS[source.Schedule] || 24)
    .input('inserted', sql.Int, inserted)
    .query(`
      UPDATE dbo.MktSource
      SET LastRunAt = SYSUTCDATETIME(), LastSuccessAt = SYSUTCDATETIME(),
          NextRunAt = DATEADD(HOUR, @hours, SYSUTCDATETIME()),
          LastError = NULL, ConsecutiveFailures = 0, ItemsTotal = ItemsTotal + @inserted
      WHERE SourceID = @id
    `);
}

async function recordSourceFailure(pool, source, error, pauseAfter) {
  const failures = Number(source.ConsecutiveFailures || 0) + 1;
  const autoPause = failures >= pauseAfter;
  await pool.request()
    .input('id', sql.Int, source.SourceID)
    .input('hours', sql.Int, SCHEDULE_HOURS[source.Schedule] || 24)
    .input('error', sql.NVarChar(1000), String(error).slice(0, 1000))
    .input('failures', sql.Int, failures)
    .input('status', sql.NVarChar(20), autoPause ? 'auto_paused' : 'active')
    .query(`
      UPDATE dbo.MktSource
      SET LastRunAt = SYSUTCDATETIME(), NextRunAt = DATEADD(HOUR, @hours, SYSUTCDATETIME()),
          LastError = @error, ConsecutiveFailures = @failures, Status = @status
      WHERE SourceID = @id
    `);
  return autoPause;
}

async function run(params = {}) {
  const sourceId = Number(params.source_id || params.sourceId || 0) || null;
  const processLogId = Number(params.log_id || 0) || null;
  const pool = await connectPool(getProductionDatabase());
  const totals = { sources: 0, fetched: 0, inserted: 0, duplicates: 0, failed: 0, auto_paused: 0, errors: [] };
  const started = Date.now();

  try {
    const settings = await loadSettings(pool);
    const ctx = {
      userAgent: settings['harvest.user_agent'] || 'NutraAxisResearchBot/1.0',
      contactEmail: process.env.HARVEST_CONTACT_EMAIL || process.env.MAIL_REPLY_TO || 'marketing@nutraaxislabs.com',
      maxItems: settingNumber(settings, 'harvest.max_items_per_run', 50),
      lookbackDays: settingNumber(settings, 'harvest.lookback_days', 30),
      crawlDelayMs: settingNumber(settings, 'harvest.crawl_delay_ms', 1500),
      isKnownUrl: (url) => store.isKnownUrl(pool, url),
    };
    const pauseAfter = settingNumber(settings, 'harvest.auto_pause_failures', 5);
    const recentSimhashes = await store.loadRecentSimhashes(pool);
    const sources = await dueSources(pool, sourceId);

    for (const source of sources) {
      if (Date.now() - started > MAX_RUN_MS) break;
      totals.sources += 1;
      const runId = await store.startRun(pool, { sourceId: source.SourceID, runType: source.SourceType, processLogId });
      const counts = { fetched: 0, inserted: 0, duplicates: 0 };
      try {
        const { items } = await harvest(source, ctx);
        counts.fetched = items.length;
        for (const item of items) {
          const outcome = await store.insertItem(pool, {
            ...item,
            sourceId: source.SourceID,
            harvestRunId: runId,
            sourceType: source.SourceType,
          }, recentSimhashes);
          counts[outcome === 'inserted' ? 'inserted' : 'duplicates'] += 1;
        }
        await store.finishRun(pool, runId, { status: 'success', ...counts });
        await recordSourceSuccess(pool, source, counts.inserted);
        await recordUsage(pool, {
          provider: 'http', operation: `harvest.${source.SourceType}`, mode: 'api', units: 1,
          processLogId, refType: 'source', refId: source.SourceID,
        });
      } catch (error) {
        totals.failed += 1;
        totals.errors.push(`${source.Name}: ${error.message}`);
        await store.finishRun(pool, runId, { status: 'failed', ...counts, error: error.message });
        if (await recordSourceFailure(pool, source, error.message, pauseAfter)) {
          totals.auto_paused += 1;
        }
      }
      totals.fetched += counts.fetched;
      totals.inserted += counts.inserted;
      totals.duplicates += counts.duplicates;
    }

    return {
      ok: true,
      error: null,
      ...totals,
      errors: totals.errors.slice(0, 20),
    };
  } finally {
    await pool.close();
  }
}

module.exports = { run };
