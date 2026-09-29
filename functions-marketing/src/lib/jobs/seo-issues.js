const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings } = require('../mkt/settings');
const { runPrompt } = require('../mkt/ai');
const pages = require('../mkt/pages');
const issues = require('../mkt/issues');
const seoPages = require('./seo-pages');

const FIX_SPEC_PROMPT_KEY = 'seo.fix_spec';
const FIX_SPEC_MAX_URLS = 25;

function userIdFrom(params) {
  return Number(params.triggered_by_user_id || params.user_id || 0) || null;
}

/** Accepts the whole audit_site envelope, its `data`, or a JSON string of either. */
function openRushData(raw) {
  const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
  const data = parsed?.data?.pages ? parsed.data : parsed;
  if (!data || !Array.isArray(data.pages)) {
    throw new Error('That is not an OpenRush audit_site result (no pages list).');
  }
  return data;
}

/**
 * seo-openrush-import: record an OpenRush audit_site result (run interactively in Cursor / Claude) as an audit.
 * Only pages already in the Page Inventory and active count; excluded pages are skipped as intended.
 */
async function importOpenRush(params = {}) {
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  let data;
  try {
    data = openRushData(params.audit_json);
  } catch (error) {
    return { ok: false, error: error.message.startsWith('That is') ? error.message : `The audit JSON could not be read: ${error.message}` };
  }
  const pool = await connectPool(getProductionDatabase());
  try {
    const ctx = seoPages.context(await loadSettings(pool));
    const domain = String(data.domain || data.pages[0]?.url || '').toLowerCase()
      .replace(/^https?:\/\//, '').replace(/[/?#].*$/, '').replace(/^www\./, '');
    if (domain !== ctx.siteHost) {
      return { ok: false, error: `This audit is for ${domain || 'an unknown site'}; only ${ctx.siteHost} audits can be imported.` };
    }
    const keys = data.pages.map((p) => pages.pageKey(p.url)).filter(Boolean);
    const known = new Map((await pool.request()
      .input('keys', sql.NVarChar(sql.MAX), JSON.stringify(keys))
      .query(`SELECT PageID, Url, PageKey, Status FROM dbo.MktPage WHERE PageKey IN (SELECT value FROM OPENJSON(@keys))`))
      .recordset.map((row) => [row.PageKey, row]));

    const findings = [];
    const audited = [];
    let skipped = 0;
    for (const p of data.pages) {
      const page = pages.sameSite(p.url, ctx.siteHost) ? known.get(pages.pageKey(p.url)) : null;
      if (!page || page.Status !== 'active') {
        skipped += 1;
        continue;
      }
      audited.push(page.PageID);
      for (const name of Array.isArray(p.issues) ? p.issues : []) {
        const code = issues.OPENRUSH_CODES[name] || `or_${String(name).toLowerCase().replace(/[^a-z0-9]+/g, '_').slice(0, 50)}`;
        const detail = code === 'or_low_content' ? `${p.word_count ?? 0} words (OpenRush)` : 'Found by OpenRush';
        findings.push(issues.finding(code, page, detail));
      }
    }
    if (audited.length === 0) {
      return { ok: false, error: `None of the ${data.pages.length} audited pages are active in the Page Inventory — run a crawl first.` };
    }
    const audit = await issues.recordAudit(pool, {
      source: 'openrush',
      scope: 'sample',
      findings,
      auditedPageIds: audited,
      processLogId,
      userId,
      score: Number.isFinite(Number(data.onpage_score)) ? Number(data.onpage_score) : null,
      summary: `OpenRush sampled ${data.pages_audited ?? data.pages.length} of ${data.pages_discovered ?? '?'} pages; ${audited.length} active in the inventory, ${skipped} skipped (excluded or unknown).`,
      skipped,
    });
    return { ok: true, pages: audited.length, audit };
  } finally {
    await pool.close();
  }
}

/** seo-issue-verify: recrawl every open URL of an issue, then record the recheck (verified, or back to open). */
async function verify(params = {}) {
  const issueId = Number(params.issue_id || 0);
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  if (!issueId) return { ok: false, error: 'issue_id is required.' };
  const started = Date.now();
  const pool = await connectPool(getProductionDatabase());
  try {
    const issue = (await pool.request().input('id', sql.Int, issueId)
      .query('SELECT IssueID, Code, Title, Status FROM dbo.MktIssue WHERE IssueID = @id')).recordset[0];
    if (!issue) return { ok: false, error: 'Issue not found.' };
    if (issue.Code.startsWith('or_')) {
      return { ok: false, error: 'This issue comes only from OpenRush; re-run the OpenRush audit and import it to verify.' };
    }
    const pageIds = (await pool.request().input('id', sql.Int, issueId).query(`
      SELECT DISTINCT PageID FROM dbo.MktIssueUrl WHERE IssueID = @id AND Status = N'open' AND PageID IS NOT NULL
    `)).recordset.map((row) => row.PageID);
    if (pageIds.length === 0) return { ok: true, skipped: true, issue_id: issueId, message: 'No open URLs to recheck.' };

    const ctx = seoPages.context(await loadSettings(pool));
    const list = [];
    for (const id of pageIds) list.push(...await seoPages.pagesToCrawl(pool, id));
    const { pageIds: crawled, ...totals } = await seoPages.crawlAll(pool, ctx, list, processLogId, started);
    const audit = await issues.recordCrawlAudit(pool, { scope: 'verify', pageIds: crawled, processLogId, userId, issueId });
    const after = (await pool.request().input('id', sql.Int, issueId)
      .query('SELECT Status, OpenUrlCount, VerifyNote FROM dbo.MktIssue WHERE IssueID = @id')).recordset[0];
    return { ok: true, issue_id: issueId, title: issue.Title, crawled: totals.crawled, status: after.Status, open_urls: after.OpenUrlCount, note: after.VerifyNote, audit };
  } finally {
    await pool.close();
  }
}

/** seo-fix-spec: ask for a developer fix spec for one issue from its open URLs and what the crawler saw. */
async function fixSpec(params = {}) {
  const issueId = Number(params.issue_id || 0);
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  if (!issueId) return { ok: false, error: 'issue_id is required.' };
  const pool = await connectPool(getProductionDatabase());
  try {
    const settings = await loadSettings(pool);
    const issue = (await pool.request().input('id', sql.Int, issueId)
      .query('SELECT IssueID, Code, Title, Severity, Owner, OpenUrlCount FROM dbo.MktIssue WHERE IssueID = @id')).recordset[0];
    if (!issue) return { ok: false, error: 'Issue not found.' };
    const urls = (await pool.request().input('id', sql.Int, issueId).input('max', sql.Int, FIX_SPEC_MAX_URLS).query(`
      SELECT TOP (@max) u.Url AS url, p.PageType AS page_type, p.LastStatusCode AS status, p.Title AS title,
             p.MetaDescription AS meta_description, p.H1 AS h1, p.H1Count AS h1_count, p.WordCount AS words,
             p.Canonical AS canonical, p.Robots AS robots, p.ImagesMissingAlt AS images_missing_alt, u.Detail AS detail
      FROM dbo.MktIssueUrl u LEFT JOIN dbo.MktPage p ON p.PageID = u.PageID
      WHERE u.IssueID = @id AND u.Status = N'open'
      ORDER BY p.PageType, u.Url
    `)).recordset;
    if (urls.length === 0) return { ok: false, error: 'This issue has no open URLs to write a fix spec for.' };

    const ai = await runPrompt(pool, settings, {
      promptKey: FIX_SPEC_PROMPT_KEY,
      vars: {
        brand_name: settings['brand.name'] || 'NutraAxis',
        platform_notes: settings['issues.platform_notes'] || '',
        title: issue.Title,
        code: issue.Code,
        severity: issue.Severity,
        owner: issue.Owner,
        advice: String(params.advice || '').slice(0, 500) || 'None recorded.',
        url_count: issue.OpenUrlCount,
        truncated_note: issue.OpenUrlCount > urls.length ? ` (first ${urls.length} shown)` : '',
        urls: JSON.stringify(urls),
      },
      operation: FIX_SPEC_PROMPT_KEY,
      processLogId,
      refType: 'issue',
      refId: issueId,
    });
    const spec = String(ai.text || '').replace(/^```(?:markdown|md)?\s*\n([\s\S]*?)\n```\s*$/i, '$1').trim();
    if (!spec) throw new Error('The fix spec came back empty — try again.');
    await pool.request()
      .input('id', sql.Int, issueId)
      .input('spec', sql.NVarChar(sql.MAX), spec)
      .input('model', sql.NVarChar(100), ai.model || null)
      .input('cost', sql.Decimal(12, 6), ai.costUsd ?? null)
      .input('count', sql.Int, urls.length)
      .input('user', sql.Int, userId)
      .query(`
        UPDATE dbo.MktIssue SET FixSpec = @spec, FixSpecAt = SYSUTCDATETIME(), FixSpecBy = @user, FixSpecModel = @model,
               FixSpecCostUsd = @cost, FixSpecUrlCount = @count, UpdatedAt = SYSUTCDATETIME(), UpdatedBy = @user
        WHERE IssueID = @id
      `);
    return { ok: true, issue_id: issueId, title: issue.Title, urls: urls.length, cost_usd: ai.costUsd || 0 };
  } finally {
    await pool.close();
  }
}

module.exports = { importOpenRush, verify, fixSpec };
