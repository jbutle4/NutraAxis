/** QuickBooks Online — sandbox only on the test function app. */

function envValue(name, fallback = '') {
  const value = process.env[name];
  if (value !== undefined && String(value).trim() !== '') {
    return String(value).trim();
  }
  return fallback;
}

function environment() {
  const env = envValue('QBO_ENVIRONMENT', 'sandbox').toLowerCase();
  return env === 'production' ? 'production' : 'sandbox';
}

/** Prefer *_SANDBOX / *_PROD keys; fall back to unsuffixed for the active QBO_ENVIRONMENT. */
function invAccountSetting(baseKey, fallback = '') {
  const key = String(baseKey || '').trim();
  if (!key) {
    return fallback;
  }
  if (environment() === 'production') {
    return envValue(`${key}_PROD`, envValue(key, fallback));
  }
  return envValue(`${key}_SANDBOX`, envValue(key, fallback));
}

function clientId() {
  return envValue('QBO_CLIENT_ID');
}

function clientSecret() {
  return envValue('QBO_CLIENT_SECRET');
}

function redirectUri() {
  return envValue('QBO_REDIRECT_URI');
}

function oauthStateSecret() {
  return envValue('QBO_OAUTH_STATE_SECRET', envValue('ACCS_WEBHOOK_SECRET', 'qbo-sandbox-oauth'));
}

function apiBaseUrl() {
  return environment() === 'production'
    ? 'https://quickbooks.api.intuit.com'
    : 'https://sandbox-quickbooks.api.intuit.com';
}

function fallbackItemId() {
  return envValue('QBO_SANDBOX_FALLBACK_ITEM_ID');
}

/** Suffix appended to QBO DocNumber while legacy integration is active (Jazz uses -02). */
function orderTestSuffix() {
  return envValue('QBO_ORDER_TEST_SUFFIX', '03');
}

function configError() {
  if (!clientId() || !clientSecret()) {
    return 'QBO_CLIENT_ID and QBO_CLIENT_SECRET are not configured on the function app.';
  }
  return null;
}

function oauthConfigError() {
  const base = configError();
  if (base) {
    return base;
  }
  if (!redirectUri()) {
    return 'QBO_REDIRECT_URI is not configured on the function app.';
  }
  return null;
}

module.exports = {
  environment,
  invAccountSetting,
  clientId,
  clientSecret,
  redirectUri,
  oauthStateSecret,
  apiBaseUrl,
  fallbackItemId,
  orderTestSuffix,
  configError,
  oauthConfigError,
};
