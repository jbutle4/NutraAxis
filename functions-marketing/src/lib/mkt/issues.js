const { sql } = require('../db-config');
const { sha256 } = require('./dedup');
const { pageKey } = require('./pages');

/** code => [title, category, severity, owner]. Codes the crawler checks; "or_" codes come only from OpenRush. */
const CATALOG = {
  unreachable: ['Page unreachable', 'technical', 'high', 'developer'],
  noindex: ['Blocked from Google (noindex)', 'technical', 'high', 'developer'],
  missing_title: ['Missing title', 'meta', 'high', 'content'],
  long_title: ['Title too long', 'meta', 'medium', 'content'],
  short_title: ['Title too short', 'meta', 'low', 'content'],
  duplicate_title: ['Duplicate title', 'meta', 'medium', 'content'],
  missing_meta: ['Missing meta description', 'meta', 'medium', 'content'],
  short_meta: ['Meta description too short', 'meta', 'medium', 'content'],
  long_meta: ['Meta description too long', 'meta', 'low', 'content'],
  duplicate_meta: ['Duplicate meta description', 'meta', 'low', 'content'],
  missing_h1: ['No H1 heading', 'structure', 'medium', 'developer'],
  multiple_h1: ['More than one H1', 'structure', 'low', 'developer'],
  canonical_elsewhere: ['Canonical points elsewhere', 'technical', 'medium', 'developer'],
  thin: ['Thin content', 'content', 'medium', 'content'],
  redirects: ['Redirects', 'technical', 'low', 'developer'],
  missing_schema: ['No structured data (JSON-LD)', 'structure', 'low', 'developer'],
  missing_alt: ['Images without alt text', 'structure', 'low', 'content'],
  legacy_brand: ['Legacy brand name on page', 'brand', 'high', 'content'],
  or_low_content: ['Low content (OpenRush)', 'content', 'medium', 'content'],
};

/** OpenRush audit_site issue names → our codes (same rule); anything else becomes or_<name>. */
const OPENRUSH_CODES = {
  noindex: 'noindex',
  missing_title: 'missing_title',
  title_too_long: 'long_title',
  title_too_short: 'short_title',
  duplicate_title: 'duplicate_title',
  missing_meta_description: 'missing_meta',
  meta_description_too_short: 'short_meta',
  meta_description_too_long: 'long_meta',
  duplicate_meta_description: 'duplicate_meta',
  missing_h1: 'missing_h1',
  multiple_h1: 'multiple_h1',
  missing_structured_data: 'missing_schema',
  no_image_alt: 'missing_alt',
  low_content: 'or_low_content',
};

function describe(code) {
  if (CATALOG[code]) {
    const [title, category, severity, owner] = CATALOG[code];
    return { code, title, category, severity, owner };
  }
  const http = code.match(/^http_(\d{3})$/);
  if (http) return { code, title: `HTTP ${http[1]}`, category: 'technical', severity: 'high', owner: 'developer' };
  const name = code.replace(/^or_/, '').replace(/_/g, ' ');
  return { code, title: `OpenRush: ${name}`, category: 'technical', severity: 'low', owner: 'developer' };
}

function fingerprint(code) {
  return sha256(`issue:${code}`).toString('hex');
}

function finding(code, page, detail) {
  const key = page.PageKey || pageKey(page.Url);
  return {
    ...describe(code),
    fp: fingerprint(code),
    pageId: page.PageID ?? null,
    pageKey: key,
    keyHash: sha256(key).toString('hex'),
    url: String(page.Url).slice(0, 1000),
    detail: detail ? String(detail).slice(0, 500) : null,
  };
}

