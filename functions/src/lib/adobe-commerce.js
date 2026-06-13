const IMS_SCOPE = 'openid,AdobeID,commerce.accs,additional_info.roles,org.read,additional_info.projectedProductContext,profile,email';

const ENVIRONMENTS = {
  stage: {
    tenant: 'UAEyTrirS4qBMAWYZa4uic',
    apiHost: 'na1-sandbox.api.commerce.adobe.com',
  },
  dev: {
    tenant: 'JZTG7BaEUkyB9oWTNxEzEq',
    apiHost: 'na1-sandbox.api.commerce.adobe.com',
  },
  production: {
    tenant: 'VLuKe3eeTwfID5oxmLBfcr',
    apiHost: 'na1-sandbox.api.commerce.adobe.com',
  },
};

let cachedToken = null;
let cachedExpiresAt = 0;

function normalizeError(value, fallback = 'Request failed.') {
  if (typeof value === 'string' && value.trim()) {
    return value;
  }

  if (Array.isArray(value)) {
    return value
      .map((item) => (typeof item === 'string' ? item : item?.message || JSON.stringify(item)))
      .join('; ');
  }

  if (value && typeof value === 'object') {
    if (value.message) {
      return normalizeError(value.message, fallback);
    }

    return JSON.stringify(value);
  }

  return fallback;
}

function envFirst(keys, fallback = '') {
  for (const key of keys) {
    const value = process.env[key];
    if (value !== undefined && String(value).trim() !== '') {
      return String(value).trim();
    }
  }

  return fallback;
}

function environment() {
  const env = envFirst([
    'ADOBE_COMMERCE_ENVIRONMENT',
    'ADOBE_ACCS_ENVIRONMENT',
    'ACCS_ENVIRONMENT',
  ], 'stage').toLowerCase();

  return Object.prototype.hasOwnProperty.call(ENVIRONMENTS, env) ? env : 'stage';
}

function tenantForEnvironment(env) {
  return envFirst([
    `ADOBE_COMMERCE_${env.toUpperCase()}`,
    `ADOBE_ACCS_${env.toUpperCase()}`,
  ], '');
}

function tenantId() {
  const override = envFirst([
    'ADOBE_COMMERCE_TENANT_ID',
    'ADOBE_ACCS_TENANT_ID',
    'ACCS_TENANT_ID',
  ], '');

  if (override) {
    return override;
  }

  const env = environment();
  const tenantByEnv = tenantForEnvironment(env);
  if (tenantByEnv) {
    return tenantByEnv;
  }

  return ENVIRONMENTS[env].tenant;
}

function apiHost() {
  const override = envFirst(['ADOBE_COMMERCE_API_HOST'], '');
  if (override) {
    return override;
  }

  return ENVIRONMENTS[environment()].apiHost;
}

function imsTokenUrl() {
  return envFirst(['ADOBE_COMMERCE_IMS_TOKEN_URL'], '') || 'https://ims-na1.adobelogin.com/ims/token/v3';
}

function baseUrl() {
  return `https://${apiHost()}/${tenantId()}/V1`;
}

function clientId() {
  return envFirst([
    'ADOBE_COMMERCE_CLIENT_ID',
    'ADOBE_ACCS_CLIENT_ID',
    'ACCS_CLIENT_ID',
  ], '');
}

function clientSecret() {
  return envFirst([
    'ADOBE_COMMERCE_CLIENT_SECRET',
    'ADOBE_ACCS_CLIENT_SECRET',
    'ACCS_CLIENT_SECRET',
  ], '');
}

function isConfigured() {
  return Boolean(clientId() && clientSecret() && tenantId());
}

function configError() {
  if (isConfigured()) {
    return null;
  }

  const missing = [];
  if (!clientId()) {
    missing.push('ADOBE_COMMERCE_CLIENT_ID');
  }
  if (!clientSecret()) {
    missing.push('ADOBE_COMMERCE_CLIENT_SECRET');
  }

  const env = environment();
  if (!tenantId()) {
    missing.push(`ADOBE_COMMERCE_${env.toUpperCase()} (or ADOBE_COMMERCE_TENANT_ID)`);
  }

  let message = 'Adobe Commerce is not configured in Function App settings.';
  if (missing.length > 0) {
    message += ` Missing or empty: ${missing.join(', ')}.`;
  }
  message += ' Set ADOBE_COMMERCE_ENVIRONMENT to stage, dev, or production (defaults to stage).';

  return message;
}

