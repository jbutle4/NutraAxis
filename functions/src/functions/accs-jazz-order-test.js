/**
 * TEST ONLY — send an ACCS Stage order to Jazz OMS UAT.
 *
 * Fetches a real order from ACCS Stage, maps it to Jazz /api/v1/order/import,
 * increments the order number to avoid duplicate rejection, and POSTs to Jazz UAT.
 *
 * Query/body params:
 *   entity_id | order_id     — ACCS order entity_id (preferred)
 *   increment_id             — ACCS increment_id (alternative lookup)
 *   dry_run                  — true to return payload without sending
 *   test_suffix              — explicit Jazz order suffix (e.g. MYRUN-01)
 *   test_seq                 — numeric suffix → {increment}-TEST-{seq padded}
 *   cart_only                — true (default) to include only Cart fulfillment lines
 *   force_sku                — override SKU on all lines (UAT testing when SKU missing in Jazz)
 *
 * Env:
 *   ACCS_JAZZ_ORDER_TEST_SECRET — optional shared secret (x-nutraaxis-test-secret header)
 *   JAZZ_UAT_* / JAZZ_*         — Jazz UAT credentials (never prod vars)
 *   ADOBE_COMMERCE_*            — ACCS Stage credentials (forced to stage environment)
 */

const { app } = require('@azure/functions');
const adobeCommerce = require('../lib/adobe-commerce');
const jazzOms = require('../lib/jazz-oms');
const { mapAccsOrderToJazzImport } = require('../lib/jazz-order-mapper');
const { enrichItemsWithFulfillment } = require('../lib/accs-client');

const TEST_SECRET = process.env.ACCS_JAZZ_ORDER_TEST_SECRET || '';
const STAGE_ENV = 'stage';
const JAZZ_UAT = { uat: true };

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
    entityId: firstNonEmpty(request.query.get('entity_id'), request.query.get('order_id'), params.entity_id, params.order_id),
    incrementId: firstNonEmpty(request.query.get('increment_id'), params.increment_id),
    dryRun: parseBoolean(firstNonEmpty(request.query.get('dry_run'), params.dry_run)),
    testSuffix: firstNonEmpty(request.query.get('test_suffix'), params.test_suffix),
    testSeq: firstNonEmpty(request.query.get('test_seq'), params.test_seq),
    cartOnly: parseBoolean(firstNonEmpty(request.query.get('cart_only'), params.cart_only), true),
    forceSku: firstNonEmpty(request.query.get('force_sku'), params.force_sku),
  };
}

function verifySecret(request, context) {
  if (!TEST_SECRET) {
    return true;
  }

  const incoming = request.headers.get('x-nutraaxis-test-secret') || '';
  if (incoming !== TEST_SECRET) {
    context.warn('accs-jazz-order-test secret verification failed');
    return false;
  }

  return true;
}

async function fetchAccsStageOrder(params) {
  const accsError = adobeCommerce.configError();
  if (accsError) {
    return { ok: false, error: accsError, order: null };
  }

  if (params.entityId) {
    const result = await adobeCommerce.fetchOrderByEntityId(params.entityId, { environment: STAGE_ENV });
    return { ok: result.ok, error: result.error, order: result.order };
  }

  if (params.incrementId) {
    const result = await adobeCommerce.fetchPaginatedOrders({
      'searchCriteria[filter_groups][0][filters][0][field]': 'increment_id',
      'searchCriteria[filter_groups][0][filters][0][value]': params.incrementId,
      'searchCriteria[filter_groups][0][filters][0][condition_type]': 'eq',
    }, 1, { environment: STAGE_ENV });

    if (!result.ok) {
      return { ok: false, error: result.error, order: null };
    }

    const order = result.rows[0] ?? null;
    if (!order) {
      return { ok: false, error: `ACCS Stage order increment_id ${params.incrementId} not found.`, order: null };
    }

    return { ok: true, error: null, order };
  }

  return { ok: false, error: 'Provide entity_id (or order_id) or increment_id.', order: null };
}

