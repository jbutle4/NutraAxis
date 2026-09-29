const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingNumber, settingLines, recordUsage } = require('../mkt/settings');
const pages = require('../mkt/pages');
const issues = require('../mkt/issues');

const MAX_RUN_MS = 8 * 60 * 1000;

function context(settings) {
  const siteUrl = settings['pages.site_url'] || 'https://www.nutraaxislabs.com';
  return {
    siteUrl,
    siteHost: new URL(siteUrl).hostname.toLowerCase().replace(/^www\./, ''),
    excludes: pages.compilePatterns(settingLines(settings, 'pages.exclude_patterns')),
    typeRules: pages.compileTypeRules(settingLines(settings, 'pages.type_rules')),
    delayMs: settingNumber(settings, 'pages.crawl_delay_ms', 1000),
    limits: {
      titleMin: settingNumber(settings, 'seo.title_min', 30),
      titleMax: settingNumber(settings, 'seo.title_max', 60),
      legacy: pages.legacyMatcher(settingLines(settings, 'brand.legacy_terms')),
      legacyAllow: pages.compilePatterns(settingLines(settings, 'brand.legacy_allow_paths')),
      metaMin: settingNumber(settings, 'seo.meta_min', 70),
      metaMax: settingNumber(settings, 'seo.meta_max', 160),
      thinWords: settingNumber(settings, 'seo.thin_words', 250),
    },
  };
}

function splitKeywords(text) {
  return String(text || '').split(/[\n,;]+/).map((k) => k.trim()).filter(Boolean);
}

/** Published Content Pipeline pieces become content pages, and their keywords seed the page keyword map. */
async function syncPublishedContent(pool, ctx, contentId = null) {
  const request = pool.request();
  let where = `c.Stage IN (N'published', N'monitoring') AND c.PublishedUrl IS NOT NULL AND c.PublishedUrl <> N''`;
  if (contentId) {
    request.input('cid', sql.Int, contentId);
    where += ' AND c.ContentID = @cid';
  }
  const rows = (await request.query(`
    SELECT c.ContentID, c.PublishedUrl, c.PrimaryKeyword, c.SecondaryKeywords, c.KeywordID
    FROM dbo.MktContent c WHERE ${where}
  `)).recordset;

  const linked = [];
  for (const row of rows) {
    if (!pages.sameSite(row.PublishedUrl, ctx.siteHost)) continue;
    const { pageId } = await pages.upsertPage(pool, row.PublishedUrl, { source: 'content', pageType: 'content', contentId: row.ContentID });
    if (!pageId) continue;
    await pool.request()
      .input('pid', sql.Int, pageId)
      .input('cid', sql.Int, row.ContentID)
      .query('UPDATE dbo.MktPage SET ContentID = NULL WHERE ContentID = @cid AND PageID <> @pid');
    const keywords = [
      ...(row.PrimaryKeyword ? [{ keyword: row.PrimaryKeyword, role: 'primary', keywordId: row.KeywordID }] : []),
      ...splitKeywords(row.SecondaryKeywords).map((keyword) => ({ keyword, role: 'secondary', keywordId: null })),
    ];
    for (const k of keywords) {
      await pool.request()
        .input('pid', sql.Int, pageId)
        .input('kid', sql.Int, k.keywordId ?? null)
        .input('kw', sql.NVarChar(200), k.keyword.slice(0, 200))
        .input('role', sql.NVarChar(20), k.role)
        .query(`
          IF NOT EXISTS (SELECT 1 FROM dbo.MktPageKeyword WHERE PageID = @pid AND Keyword = @kw)
            INSERT INTO dbo.MktPageKeyword (PageID, KeywordID, Keyword, Role, Source) VALUES (@pid, @kid, @kw, @role, N'content');
        `);
    }
    linked.push({ contentId: row.ContentID, pageId });
  }
  return linked;
}

async function discover(pool, ctx) {
  const { urls, errors } = await pages.sitemapUrls(ctx.siteUrl);
  const counts = { in_sitemap: 0, new_pages: 0, excluded: 0, sitemap_errors: errors };
  const seenKeys = [];
  for (const url of urls.keys()) {
    if (!pages.sameSite(url, ctx.siteHost)) continue;
    const path = pages.pathOf(url);
    const excluded = pages.isExcluded(path, ctx.excludes);
    const pageType = pages.typeFor(path, ctx.typeRules);
    const { pageId, created } = await pages.upsertPage(pool, url, {
      source: 'sitemap',
      pageType,
      status: excluded ? 'excluded' : 'active',
      inSitemap: true,
    });
    if (!pageId) continue;
    if (!created) {
      await pool.request()
        .input('id', sql.Int, pageId)
        .input('status', sql.NVarChar(20), excluded ? 'excluded' : 'active')
        .input('type', sql.NVarChar(30), pageType)
        .query(`
          UPDATE dbo.MktPage
          SET Status = CASE WHEN Source = N'sitemap' AND Status IN (N'active', N'excluded') THEN @status ELSE Status END,
              PageType = CASE WHEN PageType <> N'content' THEN @type ELSE PageType END
          WHERE PageID = @id
        `);
    }
    seenKeys.push(pageId);
    counts.in_sitemap += 1;
    if (created) counts.new_pages += 1;
    if (excluded) counts.excluded += 1;
  }
  if (seenKeys.length > 0) {
    await pool.request()
      .input('ids', sql.NVarChar(sql.MAX), seenKeys.join(','))
      .query(`
        UPDATE dbo.MktPage SET InSitemap = 0, UpdatedAt = SYSUTCDATETIME()
        WHERE InSitemap = 1 AND PageID NOT IN (SELECT TRY_CAST(value AS INT) FROM STRING_SPLIT(@ids, ','))
      `);
  }
  return counts;
}

