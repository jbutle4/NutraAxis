const qboConfig = require('./qbo-config');
const qboConnection = require('./qbo-connection');

const TOKEN_URL = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
const MINOR_VERSION = 65;

async function tokenRequest(fields) {
  const auth = Buffer.from(`${qboConfig.clientId()}:${qboConfig.clientSecret()}`).toString('base64');
  const body = new URLSearchParams(fields);

  const response = await fetch(TOKEN_URL, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
      Authorization: `Basic ${auth}`,
    },
    body,
  });

  let data;
  try {
    data = await response.json();
  } catch {
    data = null;
  }

  if (!response.ok) {
    const message = data?.error_description || data?.error || `QuickBooks OAuth failed (${response.status}).`;
    return { ok: false, error: String(message), data };
  }

  return { ok: true, error: null, data };
}

function expiresAtFromToken(data) {
  const expiresIn = Number(data?.expires_in || 3600);
  const date = new Date(Date.now() + Math.max(60, expiresIn - 60) * 1000);
  return date.toISOString().slice(0, 19).replace('T', ' ');
}

async function storeTokenResponse(data, realmId, connectionStore = qboConnection.staging) {
  const accessToken = String(data?.access_token || '');
  const refreshToken = String(data?.refresh_token || '');

  if (!accessToken || !refreshToken) {
    return { ok: false, error: 'QuickBooks did not return connection tokens.' };
  }

  const existing = await connectionStore.getConnection();
  const resolvedRealm = String(realmId || existing?.RealmID || '').trim();
  if (!resolvedRealm) {
    return { ok: false, error: 'QuickBooks company realm ID is missing.' };
  }

  let companyName = existing?.CompanyName ?? null;
  if (!companyName) {
    const info = await fetchCompanyName(resolvedRealm, accessToken);
    if (info.ok) {
      companyName = info.name;
    }
  }

  await connectionStore.saveConnection({
    realm_id: resolvedRealm,
    company_name: companyName,
    access_token: accessToken,
    refresh_token: refreshToken,
    access_token_expires_at: expiresAtFromToken(data),
  });

  return { ok: true, error: null, realm_id: resolvedRealm, company_name: companyName };
}

async function exchangeAuthorizationCode(code) {
  return tokenRequest({
    grant_type: 'authorization_code',
    code,
    redirect_uri: qboConfig.redirectUri(),
  });
}

async function refreshAccessToken(connection) {
  return tokenRequest({
    grant_type: 'refresh_token',
    refresh_token: String(connection.RefreshToken),
  });
}

async function fetchCompanyName(realmId, accessToken) {
  const url = `${qboConfig.apiBaseUrl()}/v3/company/${encodeURIComponent(realmId)}/companyinfo/${encodeURIComponent(realmId)}?minorversion=${MINOR_VERSION}`;

  const response = await fetch(url, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${accessToken}`,
    },
  });

  if (!response.ok) {
    return { ok: false, name: null };
  }

  const data = await response.json();
  const name = String(data?.CompanyInfo?.CompanyName ?? '').trim();
  return { ok: name !== '', name: name || null };
}

async function refreshAndStore(connection, connectionStore = qboConnection.staging) {
  const refresh = await refreshAccessToken(connection);
  if (!refresh.ok) {
    return refresh;
  }

  const stored = await storeTokenResponse(refresh.data, connection.RealmID, connectionStore);
  if (!stored.ok) {
    return stored;
  }

  const updated = await connectionStore.getConnection();
  return { ok: true, connection: updated };
}

async function ensureAccessToken(connectionStore = qboConnection.staging, { forceRefresh = false } = {}) {
  const connection = await connectionStore.getConnection();
  if (!connection) {
    return {
      ok: false,
      error: connectionStore === qboConnection.production
        ? 'QuickBooks is not connected in the production database. Connect via Accounting in the web app.'
        : 'QuickBooks sandbox is not connected. Open /api/qbo-sandbox-oauth-start on the test function app.',
    };
  }

  // Refresh 2 minutes early — SQL DateTime2 expiry can look valid while Intuit already rejects the JWT.
  const expires = new Date(connection.AccessTokenExpiresAt);
  const refreshSkewMs = 2 * 60 * 1000;
  if (!forceRefresh && expires.getTime() - refreshSkewMs > Date.now()) {
    return { ok: true, connection };
  }

  return refreshAndStore(connection, connectionStore);
}

async function apiRequest(method, path, { query = null, body = null, connectionStore = qboConnection.staging } = {}) {
  const configError = qboConfig.configError();
  if (configError) {
    return { ok: false, error: configError, data: null, status: 0 };
  }

  let tokenResult = await ensureAccessToken(connectionStore);
  if (!tokenResult.ok) {
    return tokenResult;
  }

  const buildUrl = (realmId) => {
    let url = `${qboConfig.apiBaseUrl()}/v3/company/${encodeURIComponent(realmId)}${path}`;
    if (query && Object.keys(query).length > 0) {
      const params = new URLSearchParams(query);
      url += (path.includes('?') ? '&' : '?') + params.toString();
    }
    return url;
  };

  const send = async (connection) => {
    const options = {
      method,
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${connection.AccessToken}`,
      },
    };

    if (body !== null) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }

    const response = await fetch(buildUrl(String(connection.RealmID)), options);
    let data;
    try {
      data = await response.json();
    } catch {
      data = null;
    }
    return { response, data };
  };

  let connection = tokenResult.connection;
  let { response, data } = await send(connection);

  if (response.status === 401) {
    tokenResult = await ensureAccessToken(connectionStore, { forceRefresh: true });
    if (!tokenResult.ok) {
      return tokenResult;
    }
    connection = tokenResult.connection;
    ({ response, data } = await send(connection));
  }

  if (!response.ok) {
    const fault = data?.Fault?.Error?.[0];
    const message = [fault?.Message, fault?.Detail].filter(Boolean).join(' ') || `QuickBooks request failed (${response.status}).`;
    return { ok: false, error: message, data, status: response.status };
  }

  return { ok: true, error: null, data, status: response.status };
}

async function query(sqlText, maxResults = 100, connectionStore = qboConnection.staging) {
  let text = String(sqlText || '').trim();
  if (!text.toUpperCase().includes('MAXRESULTS')) {
    text += ` MAXRESULTS ${maxResults}`;
  }

  return apiRequest('GET', '/query', {
    query: {
      query: text,
      minorversion: String(MINOR_VERSION),
    },
    connectionStore,
  });
}

function extractQueryRows(data, entityKeys) {
  const response = data?.QueryResponse ?? {};
  for (const key of entityKeys) {
    if (Array.isArray(response[key])) {
      return response[key];
    }
  }
  return [];
}

module.exports = {
  exchangeAuthorizationCode,
  storeTokenResponse,
  ensureAccessToken,
  apiRequest,
  query,
  extractQueryRows,
  MINOR_VERSION,
};
