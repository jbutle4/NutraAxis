const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingNumber, settingLines, recordUsage } = require('../mkt/settings');
const { sha256 } = require('../mkt/dedup');
const google = require('../mkt/google');
const pages = require('../mkt/pages');

const DAY_MS = 86400000;
const GA4_METRICS = ['sessions', 'totalUsers', 'engagedSessions', 'userEngagementDuration', 'keyEvents', 'transactions', 'purchaseRevenue'];

function isoDate(date) {
  return date.toISOString().slice(0, 10);
}

function addDays(iso, days) {
  return isoDate(new Date(Date.parse(`${iso}T00:00:00Z`) + days * DAY_MS));
}

/**
 * Date window to (re)load: explicit start/end, else backfill on an empty table, else the last refresh_days
 * (or from the day after the latest stored day, whichever is earlier). Ends yesterday (UTC).
 */
async function loadWindow(pool, table, params, backfillDays, refreshDays) {
  const end = params.end_date || addDays(isoDate(new Date()), -1);
  if (params.start_date) {
    return { start: params.start_date, end, backfill: false };
  }
  const latest = (await pool.request().query(`SELECT CONVERT(varchar(10), MAX(MetricDate), 23) AS Latest FROM dbo.${table}`)).recordset[0]?.Latest;
  if (!latest) {
    return { start: addDays(end, -(backfillDays - 1)), end, backfill: true };
  }
  const refreshStart = addDays(end, -(refreshDays - 1));
  const afterLatest = addDays(latest, 1);
  return { start: afterLatest < refreshStart ? afterLatest : refreshStart, end, backfill: false };
}

async function pageIndex(pool) {
  const rows = (await pool.request().query('SELECT PageID, PageKey FROM dbo.MktPage')).recordset;
  return new Map(rows.map((row) => [row.PageKey, Number(row.PageID)]));
}

const JSON_CHUNK = 4000;

/**
 * Replace every row of the table in [start, end] with the given rows inside one transaction.
 * Rows go in as JSON chunks through OPENJSON; Binary columns travel as hex.
 */
async function replaceRange(pool, table, columns, rows, start, end, afterInsertSql = null) {
  const names = columns.map(([name]) => name);
  const withClause = columns.map(([name, type]) => `${name} ${type.startsWith('binary') ? 'varchar(64)' : type} '$.${name}'`).join(', ');
  const selectList = columns.map(([name, type]) => (type.startsWith('binary') ? `CONVERT(${type}, ${name}, 2)` : name)).join(', ');

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    await new sql.Request(transaction)
      .input('start', sql.Date, start)
      .input('end', sql.Date, end)
      .query(`DELETE FROM dbo.${table} WHERE MetricDate BETWEEN @start AND @end`);
    for (let i = 0; i < rows.length; i += JSON_CHUNK) {
      const chunk = rows.slice(i, i + JSON_CHUNK).map((row) => Object.fromEntries(names.map((name) => {
        const value = row[name];
        return [name, Buffer.isBuffer(value) ? value.toString('hex') : value ?? null];
      })));
      await new sql.Request(transaction)
        .input('rows', sql.NVarChar(sql.MAX), JSON.stringify(chunk))
        .query(`INSERT INTO dbo.${table} (${names.join(', ')}) SELECT ${selectList} FROM OPENJSON(@rows) WITH (${withClause})`);
    }
    if (afterInsertSql) {
      await new sql.Request(transaction).input('start', sql.Date, start).input('end', sql.Date, end).query(afterInsertSql);
    }
    await transaction.commit();
  } catch (error) {
    await transaction.rollback().catch(() => {});
    throw error;
  }
}

const GSC_COLUMNS = [
  ['MetricDate', 'date'], ['Grain', 'nvarchar(10)'], ['Page', 'nvarchar(1000)'], ['Query', 'nvarchar(400)'],
  ['DimHash', 'binary(32)'], ['PageID', 'int'], ['Clicks', 'int'], ['Impressions', 'int'], ['Position', 'decimal(9, 2)'],
];

