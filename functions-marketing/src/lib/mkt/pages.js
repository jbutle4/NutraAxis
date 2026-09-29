const zlib = require('zlib');
const { sql } = require('../db-config');
const { decodeEntities, extractArticle, extractLinks } = require('./html');
const { sha256 } = require('./dedup');

const USER_AGENT = 'NutraAxisSiteAudit/1.0 (+https://www.nutraaxislabs.com)';
const TIMEOUT_MS = 20000;
const MAX_BYTES = 3 * 1024 * 1024;
const COMPARED_FIELDS = ['StatusCode', 'Title', 'MetaDescription', 'H1', 'Canonical', 'Robots'];

/** Stable identity for a page: host without www + path without trailing slash, lower case, no query. */
function pageKey(raw) {
  try {
    const url = new URL(String(raw).trim());
    const host = url.hostname.toLowerCase().replace(/^www\./, '');
    let path = url.pathname.replace(/\/{2,}/g, '/');
    if (path.length > 1) path = path.replace(/\/+$/, '');
    return `${host}${path}`.toLowerCase();
  } catch {
    return null;
  }
}

function pathOf(raw) {
  try {
    const path = new URL(String(raw).trim()).pathname;
    return path.length > 1 ? path.replace(/\/+$/, '') : '/';
  } catch {
    return '/';
  }
}

/** GA4 landingPage values are paths; match them to the same key as a full URL on the site host. */
function pathKey(host, path) {
  return pageKey(`https://${host}${String(path || '/').split('?')[0]}`);
}

function sameSite(raw, siteHost) {
  try {
    return new URL(raw).hostname.toLowerCase().replace(/^www\./, '') === siteHost;
  } catch {
    return false;
  }
}

function compilePatterns(lines) {
  return lines.map((line) => (line.startsWith('^') ? { regex: new RegExp(line, 'i') } : { prefix: line.toLowerCase() }));
}

/** A prefix matches the path itself or anything below it ("/customer" matches "/customer/login", not "/customers"). */
function isExcluded(path, patterns) {
  const lower = path.toLowerCase();
  return patterns.some((p) => {
    if (p.regex) return p.regex.test(path);
    const base = p.prefix.replace(/\/+$/, '');
    return lower === base || lower.startsWith(`${base}/`);
  });
}

function compileTypeRules(lines) {
  return lines.map((line) => {
    const cut = line.indexOf('|');
    const type = cut > 0 ? line.slice(0, cut).trim() : '';
    const pattern = cut > 0 ? line.slice(cut + 1).trim() : '';
    try {
      return type && pattern ? { type, regex: new RegExp(pattern, 'i') } : null;
    } catch {
      return null;
    }
  }).filter(Boolean);
}

function typeFor(path, rules) {
  return rules.find((rule) => rule.regex.test(path))?.type || 'page';
}

async function fetchRaw(url, { method = 'GET' } = {}) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
  const started = Date.now();
  try {
    const response = await fetch(url, {
      method,
      redirect: 'follow',
      signal: controller.signal,
      headers: { 'User-Agent': USER_AGENT, Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' },
    });
    let buffer = Buffer.from(await response.arrayBuffer()).subarray(0, MAX_BYTES);
    if (buffer[0] === 0x1f && buffer[1] === 0x8b) {
      buffer = zlib.gunzipSync(buffer);
    }
    return {
      status: response.status,
      url: response.url || url,
      ms: Date.now() - started,
      robotsHeader: response.headers.get('x-robots-tag') || '',
      contentType: response.headers.get('content-type') || '',
      text: buffer.toString('utf8'),
    };
  } catch (error) {
    return { status: 0, url, ms: Date.now() - started, text: '', error: error.name === 'AbortError' ? `Timed out after ${TIMEOUT_MS} ms` : error.message };
  } finally {
    clearTimeout(timer);
  }
}

