const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings } = require('../mkt/settings');
const { runPrompt, extractJson } = require('../mkt/ai');

const HIGHLIGHTS_PROMPT_KEY = 'reports.monthly_highlights';

async function query(pool, text, inputs = {}) {
  const request = pool.request();
  for (const [name, value] of Object.entries(inputs)) request.input(name, value);
  return (await request.query(text)).recordset;
}

/**
 * Aggregate figures only: totals, top queries / pages / channels / keywords, link domains, asset and
 * campaign titles, job names. The saved report holds no contact details.
 */
function factsFrom(data) {
  const facts = { ...data };
  delete facts.period;
  return facts;
}

/**
 * Writes the "Highlights" section for frozen monthly reports: one month when params.month is given
 * (rewriting existing highlights with force), otherwise every recently frozen report that has none.
 */
async function highlights(params = {}) {
  const processLogId = Number(params.log_id || 0) || null;
  const month = /^\d{4}-\d{2}$/.test(String(params.month || '')) ? String(params.month) : null;
  const force = params.force === true || params.force === 1 || params.force === '1';
  const pool = await connectPool(getProductionDatabase());
  try {
    const reports = month
      ? await query(pool, 'SELECT ReportID, PeriodMonth, DataJson, Highlights FROM dbo.MktReport WHERE PeriodMonth = @m', { m: month })
      : await query(pool, `SELECT ReportID, PeriodMonth, DataJson, Highlights FROM dbo.MktReport
          WHERE Highlights IS NULL AND HighlightsError IS NULL AND FrozenAt >= DATEADD(day, -40, SYSUTCDATETIME()) ORDER BY PeriodMonth`);
    if (month && reports.length === 0) {
      throw new Error(`The ${month} report is not frozen yet — freeze it first.`);
    }
    const todo = reports.filter((r) => force || !r.Highlights);
    if (todo.length === 0) {
      return { ok: true, skipped: true, message: 'No frozen report needs highlights.' };
    }

    const settings = await loadSettings(pool);
    let written = 0;
    let costUsd = 0;
    const failures = [];
    for (const report of todo) {
      try {
        const data = JSON.parse(report.DataJson);
        const period = data.period || {};
        const ai = await runPrompt(pool, settings, {
          promptKey: HIGHLIGHTS_PROMPT_KEY,
          vars: {
            brand_name: settings['brand.name'] || 'NutraAxis',
            period_label: period.label || report.PeriodMonth,
            period_start: period.start || '',
            period_end: period.end || '',
            prior_label: period.prior_label || 'the month before',
            facts: JSON.stringify(factsFrom(data)),
          },
          operation: HIGHLIGHTS_PROMPT_KEY,
          processLogId,
          refType: 'report',
          refId: report.ReportID,
        });
        costUsd += ai.costUsd || 0;
        const parsed = extractJson(ai.text);
        const text = parsed && typeof parsed.highlights === 'string' ? parsed.highlights.trim() : '';
        if (!text) {
          throw new Error('The highlights reply could not be read — try again.');
        }
        await pool.request()
          .input('id', sql.Int, report.ReportID)
          .input('text', sql.NVarChar(sql.MAX), text.slice(0, 6000))
          .input('model', sql.NVarChar(100), ai.model || null)
          .input('version', sql.Int, ai.promptVersion || null)
          .input('cost', sql.Decimal(12, 6), ai.costUsd ?? null)
          .input('log', sql.Int, processLogId)
          .query(`UPDATE dbo.MktReport SET Highlights = @text, HighlightsAt = SYSUTCDATETIME(), HighlightsModel = @model,
                    HighlightsPromptVersion = @version, HighlightsCostUsd = @cost, HighlightsError = NULL, HighlightsLogID = @log
                  WHERE ReportID = @id`);
        written += 1;
      } catch (error) {
        const message = String(error.message || error).slice(0, 1000);
        failures.push(`${report.PeriodMonth}: ${message}`);
        await pool.request()
          .input('id', sql.Int, report.ReportID)
          .input('err', sql.NVarChar(1000), message)
          .query('UPDATE dbo.MktReport SET HighlightsError = @err WHERE ReportID = @id');
      }
    }
    if (month && failures.length) {
      throw new Error(failures[0]);
    }

    return { ok: true, written, failed: failures.length, months: todo.map((r) => r.PeriodMonth), cost_usd: costUsd };
  } finally {
    await pool.close();
  }
}

module.exports = { highlights };
