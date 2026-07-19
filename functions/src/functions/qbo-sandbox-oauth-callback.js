const crypto = require('crypto');
const { app } = require('@azure/functions');
const qboConfig = require('../lib/qbo-config');
const qboClient = require('../lib/qbo-client');

function validateOAuthState(state) {
  const [nonce, sig] = String(state || '').split('.');
  if (!nonce || !sig || sig.length !== 64) {
    return false;
  }

  const expected = crypto.createHmac('sha256', qboConfig.oauthStateSecret()).update(nonce).digest('hex');
  try {
    return crypto.timingSafeEqual(Buffer.from(sig, 'hex'), Buffer.from(expected, 'hex'));
  } catch {
    return false;
  }
}

app.http('qbo-sandbox-oauth-callback', {
  methods: ['GET'],
  authLevel: 'anonymous',
  handler: async (request, context) => {
    const error = qboConfig.oauthConfigError();
    if (error) {
      return htmlResponse(500, 'QuickBooks setup error', error);
    }

    const oauthError = request.query.get('error');
    if (oauthError) {
      return htmlResponse(400, 'QuickBooks authorization denied', oauthError);
    }

    const code = request.query.get('code') || '';
    const realmId = request.query.get('realmId') || '';
    const state = request.query.get('state') || '';

    if (!code || !realmId || !validateOAuthState(state)) {
      return htmlResponse(400, 'Invalid OAuth callback', 'Missing code/realmId or state validation failed.');
    }

    const exchange = await qboClient.exchangeAuthorizationCode(code);
    if (!exchange.ok) {
      context.error('QBO OAuth exchange failed: %s', exchange.error);
      return htmlResponse(502, 'QuickBooks token exchange failed', exchange.error);
    }

    const stored = await qboClient.storeTokenResponse(exchange.data, realmId);
    if (!stored.ok) {
      return htmlResponse(500, 'QuickBooks connection save failed', stored.error);
    }

    context.log(
      'QuickBooks sandbox connected realm=%s company=%s',
      stored.realm_id,
      stored.company_name ?? '(unknown)'
    );

    return htmlResponse(
      200,
      'QuickBooks sandbox connected',
      `Realm ID: ${stored.realm_id}<br>Company: ${stored.company_name ?? '(unknown)'}<br>Environment: ${qboConfig.environment()}<br><br>Sandbox tokens were saved to nutraaxis_test.dbo.QBOConnection.`
    );
  },
});

function htmlResponse(status, title, message) {
  const body = `<!doctype html><html><head><meta charset="utf-8"><title>${title}</title></head><body><h1>${title}</h1><p>${message}</p></body></html>`;

  return {
    status,
    headers: { 'Content-Type': 'text/html; charset=utf-8' },
    body,
  };
}