/** Every <loc> in the site's sitemaps (robots.txt Sitemap lines, else /sitemap.xml), following sitemap indexes. */
async function sitemapUrls(siteUrl) {
  const robots = await fetchRaw(new URL('/robots.txt', siteUrl).toString());
  const queue = (robots.status === 200 ? robots.text.match(/^\s*sitemap:\s*(\S+)/gim) || [] : [])
    .map((line) => line.replace(/^\s*sitemap:\s*/i, '').trim());
  if (queue.length === 0) queue.push(new URL('/sitemap.xml', siteUrl).toString());

  const seen = new Set();
  const urls = new Map();
  const errors = [];
  while (queue.length > 0 && seen.size < 50) {
    const sitemap = queue.shift();
    if (seen.has(sitemap)) continue;
    seen.add(sitemap);
    const response = await fetchRaw(sitemap);
    if (response.status !== 200) {
      errors.push(`${sitemap}: ${response.status || response.error}`);
      continue;
    }
    const locs = [...response.text.matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/gi)].map((m) => decodeEntities(m[1]));
    if (/<sitemapindex/i.test(response.text)) {
      queue.push(...locs);
    } else {
      for (const loc of locs) urls.set(loc, sitemap);
    }
  }
  return { urls, sitemaps: [...seen], errors };
}

