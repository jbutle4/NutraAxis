/**
 * TEST ONLY — create ACCS Stage orders via customer cart checkout.
 *
 * Uses customer, address, and payment from order 000000094 (entity_id 93),
 * randomly selects products from the Stage catalog, and places orders in a loop.
 *
 * Query/body params:
 *   count       — orders to create (default 5, max 20)
 *   line_count  — random SKUs per order (default 4, max 20)
 *   dry_run     — true to preview SKUs without placing orders
 *
 * Env:
 *   ACCS_TEST_ORDER_CREATION_SECRET — optional shared secret (x-nutraaxis-test-secret header)
 *   ADOBE_COMMERCE_*                — ACCS Stage credentials (forced to stage environment)
 */

const { app } = require('@azure/functions');
const { createTestOrders, STAGE_ENV } = require('../lib/accs-test-order-create');

const TEST_SECRET = process.env.ACCS_TEST_ORDER_CREATION_SECRET
  || process.env.ACCS_JAZZ_ORDER_TEST_SECRET
  || '';

function parseBoolean(value, fallback = false) {
  if (value === undefined || value === null || value === '') {
    return fallback;
  }

  const normalized = String(value).trim().toLowerCase();
  return normalized === '1' || normalized === 'true' || normalized === 'yes';
}

function firstNonEmpty(...values) {
  for (const value of values) {
    const trimmed = String(value ?? '').trim();
    if (trimmed) {
      return trimmed;
    }
  }

  return '';
}

function parsePositiveInt(value, fallback) {
  const parsed = Number.parseInt(String(value ?? ''), 10);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    return fallback;
  }
  return parsed;
}

async function parseRequestParams(request) {
  let body = {};
  try {
    body = await request.json();
  } catch {
    body = {};
  }

  const params = body && typeof body === 'object' && body.params && typeof body.params === 'object'
    ? body.params
    : body;

  return {
    count: parsePositiveInt(firstNonEmpty(request.query.get('count'), params.count), 5),
    lineCount: parsePositiveInt(firstNonEmpty(request.query.get('line_count'), params.line_count), 4),
    dryRun: parseBoolean(firstNonEmpty(request.query.get('dry_run'), params.dry_run)),
  };
}

function verifySecret(request, context) {
  if (!TEST_SECRET) {
    return true;
  }

  const incoming = request.headers.get('x-nutraaxis-test-secret') || '';
  if (incoming !== TEST_SECRET) {
    context.warn('accs-test-order-creation secret verification failed');
    return false;
  }

  return true;
}

function respond(status, payload) {
  return {
    status,
    headers: { 'Content-Type': 'application/json' },
    jsonBody: payload,
  };
}

app.http('accs-test-order-creation', {
  methods: ['GET', 'POST'],
  authLevel: 'function',
  handler: async (request, context) => {
    if (!verifySecret(request, context)) {
      return respond(401, { ok: false, error: 'Unauthorized' });
    }

    const params = await parseRequestParams(request);
    context.log(
      'accs-test-order-creation count=%s line_count=%s dry_run=%s',
      params.count,
      params.lineCount,
      params.dryRun
    );

    const result = await createTestOrders({
      count: params.count,
      lineCount: params.lineCount,
      dryRun: params.dryRun,
      environment: STAGE_ENV,
    }, context);

    if (result.error && result.orders.length === 0) {
      return respond(400, { ok: false, error: result.error });
    }

    const status = result.ok ? (params.dryRun ? 200 : 201) : 502;
    return respond(status, {
      ok: result.ok,
      dry_run: result.dry_run,
      accs_environment: result.accs_environment,
      reference: result.reference,
      catalog_product_count: result.catalog_product_count,
      requested_count: result.requested_count,
      line_count: result.line_count,
      created_count: result.created_count,
      orders: result.orders,
      errors: result.errors,
    });
  },
});