function crawlDetail(code, p) {
  const len = (s) => String(s || '').length;
  switch (code) {
    case 'long_title': case 'short_title': case 'duplicate_title':
      return `Title (${len(p.Title)} chars): ${p.Title}`;
    case 'short_meta': case 'long_meta': case 'duplicate_meta':
      return `Meta description (${len(p.MetaDescription)} chars): ${p.MetaDescription}`;
    case 'thin': return `${p.WordCount ?? 0} words`;
    case 'missing_h1': case 'multiple_h1': return `${p.H1Count ?? 0} H1 headings`;
    case 'noindex': return p.Robots ? `robots: ${p.Robots}` : null;
    case 'canonical_elsewhere': return p.Canonical ? `canonical: ${p.Canonical}` : null;
    case 'redirects': return p.FinalUrl ? `ends at ${p.FinalUrl}` : null;
    case 'missing_alt': return `${p.ImagesMissingAlt ?? 0} images without alt text`;
    case 'legacy_brand': return p.LegacyTerms ? `mentions ${p.LegacyTerms}` : null;
    default: return code.startsWith('http_') ? `last crawl returned ${code.slice(5)}` : null;
  }
}

/** Current crawler findings for active pages (all, or the given ids). */
async function crawlFindings(pool, pageIds = null) {
  const request = pool.request();
  let where = `Status = N'active' AND Issues IS NOT NULL AND Issues <> N''`;
  if (pageIds) {
    request.input('ids', sql.NVarChar(sql.MAX), JSON.stringify(pageIds));
    where += ' AND PageID IN (SELECT CAST(value AS INT) FROM OPENJSON(@ids))';
  }
  const rows = (await request.query(`
    SELECT PageID, Url, PageKey, Issues, Title, MetaDescription, H1Count, WordCount, Robots, Canonical, FinalUrl,
           ImagesMissingAlt, LegacyTerms
    FROM dbo.MktPage WHERE ${where}
  `)).recordset;
  const findings = [];
  for (const row of rows) {
    for (const code of String(row.Issues).split(',').map((c) => c.trim()).filter(Boolean)) {
      findings.push(finding(code, row, crawlDetail(code, row)));
    }
  }
  return findings;
}

/**
 * Record one audit: upsert issues (by code fingerprint) and issue URLs, resolve URLs this audit rechecked and no
 * longer sees, then move issue status — verified when no URLs remain, reopened when a verified issue recurs, and back
 * to open when a "fixed" issue is still present on recheck. Re-running the same audit changes nothing but LastSeen.
 *
 * Each source resolves only what it checks: the crawler owns every non-"or_" code; OpenRush imports own "or_" codes.
 */