/** Titles / meta descriptions shared by two or more live pages get a duplicate_title / duplicate_meta issue. */
async function flagDuplicates(pool) {
  for (const [column, code] of [['Title', 'duplicate_title'], ['MetaDescription', 'duplicate_meta']]) {
    await pool.request().query(`
      UPDATE p SET Issues = CASE WHEN p.Issues IS NULL OR p.Issues = N'' THEN N'${code}' ELSE p.Issues + N',${code}' END
      FROM dbo.MktPage p
      WHERE p.Status = N'active' AND p.LastStatusCode = 200 AND p.${column} IS NOT NULL
        AND (p.Issues IS NULL OR p.Issues NOT LIKE N'%${code}%')
        AND EXISTS (SELECT 1 FROM dbo.MktPage o WHERE o.PageID <> p.PageID AND o.Status = N'active' AND o.LastStatusCode = 200 AND o.${column} = p.${column});
      UPDATE p SET Issues = NULLIF(REPLACE(REPLACE(REPLACE(p.Issues, N',${code}', N''), N'${code},', N''), N'${code}', N''), N'')
      FROM dbo.MktPage p
      WHERE p.Issues LIKE N'%${code}%'
        AND NOT EXISTS (SELECT 1 FROM dbo.MktPage o WHERE o.PageID <> p.PageID AND o.Status = N'active' AND o.LastStatusCode = 200 AND o.${column} = p.${column});
    `);
  }
}

async function pagesToCrawl(pool, pageId) {
  const request = pool.request();
  let where = `Status IN (N'active', N'gone')`;
  if (pageId) {
    request.input('id', sql.Int, pageId);
    where = 'PageID = @id';
  }
  return (await request.query(`
    SELECT PageID, Url, PageKey, PageType, Status, ConsecutiveErrors
    FROM dbo.MktPage WHERE ${where}
    ORDER BY COALESCE(LastCrawledAt, '2000-01-01') ASC, PageID ASC
  `)).recordset;
}

async function crawlAll(pool, ctx, list, processLogId, started) {
  const totals = { crawled: 0, healthy: 0, errors: 0, changed: 0, with_issues: 0, skipped_time: 0, pageIds: [] };
  for (const [i, page] of list.entries()) {
    if (Date.now() - started > MAX_RUN_MS) {
      totals.skipped_time = list.length - i;
      break;
    }
    if (i > 0 && ctx.delayMs > 0) await new Promise((resolve) => setTimeout(resolve, ctx.delayMs));
    const result = await pages.crawlPage(pool, page, { siteHost: ctx.siteHost, limits: ctx.limits, processLogId });
    totals.crawled += 1;
    totals.pageIds.push(page.PageID);
    if (result.status === 200) totals.healthy += 1; else totals.errors += 1;
    if (result.changes.length > 0) totals.changed += 1;
    if (result.issues.length > 0) totals.with_issues += 1;
  }
  await flagDuplicates(pool);
  if (totals.crawled > 0) {
    await recordUsage(pool, { provider: 'http', operation: 'pages.crawl', mode: 'api', units: totals.crawled, processLogId });
  }
  return totals;
}

/**
 * seo-crawl: sitemaps + published content → inventory, then crawl every active page.
 * page_id recrawls one page; url adds a manual page on the site and crawls it.
 */