async function gscIngest(params = {}) {
  const conn = google.connection();
  if (!conn.key || !conn.gscSite) {
    return { ok: true, skipped: true, message: 'Search Console is not connected (GOOGLE_SA_JSON_B64 / GSC_SITE_URL not set on the Function App).' };
  }
  const processLogId = Number(params.log_id || 0) || null;
  const pool = await connectPool(getProductionDatabase());
  try {
    const settings = await loadSettings(pool);
    const allowedHost = settings['analytics.allowed_host'] || 'nutraaxislabs.com';
    google.assertGscSiteAllowed(conn.gscSite, allowedHost);
    const window = await loadWindow(pool, 'MktGscDaily', params,
      settingNumber(settings, 'gsc.backfill_days', 90), settingNumber(settings, 'gsc.refresh_days', 5));
    const range = { startDate: window.start, endDate: window.end };

    const [siteRows, pageRows, queryRows] = await Promise.all([
      google.gscQuery(conn.gscSite, { ...range, dimensions: ['date'] }),
      google.gscQuery(conn.gscSite, { ...range, dimensions: ['date', 'page'] }),
      google.gscQuery(conn.gscSite, { ...range, dimensions: ['date', 'page', 'query'] }),
    ]);

    const siteUrl = settings['pages.site_url'] || `https://www.${allowedHost}`;
    const siteHost = new URL(siteUrl).hostname.toLowerCase().replace(/^www\./, '');
    const excludes = pages.compilePatterns(settingLines(settings, 'pages.exclude_patterns'));
    const typeRules = pages.compileTypeRules(settingLines(settings, 'pages.type_rules'));
    let index = await pageIndex(pool);
    let discovered = 0;
    for (const url of new Set(pageRows.map((row) => row.keys[1]))) {
      const key = pages.pageKey(url);
      if (!key || index.has(key) || !pages.sameSite(url, siteHost)) continue;
      const path = pages.pathOf(url);
      await pages.upsertPage(pool, url, { source: 'search', pageType: pages.typeFor(path, typeRules), status: pages.isExcluded(path, excludes) ? 'excluded' : 'active' });
      discovered += 1;
    }
    if (discovered > 0) index = await pageIndex(pool);

    const row = (grain, date, page, query, metrics) => ({
      MetricDate: date,
      Grain: grain,
      Page: String(page).slice(0, 1000),
      Query: String(query).slice(0, 400),
      DimHash: sha256(`${page}\u0000${query}`),
      PageID: page ? index.get(pages.pageKey(page)) ?? null : null,
      Clicks: Math.round(metrics.clicks || 0),
      Impressions: Math.round(metrics.impressions || 0),
      Position: metrics.position ? Math.round(metrics.position * 100) / 100 : null,
    });
    const rows = [
      ...siteRows.map((r) => row('site', r.keys[0], '', '', r)),
      ...pageRows.map((r) => row('page', r.keys[0], r.keys[1], '', r)),
      ...queryRows.map((r) => row('query', r.keys[0], r.keys[1], r.keys[2], r)),
    ];
    await replaceRange(pool, 'MktGscDaily', GSC_COLUMNS, rows, window.start, window.end);
    await recordUsage(pool, { provider: 'google', operation: 'gsc.search_analytics', mode: 'api', units: rows.length, processLogId });

    const days = new Set(siteRows.map((r) => r.keys[0]));
    return {
      ok: true,
      start: window.start,
      end: window.end,
      backfill: window.backfill,
      days: days.size,
      clicks: siteRows.reduce((sum, r) => sum + (r.clicks || 0), 0),
      impressions: siteRows.reduce((sum, r) => sum + (r.impressions || 0), 0),
      page_rows: pageRows.length,
      query_rows: queryRows.length,
      new_pages: discovered,
    };
  } finally {
    await pool.close();
  }
}

const GA4_COLUMNS = [
  ['MetricDate', 'date'], ['Grain', 'nvarchar(10)'], ['Dim1', 'nvarchar(500)'], ['Dim2', 'nvarchar(300)'],
  ['Dim3', 'nvarchar(300)'], ['Dim4', 'nvarchar(300)'], ['DimHash', 'binary(32)'], ['PageID', 'int'],
  ['Sessions', 'int'], ['Users', 'int'], ['EngagedSessions', 'int'], ['EngagementSeconds', 'bigint'],
  ['KeyEvents', 'decimal(12, 2)'], ['Transactions', 'int'], ['Revenue', 'decimal(14, 2)'],
];

/** UTM rows resolve to a campaign by utm_campaign = campaign slug, then to an asset by utm_content = asset id. */
const GA4_ATTRIBUTION_SQL = `
  UPDATE g SET CampaignID = c.CampaignID
  FROM dbo.MktGa4Daily g
  INNER JOIN dbo.MktCampaign c ON c.Slug = g.Dim3
  WHERE g.Grain = N'utm' AND g.MetricDate BETWEEN @start AND @end;

  UPDATE g SET AssetID = a.AssetID
  FROM dbo.MktGa4Daily g
  INNER JOIN dbo.MktAsset a ON a.AssetID = TRY_CAST(g.Dim4 AS INT) AND a.CampaignID = g.CampaignID
  WHERE g.Grain = N'utm' AND g.MetricDate BETWEEN @start AND @end;
`;