function ordersPageSize() {
  const size = Number(envFirst(['ADOBE_COMMERCE_ORDERS_PAGE_SIZE'], '100'));
  return Math.max(1, Math.min(100, size > 0 ? size : 100));
}

async function getToken() {
  if (cachedToken && cachedExpiresAt > Math.floor(Date.now() / 1000)) {
    return { ok: true, error: null, token: cachedToken };
  }

  const error = configError();
  if (error) {
    return { ok: false, error, token: null };
  }

  const body = new URLSearchParams({
    grant_type: 'client_credentials',
    client_id: clientId(),
    client_secret: clientSecret(),
    scope: IMS_SCOPE,
  });

  const response = await fetch(imsTokenUrl(), {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
    signal: AbortSignal.timeout(15000),
  });

  let data;
  try {
    data = await response.json();
  } catch {
    return { ok: false, error: 'Adobe IMS returned an unexpected response.', token: null };
  }

  if (response.status >= 400) {
    const message = normalizeError(
      data.error_description || data.error,
      `Adobe IMS token request failed (HTTP ${response.status}).`
    );
    return { ok: false, error: message, token: null };
  }

  const token = String(data.access_token || '');
  if (!token) {
    return { ok: false, error: 'Adobe IMS did not return an access token.', token: null };
  }

  const expiresIn = Number(data.expires_in || 3600);
  cachedToken = token;
  cachedExpiresAt = Math.floor(Date.now() / 1000) + Math.max(60, expiresIn - 60);

  return { ok: true, error: null, token };
}

async function apiRequest(method, path, query = null) {
  const tokenResult = await getToken();
  if (!tokenResult.ok) {
    return { ok: false, error: tokenResult.error, data: null, status: 0 };
  }

  const normalizedPath = `/${path.replace(/^\//, '')}`;
  let url = `${baseUrl()}${normalizedPath}`;

  if (query && Object.keys(query).length > 0) {
    url += `${normalizedPath.includes('?') ? '&' : '?'}${new URLSearchParams(query).toString()}`;
  }

  const response = await fetch(url, {
    method,
    headers: {
      Authorization: `Bearer ${tokenResult.token}`,
      'x-api-key': clientId(),
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    signal: AbortSignal.timeout(30000),
  });

  let data;
  try {
    data = await response.json();
  } catch {
    return { ok: false, error: 'Adobe Commerce returned an unexpected response.', data: null, status: response.status };
  }

  if (response.status >= 400) {
    const message = normalizeError(
      data.message || data.error,
      `Adobe Commerce request failed (HTTP ${response.status}).`
    );
    return { ok: false, error: message, data, status: response.status };
  }

  return { ok: true, error: null, data, status: response.status };
}

async function fetchPaginatedOrders(baseQuery = {}, maxPages = 200) {
  const pageSize = ordersPageSize();
  let currentPage = 1;
  const rows = [];
  let total = 0;

  while (currentPage <= maxPages) {
    const query = {
      ...baseQuery,
      'searchCriteria[pageSize]': String(pageSize),
      'searchCriteria[currentPage]': String(currentPage),
    };

    const result = await apiRequest('GET', '/orders', query);
    if (!result.ok) {
      return { ok: false, error: result.error, rows, total };
    }

    const items = result.data?.items;
    if (!Array.isArray(items)) {
      break;
    }

    for (const item of items) {
      if (item && typeof item === 'object') {
        rows.push(item);
      }
    }

    total = Number(result.data?.total_count ?? rows.length);
    if (items.length === 0 || items.length < pageSize) {
      break;
    }

    currentPage += 1;
  }

  return { ok: true, error: null, rows, total };
}

module.exports = {
  configError,
  fetchPaginatedOrders,
  normalizeError,
};
