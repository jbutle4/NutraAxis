const crypto = require('crypto');

const TOKEN_URL = 'https://oauth2.googleapis.com/token';
const SCOPES = 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly';
const TIMEOUT_MS = 60000;

let cachedToken = null;

function serviceAccount() {
  const encoded = process.env.GOOGLE_SA_JSON_B64;
  if (!encoded) {
    return null;
  }
  const key = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
  if (!key.client_email || !key.private_key) {
    throw new Error('GOOGLE_SA_JSON_B64 is not a service account key.');
  }
  return key;
}

/** Which Google settings are present; never returns secret values. */
function connection() {
  return {
    key: Boolean(process.env.GOOGLE_SA_JSON_B64),
    gscSite: process.env.GSC_SITE_URL || null,
    ga4Property: process.env.GA4_PROPERTY_ID || null,
  };
}

async function accessToken() {
  if (cachedToken && cachedToken.expiresAt > Date.now() + 60000) {
    return cachedToken.token;
  }
  const key = serviceAccount();
  if (!key) {
    throw new Error('Google is not connected: GOOGLE_SA_JSON_B64 is not set on the Function App.');
  }
  const now = Math.floor(Date.now() / 1000);
  const b64u = (value) => Buffer.from(JSON.stringify(value)).toString('base64url');
  const unsigned = `${b64u({ alg: 'RS256', typ: 'JWT' })}.${b64u({ iss: key.client_email, scope: SCOPES, aud: TOKEN_URL, iat: now, exp: now + 3600 })}`;
  const signature = crypto.sign('RSA-SHA256', Buffer.from(unsigned), key.private_key).toString('base64url');
  const response = await fetch(TOKEN_URL, {
    method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ grant_type: 'urn:ietf:params:oauth:grant-type:jwt-bearer', assertion: `${unsigned}.${signature}` }),
  });
  const body = await response.json().catch(() => ({}));
  if (!body.access_token) {
    throw new Error(`Google sign-in failed: ${body.error_description || body.error || response.status}`);
  }
  cachedToken = { token: body.access_token, expiresAt: Date.now() + Number(body.expires_in || 3600) * 1000 };
  return cachedToken.token;
}

async function call(url, { method = 'GET', body = null } = {}) {
  const token = await accessToken();
  for (let attempt = 1; ; attempt += 1) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
    try {
      const response = await fetch(url, {
        method,
        signal: controller.signal,
        headers: { authorization: `Bearer ${token}`, 'content-type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
      });
      const json = await response.json().catch(() => ({}));
      if (response.ok) {
        return json;
      }
      if ((response.status === 429 || response.status >= 500) && attempt < 3) {
        await new Promise((resolve) => setTimeout(resolve, attempt * 5000));
        continue;
      }
      throw new Error(`Google API ${response.status}: ${json.error?.message || response.statusText}`.slice(0, 500));
    } finally {
      clearTimeout(timer);
    }
  }
}

function hostAllowed(host, allowedHost) {
  const h = String(host || '').toLowerCase().replace(/^www\./, '');
  const allowed = String(allowedHost || '').toLowerCase().replace(/^www\./, '');
  return allowed !== '' && (h === allowed || h.endsWith(`.${allowed}`));
}

/** GSC property must be sc-domain:<allowed> or a URL-prefix property on the allowed host. */
function assertGscSiteAllowed(siteUrl, allowedHost) {
  const host = siteUrl.startsWith('sc-domain:') ? siteUrl.slice('sc-domain:'.length) : (() => {
    try { return new URL(siteUrl).hostname; } catch { return ''; }
  })();
  if (!hostAllowed(host, allowedHost)) {
    throw new Error(`Search Console property ${siteUrl} is not on ${allowedHost}; refusing to ingest.`);
  }
}

/** GA4 property must have a web data stream on the allowed host. */
async function assertGa4PropertyAllowed(propertyId, allowedHost) {
  const json = await call(`https://analyticsadmin.googleapis.com/v1beta/properties/${encodeURIComponent(propertyId)}/dataStreams`);
  const hosts = (json.dataStreams || [])
    .map((stream) => stream.webStreamData?.defaultUri)
    .filter(Boolean)
    .map((uri) => { try { return new URL(uri).hostname; } catch { return ''; } });
  if (!hosts.some((host) => hostAllowed(host, allowedHost))) {
    throw new Error(`GA4 property ${propertyId} has no web stream on ${allowedHost} (streams: ${hosts.join(', ') || 'none'}); refusing to ingest.`);
  }
}

/** All Search Analytics rows for a date range and dimensions, paging past the 25k row cap. */
async function gscQuery(siteUrl, { startDate, endDate, dimensions }) {
  const rows = [];
  const pageSize = 25000;
  for (let startRow = 0; ; startRow += pageSize) {
    const json = await call(`https://www.googleapis.com/webmasters/v3/sites/${encodeURIComponent(siteUrl)}/searchAnalytics/query`, {
      method: 'POST',
      body: { startDate, endDate, dimensions, rowLimit: pageSize, startRow, dataState: 'final' },
    });
    const batch = json.rows || [];
    rows.push(...batch);
    if (batch.length < pageSize) {
      return rows;
    }
  }
}

/** All GA4 report rows as objects keyed by dimension / metric name. */
async function ga4Report(propertyId, { startDate, endDate, dimensions, metrics }) {
  const rows = [];
  const limit = 100000;
  for (let offset = 0; ; offset += limit) {
    const json = await call(`https://analyticsdata.googleapis.com/v1beta/properties/${encodeURIComponent(propertyId)}:runReport`, {
      method: 'POST',
      body: {
        dateRanges: [{ startDate, endDate }],
        dimensions: dimensions.map((name) => ({ name })),
        metrics: metrics.map((name) => ({ name })),
        limit,
        offset,
        keepEmptyRows: false,
      },
    });
    for (const row of json.rows || []) {
      const record = {};
      dimensions.forEach((name, i) => { record[name] = row.dimensionValues[i].value; });
      metrics.forEach((name, i) => { record[name] = Number(row.metricValues[i].value) || 0; });
      rows.push(record);
    }
    if (offset + limit >= Number(json.rowCount || 0)) {
      return rows;
    }
  }
}

module.exports = {
  connection,
  assertGscSiteAllowed,
  assertGa4PropertyAllowed,
  gscQuery,
  ga4Report,
};