function tagText(html) {
  return decodeEntities(String(html || '').replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').trim();
}

function attr(tag, name) {
  const match = tag.match(new RegExp(`${name}\\s*=\\s*["']([^"']*)["']`, 'i'));
  return match ? decodeEntities(match[1].trim()) : null;
}

function metaByName(html, name) {
  for (const tag of html.match(/<meta\b[^>]*>/gi) || []) {
    if ((attr(tag, 'name') || attr(tag, 'property') || '').toLowerCase() === name) {
      return attr(tag, 'content');
    }
  }
  return null;
}

/** Title, meta, headings, canonical, robots, word and link counts from one HTML page. */
function parsePage(html, finalUrl, siteHost) {
  const titleMatch = html.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
  const h1s = [...html.matchAll(/<h1\b[^>]*>([\s\S]*?)<\/h1>/gi)].map((m) => tagText(m[1])).filter(Boolean);
  const canonicalTag = (html.match(/<link\b[^>]*rel=["']canonical["'][^>]*>/i) || [])[0];
  const links = extractLinks(html, finalUrl);
  const internal = links.filter((link) => sameSite(link, siteHost)).length;
  const text = extractArticle(html, finalUrl).text || '';
  const words = text.split(/\s+/).filter((word) => /[a-z0-9]/i.test(word)).length;

  return {
    Title: titleMatch ? tagText(titleMatch[1]).slice(0, 500) : null,
    MetaDescription: (metaByName(html, 'description') || null)?.slice(0, 1000) ?? null,
    H1: h1s[0] ? h1s[0].slice(0, 500) : null,
    H1Count: h1s.length,
    Canonical: canonicalTag ? (attr(canonicalTag, 'href') || '').slice(0, 1000) || null : null,
    Robots: (metaByName(html, 'robots') || '').slice(0, 200) || null,
    WordCount: words,
    InternalLinks: internal,
    ExternalLinks: links.length - internal,
    HasStructuredData: /<script[^>]+application\/ld\+json/i.test(html),
    ContentHash: sha256(text.replace(/\s+/g, ' ').trim().toLowerCase()),
  };
}

function issuesFor(page, snapshot, limits) {
  const issues = [];
  const status = snapshot.StatusCode;
  if (status !== 200) {
    issues.push(status ? `http_${status}` : 'unreachable');
    return issues;
  }
  const robots = `${snapshot.Robots || ''} ${snapshot.RobotsHeader || ''}`.toLowerCase();
  if (robots.includes('noindex')) issues.push('noindex');
  if (!snapshot.Title) issues.push('missing_title');
  else if (snapshot.Title.length > limits.titleMax) issues.push('long_title');
  if (!snapshot.MetaDescription) issues.push('missing_meta');
  else if (snapshot.MetaDescription.length < limits.metaMin) issues.push('short_meta');
  else if (snapshot.MetaDescription.length > limits.metaMax) issues.push('long_meta');
  if (snapshot.H1Count === 0) issues.push('missing_h1');
  if (snapshot.H1Count > 1) issues.push('multiple_h1');
  if (snapshot.Canonical && pageKey(new URL(snapshot.Canonical, page.Url).toString()) !== page.PageKey) issues.push('canonical_elsewhere');
  if (['content', 'category', 'page'].includes(page.PageType) && snapshot.WordCount < limits.thinWords) issues.push('thin');
  if (snapshot.FinalKey && snapshot.FinalKey !== page.PageKey) issues.push('redirects');
  return issues;
}

function changesFrom(previous, snapshot) {
  if (!previous) return [];
  const changes = COMPARED_FIELDS.filter((field) => String(previous[field] ?? '') !== String(snapshot[field] ?? ''));
  if (previous.ContentHash && snapshot.ContentHash && !previous.ContentHash.equals(snapshot.ContentHash)) changes.push('content');
  return changes;
}

async function crawlPage(pool, page, { siteHost, limits, processLogId }) {
  const response = await fetchRaw(page.Url);
  const isHtml = /html/i.test(response.contentType || '') || /<html/i.test(response.text.slice(0, 2000));
  const parsed = response.status === 200 && isHtml ? parsePage(response.text, response.url, siteHost) : {};
  const snapshot = {
    StatusCode: response.status || null,
    FinalUrl: response.url ? String(response.url).slice(0, 1000) : null,
    FinalKey: response.url ? pageKey(response.url) : null,
    RobotsHeader: response.robotsHeader || '',
    ...parsed,
  };
  const issues = issuesFor(page, snapshot, limits);
  const previous = (await pool.request().input('id', sql.Int, page.PageID).query(`
    SELECT TOP 1 StatusCode, Title, MetaDescription, H1, Canonical, Robots, ContentHash
    FROM dbo.MktPageCrawl WHERE PageID = @id ORDER BY CrawlID DESC
  `)).recordset[0] || null;
  const changes = changesFrom(previous, snapshot);
  const failed = response.status !== 200;
  const errors = failed ? Number(page.ConsecutiveErrors || 0) + 1 : 0;
  const status = [404, 410].includes(response.status) && errors >= 2 ? 'gone' : (page.Status === 'gone' && !failed ? 'active' : page.Status);

  const bind = (request) => request
    .input('id', sql.Int, page.PageID)
    .input('code', sql.Int, snapshot.StatusCode)
    .input('final', sql.NVarChar(1000), snapshot.FinalUrl)
    .input('title', sql.NVarChar(500), snapshot.Title ?? null)
    .input('meta', sql.NVarChar(1000), snapshot.MetaDescription ?? null)
    .input('h1', sql.NVarChar(500), snapshot.H1 ?? null)
    .input('h1count', sql.Int, snapshot.H1Count ?? null)
    .input('canonical', sql.NVarChar(1000), snapshot.Canonical ?? null)
    .input('robots', sql.NVarChar(200), [snapshot.Robots, snapshot.RobotsHeader].filter(Boolean).join('; ').slice(0, 200) || null)
    .input('words', sql.Int, snapshot.WordCount ?? null)
    .input('internal', sql.Int, snapshot.InternalLinks ?? null)
    .input('external', sql.Int, snapshot.ExternalLinks ?? null)
    .input('sd', sql.Bit, snapshot.HasStructuredData === undefined ? null : (snapshot.HasStructuredData ? 1 : 0))
    .input('hash', sql.Binary(32), snapshot.ContentHash ?? null)
    .input('issues', sql.NVarChar(1000), issues.join(',') || null)
    .input('changes', sql.NVarChar(500), changes.join(',') || null);

  await bind(pool.request())
    .input('ms', sql.Int, response.ms)
    .input('error', sql.NVarChar(1000), response.error ? String(response.error).slice(0, 1000) : null)
    .input('log_id', sql.Int, processLogId)
    .query(`
      INSERT INTO dbo.MktPageCrawl (PageID, StatusCode, FinalUrl, ResponseMs, Title, MetaDescription, H1, H1Count, Canonical, Robots,
        WordCount, InternalLinks, ExternalLinks, HasStructuredData, ContentHash, Issues, Changes, ErrorMessage, ProcessLogID)
      VALUES (@id, @code, @final, @ms, @title, @meta, @h1, @h1count, @canonical, @robots,
        @words, @internal, @external, @sd, @hash, @issues, @changes, @error, @log_id)
    `);

  await bind(pool.request())
    .input('status', sql.NVarChar(20), status)
    .input('errors', sql.Int, errors)
    .input('changed', sql.Bit, changes.length > 0 ? 1 : 0)
    .query(`
      UPDATE dbo.MktPage
      SET LastCrawledAt = SYSUTCDATETIME(), LastStatusCode = @code, FinalUrl = @final,
          Title = CASE WHEN @code = 200 THEN @title ELSE Title END,
          MetaDescription = CASE WHEN @code = 200 THEN @meta ELSE MetaDescription END,
          H1 = CASE WHEN @code = 200 THEN @h1 ELSE H1 END,
          H1Count = CASE WHEN @code = 200 THEN @h1count ELSE H1Count END,
          Canonical = CASE WHEN @code = 200 THEN @canonical ELSE Canonical END,
          Robots = CASE WHEN @code = 200 THEN @robots ELSE Robots END,
          WordCount = CASE WHEN @code = 200 THEN @words ELSE WordCount END,
          InternalLinks = CASE WHEN @code = 200 THEN @internal ELSE InternalLinks END,
          ExternalLinks = CASE WHEN @code = 200 THEN @external ELSE ExternalLinks END,
          HasStructuredData = CASE WHEN @code = 200 THEN @sd ELSE HasStructuredData END,
          ContentHash = CASE WHEN @code = 200 THEN @hash ELSE ContentHash END,
          Issues = @issues, Status = @status, ConsecutiveErrors = @errors,
          LastChangedAt = CASE WHEN @changed = 1 THEN SYSUTCDATETIME() ELSE LastChangedAt END,
          UpdatedAt = SYSUTCDATETIME()
      WHERE PageID = @id
    `);

  return { status: snapshot.StatusCode, title: snapshot.Title, issues, changes, error: response.error || null };
}

/** Insert a page if its key is new; returns { pageId, created }. */
async function upsertPage(pool, url, { source, pageType, status = 'active', contentId = null, inSitemap = false, userId = null }) {
  const key = pageKey(url);
  if (!key) return { pageId: null, created: false };
  const result = await pool.request()
    .input('url', sql.NVarChar(1000), String(url).slice(0, 1000))
    .input('key', sql.NVarChar(1000), key.slice(0, 1000))
    .input('hash', sql.Binary(32), sha256(key))
    .input('path', sql.NVarChar(1000), pathOf(url).slice(0, 1000))
    .input('type', sql.NVarChar(30), pageType)
    .input('source', sql.NVarChar(30), source)
    .input('status', sql.NVarChar(20), status)
    .input('content', sql.Int, contentId)
    .input('sitemap', sql.Bit, inSitemap ? 1 : 0)
    .input('user', sql.Int, userId)
    .query(`
      DECLARE @id INT = (SELECT PageID FROM dbo.MktPage WHERE PageKeyHash = @hash);
      DECLARE @created BIT = 0;
      IF @id IS NULL
      BEGIN
        INSERT INTO dbo.MktPage (Url, PageKey, PageKeyHash, Path, PageType, Source, Status, ContentID, InSitemap, LastSeenInSitemapAt, CreatedBy)
        VALUES (@url, @key, @hash, @path, @type, @source, @status, @content, @sitemap, CASE WHEN @sitemap = 1 THEN SYSUTCDATETIME() END, @user);
        SET @id = SCOPE_IDENTITY();
        SET @created = 1;
      END
      ELSE
      BEGIN
        UPDATE dbo.MktPage
        SET InSitemap = CASE WHEN @sitemap = 1 THEN 1 ELSE InSitemap END,
            LastSeenInSitemapAt = CASE WHEN @sitemap = 1 THEN SYSUTCDATETIME() ELSE LastSeenInSitemapAt END,
            ContentID = COALESCE(@content, ContentID),
            PageType = CASE WHEN @content IS NOT NULL THEN N'content' ELSE PageType END,
            UpdatedAt = SYSUTCDATETIME()
        WHERE PageID = @id;
      END
      SELECT @id AS PageID, @created AS Created;
    `);
  const row = result.recordset[0];
  return { pageId: Number(row.PageID), created: Boolean(row.Created) };
}

module.exports = {
  pageKey,
  pathOf,
  pathKey,
  sameSite,
  compilePatterns,
  isExcluded,
  compileTypeRules,
  typeFor,
  sitemapUrls,
  crawlPage,
  upsertPage,
};
