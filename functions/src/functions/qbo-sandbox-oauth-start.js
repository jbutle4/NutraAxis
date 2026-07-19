const crypto = require('crypto');
const { app } = require('@azure/functions');
const qboConfig = require('../lib/qbo-config');

function buildOAuthState() {
  const nonce = crypto.randomBytes(16).toString('hex');
  const sig = crypto.createHmac('sha256', qboConfig.oauthStateSecret()).update(nonce).digest('hex');
  return `${nonce}.${sig}`;
}

app.http('qbo-sandbox-oauth-start', {
  methods: ['GET'],
  authLevel: 'anonymous',
  handler: async (request, context) => {
    const error = qboConfig.oauthConfigError();
    if (error) {
      return respond(500, { ok: false, error });
    }

    const state = buildOAuthState();
    const params = new URLSearchParams({
      client_id: qboConfig.clientId(),
      response_type: 'code',
      scope: 'com.intuit.quickbooks.accounting',
      redirect_uri: qboConfig.redirectUri(),
      state,
    });

    const authorizeUrl = `https://appcenter.intuit.com/connect/oauth2?${params.toString()}`;
    context.log('Redirecting to QuickBooks sandbox OAuth');

    return {
      status: 302,
      headers: { Location: authorizeUrl },
    };
  },
});

function respond(status, data) {
  return {
    status,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  };
}