function filterCartItems(items) {
  return items.filter((item) => String(item.fulfillment ?? '').trim().toLowerCase() === 'cart');
}

function respond(status, payload) {
  return {
    status,
    headers: { 'Content-Type': 'application/json' },
    jsonBody: payload,
  };
}

app.http('accs-jazz-order-test', {
  methods: ['GET', 'POST'],
  authLevel: 'function',
  handler: async (request, context) => {
    if (!verifySecret(request, context)) {
      return respond(401, { ok: false, error: 'Unauthorized' });
    }

    const params = await parseRequestParams(request);
    context.log(
      'accs-jazz-order-test entity_id=%s increment_id=%s dry_run=%s cart_only=%s',
      params.entityId || '(none)',
      params.incrementId || '(none)',
      params.dryRun,
      params.cartOnly
    );

    const jazzError = jazzOms.configError(JAZZ_UAT);
    if (jazzError) {
      return respond(500, { ok: false, error: jazzError });
    }

    const fetchResult = await fetchAccsStageOrder(params);
    if (!fetchResult.ok) {
      return respond(400, { ok: false, error: fetchResult.error });
    }

    const order = fetchResult.order;
    const sourceOrder = {
      entity_id: order.entity_id,
      increment_id: order.increment_id,
      status: order.status,
      created_at: order.created_at,
      customer_email: order.customer_email,
      item_count: Array.isArray(order.items) ? order.items.length : 0,
    };

    let enrichedItems = await enrichItemsWithFulfillment(order.items || [], context);
    const skippedItems = [];

    if (params.cartOnly) {
      const cartItems = filterCartItems(enrichedItems);
      for (const item of enrichedItems) {
        if (!cartItems.includes(item)) {
          skippedItems.push({
            sku: item.sku,
            fulfillment: item.fulfillment ?? null,
            reason: 'cart_only=true',
          });
        }
      }
      enrichedItems = cartItems;
    }

    const mapResult = mapAccsOrderToJazzImport(order, {
      items: enrichedItems,
      testSuffix: params.testSuffix,
      testSeq: params.testSeq,
      forceSku: params.forceSku,
    });

    if (!mapResult.ok) {
      return respond(400, {
        ok: false,
        error: mapResult.error,
        source: {
          accsEnvironment: STAGE_ENV,
          jazzTarget: 'uat',
          order: sourceOrder,
          skippedItems,
        },
      });
    }

    const response = {
      ok: true,
      dry_run: params.dryRun,
      source: {
        accsEnvironment: STAGE_ENV,
        jazzTarget: 'uat',
        jazzBaseUrl: jazzOms.baseUrl(JAZZ_UAT),
        jazzEndpoint: jazzOms.orderImportEndpoint(),
        order: sourceOrder,
        skippedItems,
      },
      transformed: mapResult.summary,
      payload: mapResult.payload,
      jazz: null,
      errors: [],
    };

    if (params.dryRun) {
      context.log('accs-jazz-order-test dry_run order=%s jazz_order=%s', sourceOrder.increment_id, mapResult.summary.jazzOrderNumber);
      return respond(200, response);
    }

    const jazzResult = await jazzOms.importOrder(mapResult.payload, JAZZ_UAT);
    response.jazz = {
      ok: jazzResult.ok,
      status: jazzResult.status,
      data: jazzResult.data,
      error: jazzResult.error,
    };

    if (!jazzResult.ok) {
      response.ok = false;
      response.errors.push(jazzResult.error);
      context.error('Jazz import failed: %s', jazzResult.error);
      return respond(jazzResult.status >= 400 ? jazzResult.status : 502, response);
    }

    context.log(
      'accs-jazz-order-test submitted accs=%s jazz=%s http=%s',
      sourceOrder.increment_id,
      mapResult.summary.jazzOrderNumber,
      jazzResult.status
    );

    return respond(201, response);
  },
});