function gaDate(value) {
  return `${value.slice(0, 4)}-${value.slice(4, 6)}-${value.slice(6, 8)}`;
}

async function ga4Ingest(params = {}) {
  const conn = google.connection();
  if (!conn.key || !conn.ga4Property) {
    return { ok: true, skipped: true, message: 'GA4 is not connected (GOOGLE_SA_JSON_B64 / GA4_PROPERTY_ID not set on the Function App).' };
  }
  const processLogId = Number(params.log_id || 0) || null;
  const pool = await connectPool(getProductionDatabase());
  try {
    const settings = await loadSettings(pool);
    const allowedHost = settings['analytics.allowed_host'] || 'nutraaxislabs.com';
    await google.assertGa4PropertyAllowed(conn.ga4Property, allowedHost);
    const window = await loadWindow(pool, 'MktGa4Daily', params,
      settingNumber(settings, 'ga4.backfill_days', 90), settingNumber(settings, 'ga4.refresh_days', 4));
    const range = { startDate: window.start, endDate: window.end, metrics: GA4_METRICS };

    const [channelRows, landingRows, utmRows] = await Promise.all([
      google.ga4Report(conn.ga4Property, { ...range, dimensions: ['date', 'sessionDefaultChannelGroup'] }),
      google.ga4Report(conn.ga4Property, { ...range, dimensions: ['date', 'landingPage'] }),
      google.ga4Report(conn.ga4Property, { ...range, dimensions: ['date', 'sessionSource', 'sessionMedium', 'sessionCampaignName', 'sessionManualAdContent'] }),
    ]);

    const siteUrl = settings['pages.site_url'] || `https://www.${allowedHost}`;
    const siteHost = new URL(siteUrl).hostname.toLowerCase().replace(/^www\./, '');
    const index = await pageIndex(pool);
    const row = (grain, r, dims, pageId = null) => ({
      MetricDate: gaDate(r.date),
      Grain: grain,
      Dim1: String(dims[0] ?? '').slice(0, 500),
      Dim2: String(dims[1] ?? '').slice(0, 300),
      Dim3: String(dims[2] ?? '').slice(0, 300),
      Dim4: String(dims[3] ?? '').slice(0, 300),
      DimHash: sha256(dims.map((d) => String(d ?? '')).join('\u0000')),
      PageID: pageId,
      Sessions: Math.round(r.sessions),
      Users: Math.round(r.totalUsers),
      EngagedSessions: Math.round(r.engagedSessions),
      EngagementSeconds: Math.round(r.userEngagementDuration),
      KeyEvents: Math.round(r.keyEvents * 100) / 100,
      Transactions: Math.round(r.transactions),
      Revenue: Math.round(r.purchaseRevenue * 100) / 100,
    });
    const hasValue = (value) => value !== '' && !String(value).startsWith('(');
    const tagged = utmRows.filter((r) => hasValue(r.sessionCampaignName) || hasValue(r.sessionManualAdContent));
    const rows = [
      ...channelRows.map((r) => row('channel', r, [r.sessionDefaultChannelGroup])),
      ...landingRows.map((r) => row('landing', r, [r.landingPage], index.get(pages.pathKey(siteHost, r.landingPage)) ?? null)),
      ...tagged.map((r) => row('utm', r, [r.sessionSource, r.sessionMedium, r.sessionCampaignName, r.sessionManualAdContent])),
    ];
    await replaceRange(pool, 'MktGa4Daily', GA4_COLUMNS, rows, window.start, window.end, GA4_ATTRIBUTION_SQL);
    await recordUsage(pool, { provider: 'google', operation: 'ga4.run_report', mode: 'api', units: rows.length, processLogId });

    const attributed = (await pool.request()
      .input('start', sql.Date, window.start)
      .input('end', sql.Date, window.end)
      .query(`SELECT COUNT(DISTINCT AssetID) AS Assets, COALESCE(SUM(Sessions), 0) AS Sessions FROM dbo.MktGa4Daily WHERE Grain = N'utm' AND AssetID IS NOT NULL AND MetricDate BETWEEN @start AND @end`)).recordset[0];

    return {
      ok: true,
      start: window.start,
      end: window.end,
      backfill: window.backfill,
      days: new Set(channelRows.map((r) => r.date)).size,
      sessions: channelRows.reduce((sum, r) => sum + r.sessions, 0),
      landing_rows: landingRows.length,
      utm_rows: tagged.length,
      assets_with_sessions: Number(attributed.Assets || 0),
      asset_sessions: Number(attributed.Sessions || 0),
    };
  } finally {
    await pool.close();
  }
}

module.exports = { gscIngest, ga4Ingest, loadWindow, addDays };
