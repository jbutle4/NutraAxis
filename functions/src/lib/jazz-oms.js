function envValue(key, fallback = '') {
  const value = process.env[key];
  if (value !== undefined && String(value).trim() !== '') {
    return String(value).trim();
  }

  return fallback;
}

function envFirst(keys, fallback = '') {
  for (const key of keys) {
    const value = envValue(key);
    if (value) {
      return value;
    }
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

function resolveProfile(options = {}) {
  if (options.uat === true || envValue('JAZZ_USE_UAT').toLowerCase() === 'true') {
    return 'uat';
  }

  if (options.uat === false) {
    return 'prod';
  }

  return 'default';
}

function domain(options = {}) {
  const profile = resolveProfile(options);

  if (profile === 'uat') {
    return normalizeDomain(envFirst(['JAZZ_UAT_DOMAIN', 'JAZZ_DOMAIN']));
  }

  if (profile === 'prod') {
    return normalizeDomain(envFirst(['JAZZ_DOMAIN_PROD', 'JAZZ_PRODUCTION_DOMAIN']));
  }

  return normalizeDomain(envValue('JAZZ_DOMAIN'));
}

function username(options = {}) {
  const profile = resolveProfile(options);

  if (profile === 'uat') {
    return envFirst(['JAZZ_UAT_USERNAME', 'JAZZ_USERNAME']);
  }

  if (profile === 'prod') {
    return envFirst(['JAZZ_USERNAME_PROD', 'JAZZ_PRODUCTION_USERNAME']);
  }

  return envValue('JAZZ_USERNAME');
}

function password(options = {}) {
  const profile = resolveProfile(options);

  if (profile === 'uat') {
    return envFirst(['JAZZ_UAT_PASSWORD', 'JAZZ_PASSWORD']);
  }

  if (profile === 'prod') {
    return envFirst(['JAZZ_PASSWORD_PROD', 'JAZZ_PRODUCTION_PASSWORD']);
  }

  return envValue('JAZZ_PASSWORD');
}

function tenantCode() {
  return envValue('JAZZ_TENANT_CODE');
}

function pageSize() {
  const size = Number(envValue('JAZZ_PAGE_SIZE', '100'));
  return Math.max(1, Math.min(500, size > 0 ? size : 100));
}

function baseUrl(options = {}) {
  const profile = resolveProfile(options);
  let override = '';

  if (profile === 'uat') {
    override = envFirst(['JAZZ_UAT_BASE_URL', 'JAZZ_BASE_URL']).replace(/\/+$/, '');
  } else if (profile === 'prod') {
    override = envFirst(['JAZZ_BASE_URL_PROD', 'JAZZ_PRODUCTION_BASE_URL']).replace(/\/+$/, '');
  } else {
    override = envValue('JAZZ_BASE_URL').replace(/\/+$/, '');
  }

  if (override) {
    return override;
  }

  const jazzDomain = domain(options);
  return jazzDomain ? `https://${jazzDomain}.jazz-oms.com` : '';
}

function orderImportEndpoint() {
  const endpoint = envValue('JAZZ_ORDER_IMPORT_ENDPOINT', '/api/v1/order/import');
  return `/${endpoint.replace(/^\/+/, '')}`;
}

function isConfigured(options = {}) {
  return Boolean(baseUrl(options) && username(options) && password(options) && tenantCode());
}

function configError(options = {}) {
  if (isConfigured(options)) {
    return null;
  }

  const profile = resolveProfile(options);
  if (profile === 'uat') {
    return 'Jazz OMS UAT is not configured. Set JAZZ_UAT_DOMAIN (or JAZZ_DOMAIN), JAZZ_UAT_USERNAME (or JAZZ_USERNAME), JAZZ_UAT_PASSWORD (or JAZZ_PASSWORD), and JAZZ_TENANT_CODE.';
  }

  if (profile === 'prod') {
    return 'Jazz OMS production is not configured. Set JAZZ_DOMAIN_PROD, JAZZ_USERNAME_PROD, JAZZ_PASSWORD_PROD, and JAZZ_TENANT_CODE.';
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

const cachedTokens = new Map();

function tokenCacheKey(options = {}) {
  return resolveProfile(options);
}

async function getToken(options = {}) {
  const cacheKey = tokenCacheKey(options);
  if (cachedTokens.has(cacheKey)) {
    return { ok: true, error: null, token: cachedTokens.get(cacheKey) };
  }

  const error = configError(options);
  if (error) {
    return { ok: false, error, token: null };
  }

  const url = `${baseUrl(options)}/api/token/`;
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'User-Agent': 'NutraAxis-Operations/1.0 (+https://nutra-forecast-tool.azurewebsites.net)',
    },
    body: JSON.stringify({
      username: username(options),
      password: password(options),
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

  cachedTokens.set(cacheKey, token);
  return { ok: true, error: null, token };
}

function parseResponseBody(responseBody, status) {
  if (!responseBody || !String(responseBody).trim()) {
    return { data: null, error: status >= 400 ? 'Jazz OMS returned an empty error response.' : null };
  }

  try {
    return { data: JSON.parse(responseBody), error: null };
  } catch {
    if (isCloudflareBlock(responseBody)) {
      return { data: null, error: cloudflareErrorMessage() };
    }

    if (status >= 400) {
      const preview = String(responseBody).replace(/\s+/g, ' ').trim();
      return {
        data: preview ? { detail: preview.slice(0, 500) } : null,
        error: preview || 'Jazz OMS returned a non-JSON error response.',
      };
    }

    return { data: null, error: 'Jazz OMS returned an unexpected response.' };
  }
}

function responseErrorMessage(data, status) {
  if (Array.isArray(data)) {
    return data.map((item) => (typeof item === 'string' ? item : JSON.stringify(item))).join('; ');
  }

  if (typeof data === 'string') {
    return data;
  }

  if (data && typeof data === 'object') {
    return String(data.detail || data.message || data.error || `Jazz OMS request failed (HTTP ${status}).`);
  }

  return `Jazz OMS request failed (HTTP ${status}).`;
}

async function apiGet(url, query = null, options = {}) {
  const tokenResult = await getToken(options);
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

  const parsed = parseResponseBody(responseBody, response.status);
  if (parsed.error && response.status < 400) {
    return { ok: false, error: parsed.error, data: null, status: response.status };
  }

  const data = parsed.data;
  if (response.status >= 400) {
    return {
      ok: false,
      error: parsed.error || responseErrorMessage(data, response.status),
      data,
      status: response.status,
    };
  }

  return { ok: true, error: null, data, status: response.status };
}

async function apiPost(path, payload, options = {}) {
  const error = configError(options);
  if (error) {
    return { ok: false, error, data: null, status: 0 };
  }

  const tokenResult = await getToken(options);
  if (!tokenResult.ok) {
    return { ok: false, error: tokenResult.error, data: null, status: 0 };
  }

  const normalizedPath = `/${String(path).replace(/^\/+/, '')}`;
  const url = `${baseUrl(options)}${normalizedPath}`;

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      Authorization: `Token ${tokenResult.token}`,
      Tenant: tenantCode(),
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'User-Agent': 'NutraAxis-Operations/1.0 (+https://nutra-forecast-tool.azurewebsites.net)',
    },
    body: JSON.stringify(payload),
    signal: AbortSignal.timeout(30000),
  });

  const responseBody = await response.text();

  if (response.status === 403 && isCloudflareBlock(responseBody)) {
    return { ok: false, error: cloudflareErrorMessage(), data: null, status: response.status };
  }

  const parsed = parseResponseBody(responseBody, response.status);
  const data = parsed.data;

  if (response.status >= 400) {
    return {
      ok: false,
      error: parsed.error || responseErrorMessage(data, response.status),
      data,
      status: response.status,
    };
  }

  return { ok: true, error: null, data, status: response.status };
}

async function importOrder(payload, options = {}) {
  return apiPost(orderImportEndpoint(), payload, { uat: true, ...options });
}

async function fetchPaginated(path, options = {}) {
  const error = configError(options);
  if (error) {
    return { ok: false, error, rows: [] };
  }

  const normalizedPath = `/${path.replace(/^\//, '')}`;
  let url = `${baseUrl(options)}${normalizedPath}`;
  const params = { limit: String(pageSize()), offset: '0' };
  const rows = [];

  for (let pageGuard = 0; pageGuard < 200 && url; pageGuard += 1) {
    const result = await apiGet(url, pageGuard === 0 ? params : null, options);
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

async function listInventory(options = {}) {
  return fetchPaginated('/api/v1/product/inventory', options);
}

module.exports = {
  baseUrl,
  configError,
  importOrder,
  listInventory,
  orderImportEndpoint,
};