async function recordAudit(pool, { source, scope, findings: raw, auditedPageIds, processLogId = null, userId = null, score = null, summary = null, skipped = 0, issueId = null }) {
  const findings = [...new Map(raw.map((f) => [`${f.fp}:${f.keyHash}`, f])).values()];
  const result = await pool.request()
    .input('source', sql.NVarChar(20), source)
    .input('scope', sql.NVarChar(20), scope)
    .input('findings', sql.NVarChar(sql.MAX), JSON.stringify(findings))
    .input('audited', sql.NVarChar(sql.MAX), JSON.stringify(auditedPageIds || []))
    .input('pages', sql.Int, (auditedPageIds || []).length)
    .input('score', sql.Decimal(5, 1), score)
    .input('summary', sql.NVarChar(1000), summary ? String(summary).slice(0, 1000) : null)
    .input('skipped', sql.Int, skipped)
    .input('issue', sql.Int, issueId)
    .input('log', sql.Int, processLogId)
    .input('user', sql.Int, userId)
    .query(`
      SET XACT_ABORT ON;
      BEGIN TRANSACTION;
      DECLARE @now DATETIME2(0) = SYSUTCDATETIME();
      INSERT INTO dbo.MktAudit (Source, Scope, StartedAt, PagesAudited, Findings, Skipped, Score, Summary, IssueID, ProcessLogID, CreatedBy)
      VALUES (@source, @scope, @now, @pages, (SELECT COUNT(*) FROM OPENJSON(@findings)), @skipped, @score, @summary, @issue, @log, @user);
      DECLARE @audit INT = SCOPE_IDENTITY();

      SELECT CONVERT(BINARY(32), fp, 2) AS Fingerprint, code, title, category, severity, owner, pageId,
             pageKey, CONVERT(BINARY(32), keyHash, 2) AS KeyHash, url, detail
      INTO #f
      FROM OPENJSON(@findings) WITH (
        fp CHAR(64) '$.fp', code NVARCHAR(60) '$.code', title NVARCHAR(200) '$.title', category NVARCHAR(20) '$.category',
        severity NVARCHAR(10) '$.severity', owner NVARCHAR(20) '$.owner', pageId INT '$.pageId', pageKey NVARCHAR(1000) '$.pageKey',
        keyHash CHAR(64) '$.keyHash', url NVARCHAR(1000) '$.url', detail NVARCHAR(500) '$.detail');

      DECLARE @issueActions TABLE (Act NVARCHAR(10));
      MERGE dbo.MktIssue AS t
      USING (SELECT DISTINCT Fingerprint, code, title, category, severity, owner FROM #f) AS s
      ON t.Fingerprint = s.Fingerprint
      WHEN MATCHED THEN UPDATE SET
        LastSeenAt = @now, LastAuditID = @audit, Title = s.title, Category = s.category, Severity = s.severity, Owner = s.owner,
        Sources = CASE WHEN CONCAT(N',', t.Sources, N',') LIKE N'%,' + @source + N',%' THEN t.Sources ELSE CONCAT_WS(N',', NULLIF(t.Sources, N''), @source) END
      WHEN NOT MATCHED THEN
        INSERT (Fingerprint, Code, Title, Category, Severity, Owner, Status, Sources, FirstSeenAt, LastSeenAt, LastAuditID)
        VALUES (s.Fingerprint, s.code, s.title, s.category, s.severity, s.owner, N'new', @source, @now, @now, @audit)
      OUTPUT $action INTO @issueActions;

      DECLARE @urlActions TABLE (Act NVARCHAR(10), WasStatus NVARCHAR(20));
      MERGE dbo.MktIssueUrl AS t
      USING (
        SELECT i.IssueID, f.pageId, f.pageKey, f.KeyHash, f.url, f.detail
        FROM #f f INNER JOIN dbo.MktIssue i ON i.Fingerprint = f.Fingerprint
      ) AS s
      ON t.IssueID = s.IssueID AND t.KeyHash = s.KeyHash
      WHEN MATCHED THEN UPDATE SET
        Status = N'open', ResolvedAt = NULL, LastSeenAt = @now, LastAuditID = @audit, Detail = s.detail, Url = s.url,
        PageID = COALESCE(s.pageId, t.PageID),
        Sources = CASE WHEN CONCAT(N',', t.Sources, N',') LIKE N'%,' + @source + N',%' THEN t.Sources ELSE CONCAT_WS(N',', NULLIF(t.Sources, N''), @source) END
      WHEN NOT MATCHED THEN
        INSERT (IssueID, PageID, PageKey, KeyHash, Url, Status, Detail, Sources, FirstSeenAt, LastSeenAt, LastAuditID)
        VALUES (s.IssueID, s.pageId, s.pageKey, s.KeyHash, s.url, N'open', s.detail, @source, @now, @now, @audit)
      OUTPUT $action, deleted.Status INTO @urlActions;

      UPDATE u SET Status = N'resolved', ResolvedAt = @now, LastAuditID = @audit
      FROM dbo.MktIssueUrl u INNER JOIN dbo.MktIssue i ON i.IssueID = u.IssueID
      WHERE u.Status = N'open' AND (u.LastAuditID IS NULL OR u.LastAuditID <> @audit)
        AND (
          (u.PageID IN (SELECT CAST(value AS INT) FROM OPENJSON(@audited))
            AND ((@source = N'crawl' AND i.Code NOT LIKE N'or[_]%') OR (@source = N'openrush' AND i.Code LIKE N'or[_]%')))
          OR (@source = N'crawl' AND @scope = N'full' AND u.PageID IN (SELECT PageID FROM dbo.MktPage WHERE Status <> N'active'))
        );
      DECLARE @resolved INT = @@ROWCOUNT;

      UPDATE i SET OpenUrlCount = (SELECT COUNT(*) FROM dbo.MktIssueUrl u WHERE u.IssueID = i.IssueID AND u.Status = N'open')
      FROM dbo.MktIssue i;

      UPDATE dbo.MktIssue SET Status = N'open', ReopenedAt = @now, ReopenCount = ReopenCount + 1, UpdatedAt = @now,
             VerifyNote = CONCAT(N'Reopened: found again on ', OpenUrlCount, N' URL(s) by the ', @source, N' audit.')
      WHERE Status = N'verified' AND OpenUrlCount > 0;
      DECLARE @reopened INT = @@ROWCOUNT;

      UPDATE i SET Status = N'open', UpdatedAt = @now,
             VerifyNote = CONCAT(N'Still present on ', i.OpenUrlCount, N' URL(s) when rechecked ', FORMAT(@now, 'yyyy-MM-dd HH:mm'), N' UTC.')
      FROM dbo.MktIssue i
      WHERE i.Status = N'fixed' AND i.FixedAt <= @now
        AND EXISTS (SELECT 1 FROM dbo.MktIssueUrl u WHERE u.IssueID = i.IssueID AND u.Status = N'open' AND u.LastAuditID = @audit);
      DECLARE @still INT = @@ROWCOUNT;

      UPDATE dbo.MktIssue SET VerifiedAt = @now, UpdatedAt = @now,
             VerifyNote = CASE WHEN Status = N'fixed' THEN N'Verified: the recheck no longer finds it.' ELSE N'Resolved: the audit no longer finds it.' END,
             Status = N'verified'
      WHERE Status IN (N'new', N'open', N'fixed') AND OpenUrlCount = 0;
      DECLARE @verified INT = @@ROWCOUNT;

      UPDATE dbo.MktAudit SET FinishedAt = SYSUTCDATETIME(),
        NewIssues = (SELECT COUNT(*) FROM @issueActions WHERE Act = N'INSERT'),
        NewUrls = (SELECT COUNT(*) FROM @urlActions WHERE Act = N'INSERT' OR WasStatus = N'resolved'),
        ReopenedIssues = @reopened, ResolvedUrls = @resolved, VerifiedIssues = @verified, StillOpenIssues = @still
      WHERE AuditID = @audit;
      COMMIT TRANSACTION;

      SELECT AuditID, Findings, NewIssues, NewUrls, ReopenedIssues, ResolvedUrls, VerifiedIssues, StillOpenIssues, Skipped
      FROM dbo.MktAudit WHERE AuditID = @audit;
    `);
  const row = result.recordset[0];
  return {
    audit_id: row.AuditID,
    findings: row.Findings,
    new_issues: row.NewIssues,
    new_urls: row.NewUrls,
    reopened: row.ReopenedIssues,
    resolved_urls: row.ResolvedUrls,
    verified: row.VerifiedIssues,
    still_open: row.StillOpenIssues,
    skipped: row.Skipped,
  };
}

/** Crawler audit over the pages just crawled (null = full crawl). */
async function recordCrawlAudit(pool, { scope, pageIds, processLogId, userId, issueId = null }) {
  const findings = await crawlFindings(pool, scope === 'full' ? null : pageIds);
  return recordAudit(pool, { source: 'crawl', scope, findings, auditedPageIds: pageIds, processLogId, userId, issueId });
}

module.exports = { CATALOG, OPENRUSH_CODES, describe, finding, crawlFindings, recordAudit, recordCrawlAudit };