async function crawl(params = {}) {
  const pageId = Number(params.page_id || 0) || null;
  const url = String(params.url || '').trim();
  const processLogId = Number(params.log_id || 0) || null;
  const userId = Number(params.triggered_by_user_id || 0) || null;
  const started = Date.now();
  const pool = await connectPool(getProductionDatabase());
  try {
    const ctx = context(await loadSettings(pool));

    if (url) {
      if (!pages.sameSite(url, ctx.siteHost)) {
        return { ok: false, error: `Only ${ctx.siteHost} pages can be added.` };
      }
      const path = pages.pathOf(url);
      const { pageId: newId, created } = await pages.upsertPage(pool, url, { source: 'manual', pageType: pages.typeFor(path, ctx.typeRules), userId });
      await pool.request().input('id', sql.Int, newId).query(`UPDATE dbo.MktPage SET Status = N'active' WHERE PageID = @id AND Status = N'excluded'`);
      const { pageIds, ...totals } = await crawlAll(pool, ctx, await pagesToCrawl(pool, newId), processLogId, started);
      const audit = await issues.recordCrawlAudit(pool, { scope: 'page', pageIds, processLogId, userId });
      return { ok: true, mode: 'url', page_id: newId, created, ...totals, audit };
    }
    if (pageId) {
      const list = await pagesToCrawl(pool, pageId);
      if (list.length === 0) return { ok: false, error: 'Page not found.' };
      const { pageIds, ...totals } = await crawlAll(pool, ctx, list, processLogId, started);
      const audit = await issues.recordCrawlAudit(pool, { scope: 'page', pageIds, processLogId, userId });
      return { ok: true, mode: 'page', page_id: pageId, ...totals, audit };
    }

    const found = await discover(pool, ctx);
    const content = await syncPublishedContent(pool, ctx);
    const { pageIds, ...totals } = await crawlAll(pool, ctx, await pagesToCrawl(pool, null), processLogId, started);
    const audit = await issues.recordCrawlAudit(pool, { scope: 'full', pageIds, processLogId, userId });
    return { ok: true, mode: 'full', ...found, content_pages: content.length, ...totals, audit };
  } finally {
    await pool.close();
  }
}

/**
 * seo-verify-published: every published piece without a successful crawl since it was published is linked
 * to its page, crawled, and gets a system review row with the live result.
 */
async function verifyPublished(params = {}) {
  const contentId = Number(params.content_id || 0) || null;
  const processLogId = Number(params.log_id || 0) || null;
  const started = Date.now();
  const pool = await connectPool(getProductionDatabase());
  try {
    const ctx = context(await loadSettings(pool));
    const request = pool.request();
    let where = `c.Stage IN (N'published', N'monitoring') AND c.PublishedUrl IS NOT NULL AND c.PublishedUrl <> N''
      AND NOT EXISTS (SELECT 1 FROM dbo.MktPage p WHERE p.ContentID = c.ContentID AND p.LastStatusCode = 200
                        AND p.LastCrawledAt >= c.PublishedAt)`;
    if (contentId) {
      request.input('cid', sql.Int, contentId);
      where = `c.ContentID = @cid AND c.Stage IN (N'published', N'monitoring') AND c.PublishedUrl IS NOT NULL AND c.PublishedUrl <> N''`;
    }
    const due = (await request.query(`SELECT c.ContentID, c.PublishedUrl, v.Title FROM dbo.MktContent c LEFT JOIN dbo.MktContentVersion v ON v.VersionID = c.CurrentVersionID WHERE ${where}`)).recordset;
    if (due.length === 0) {
      return { ok: true, skipped: true, message: 'No published content waiting for verification.' };
    }

    const totals = { checked: 0, live: 0, problems: 0, off_site: 0 };
    for (const row of due) {
      if (Date.now() - started > MAX_RUN_MS) break;
      totals.checked += 1;
      if (!pages.sameSite(row.PublishedUrl, ctx.siteHost)) {
        totals.off_site += 1;
        continue;
      }
      const [link] = await syncPublishedContent(pool, ctx, row.ContentID);
      const [page] = await pagesToCrawl(pool, link.pageId);
      const result = await pages.crawlPage(pool, page, { siteHost: ctx.siteHost, limits: ctx.limits, processLogId });
      const live = result.status === 200;
      totals[live ? 'live' : 'problems'] += 1;
      const titleNote = live && row.Title && result.title && !result.title.toLowerCase().includes(String(row.Title).toLowerCase().slice(0, 40))
        ? ` Page title "${result.title}" does not match the approved title.` : '';
      const note = live
        ? `Verified live (HTTP 200).${titleNote}${result.issues.length ? ` Page issues: ${result.issues.join(', ')}.` : ''}`
        : `Live URL check failed: ${result.status ? `HTTP ${result.status}` : result.error || 'unreachable'}. Check the published URL.`;
      // A failing URL is rechecked nightly; only record a review when the outcome changes.
      await pool.request()
        .input('cid', sql.Int, row.ContentID)
        .input('decision', sql.NVarChar(30), live ? 'verified' : 'verify_failed')
        .input('note', sql.NVarChar(2000), note.slice(0, 2000))
        .query(`
          DECLARE @last NVARCHAR(30) = (
            SELECT TOP 1 r.Decision FROM dbo.MktContentReview r
            INNER JOIN dbo.MktContent c ON c.ContentID = r.ContentID
            WHERE r.ContentID = @cid AND r.Gate = N'system' AND r.Decision IN (N'verified', N'verify_failed')
              AND r.CreatedAt >= c.PublishedAt
            ORDER BY r.ReviewID DESC);
          IF @last IS NULL OR @last <> @decision
            INSERT INTO dbo.MktContentReview (ContentID, Gate, Decision, Note) VALUES (@cid, N'system', @decision, @note);`);
    }
    return { ok: true, ...totals };
  } finally {
    await pool.close();
  }
}

module.exports = { crawl, verifyPublished, context, crawlAll, pagesToCrawl };
