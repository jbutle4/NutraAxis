function envValue(key, fallback = '') {
  const value = process.env[key];
  if (value !== undefined && String(value).trim() !== '') {
    return String(value).trim();
  }

  return fallback;
}

function normalizeDomain(domain) {
  let value = String(domain ?? '').trim();
  if (!value) {
    return '';
  }

  value = value.replace(/^https?:\/\//i, '').replace(/\/+$/, '');
  value = value.replace(/\.jazz-oms\.com$/i, '');
  value = value.split('/')[0].trim();

  return value;
}

function domain() {
  return normalizeDomain(envValue('JAZZ_DOMAIN'));
}

function username() {
  return envValue('JAZZ_USERNAME');
}

function password() {
  return envValue('JAZZ_PASSWORD');
}

function tenantCode() {
  return envValue('JAZZ_TENANT_CODE');
}

function pageSize() {
  const size = Number(envValue('JAZZ_PAGE_SIZE', '100'));
  return Math.max(1, Math.min(500, size > 0 ? size : 100));
}

function baseUrl() {
  const override = envValue('JAZZ_BASE_URL').replace(/\/+$/, '');
  if (override) {
    return override;
  }

  const jazzDomain = domain();
  return jazzDomain ? `https://${jazzDomain}.jazz-oms.com` : '';
}

function isConfigured() {
  return Boolean(baseUrl() && username() && password() && tenantCode());
}

function configError() {
  if (isConfigured()) {
    return null;
  }

  return 'Jazz OMS is not configured. Set JAZZ_DOMAIN, JAZZ_USERNAME, JAZZ_PASSWORD, and JAZZ_TENANT_CODE in application settings.';
}

function isCloudflareBlock(responseBody) {
  if (!responseBody) {
    return false;
  }

  return responseBody.includes('Just a moment')
    || responseBody.includes('cf-browser-verification')
    || responseBody.includes('challenge-platform')
    || responseBody.includes('/cdn-cgi/');
}

function cloudflareErrorMessage() {
  return 'Jazz OMS is blocking this server with Cloudflare bot protection (HTTP 403). '
    + 'Azure outbound IPs for the Function App must be allowlisted in Cloudflare for API access.';
}

function tokenError(message, url, status = 0, responseBody = null) {
  if (isCloudflareBlock(responseBody)) {
    return { ok: false, error: cloudflareErrorMessage(), token: null };
  }

  let detail = message;
  if (status > 0) {
    detail += ` (HTTP ${status})`;
  }
  detail += ` at ${url}.`;

  if (responseBody) {
    const preview = responseBody.replace(/\s+/g, ' ').trim();
    if (preview) {
      detail += ` Response: ${preview.length > 160 ? `${preview.slice(0, 160)}…` : preview}`;
    }
  }

  return { ok: false, error: detail, token: null };
}

let cachedToken = null;

async function getToken() {
  if (cachedToken) {
    return { ok: true, error: null, token: cachedToken };
  }

  const error = configError();
  if (error) {
    return { ok: false, error, token: null };
  }

  const url = `${baseUrl()}/api/token/`;
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'User-Agent': 'NutraAxis-Operations/1.0 (+https://nutra-forecast-tool.azurewebsites.net)',
    },
    body: JSON.stringify({
      username: username(),
      password: password(),
    }),
    signal: AbortSignal.timeout(15000),
  });

  const responseBody = await response.text();

  if (response.status === 403 && isCloudflareBlock(responseBody)) {
    return { ok: false, error: cloudflareErrorMessage(), token: null };
  }

  let data;
  try {
    data = JSON.parse(responseBody);
  } catch {
    return tokenError('Jazz OMS returned a non-JSON token response', url, response.status, responseBody);
  }

  if (!data || typeof data !== 'object') {
    return tokenError('Jazz OMS returned an invalid token payload', url, response.status, responseBody);
  }

  if (response.status >= 400) {
    const message = data.detail || data.message || data.error || `Jazz OMS token request failed (HTTP ${response.status}).`;
    return { ok: false, error: String(message), token: null };
  }

  const token = String(data.token || '');
  if (!token) {
    return tokenError('Jazz OMS did not return a token', url, response.status, responseBody);
  }

  cachedToken = token;
  return { ok: true, error: null, token };
}

async function apiGet(url, query = null) {
  const tokenResult = await getToken();
  if (!tokenResult.ok) {
    return { ok: false, error: tokenResult.error, data: null, status: 0 };
  }

  let requestUrl = url;
  if (query && Object.keys(query).length > 0) {
    requestUrl += `${url.includes('?') ? '&' : '?'}${new URLSearchParams(query).toString()}`;
  }

  const response = await fetch(requestUrl, {
    method: 'GET',
    headers: {
      Authorization: `Token ${tokenResult.token}`,
      Tenant: tenantCode(),
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'User-Agent': 'NutraAxis-Operations/1.0 (+https://nutra-forecast-tool.azurewebsites.net)',
    },
    signal: AbortSignal.timeout(30000),
  });

  const responseBody = await response.text();

  if (response.status === 403 && isCloudflareBlock(responseBody)) {
    return { ok: false, error: cloudflareErrorMessage(), data: null, status: response.status };
  }

  let data;
  try {
    data = JSON.parse(responseBody);
  } catch {
    if (isCloudflareBlock(responseBody)) {
      return { ok: false, error: cloudflareErrorMessage(), data: null, status: response.status };
    }

    return { ok: false, error: 'Jazz OMS returned an unexpected response.', data: null, status: response.status };
  }

  if (response.status >= 400) {
    const message = data.detail || data.message || data.error || `Jazz OMS request failed (HTTP ${response.status}).`;
    return { ok: false, error: String(message), data, status: response.status };
  }

  return { ok: true, error: null, data, status: response.status };
}

async function fetchPaginated(path) {
  const error = configError();
  if (error) {
    return { ok: false, error, rows: [] };
  }

  const normalizedPath = `/${path.replace(/^\//, '')}`;
  let url = `${baseUrl()}${normalizedPath}`;
  const params = { limit: String(pageSize()), offset: '0' };
  const rows = [];

  for (let pageGuard = 0; pageGuard < 200 && url; pageGuard += 1) {
    const result = await apiGet(url, pageGuard === 0 ? params : null);
    if (!result.ok) {
      return { ok: false, error: result.error, rows };
    }

    const data = result.data ?? {};
    const records = data.results ?? data.data ?? (Array.isArray(data) ? data : []);

    if (Array.isArray(records)) {
      for (const record of records) {
        if (record && typeof record === 'object') {
          rows.push(record);
        }
      }
    }

    url = typeof data.next === 'string' && data.next ? data.next : '';
  }

  return { ok: true, error: null, rows };
}

async function listInventory() {
  return fetchPaginated('/api/v1/product/inventory');
}

module.exports = {
  configError,
  listInventory,
};
