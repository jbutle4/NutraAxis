const jazzOms = require('./jazz-oms');
const { mapAccsOrderToJazzImport, jazzOrderTestSuffix } = require('./jazz-order-mapper');
const { parseCanonicalBody, accsOrderFromCanonical } = require('./canonical-order');
const { filterItemsBySupplier } = require('./order-suppliers');
const { validateJazzImportPayload, collectJazzHolds } = require('./jazz-order-validation');

const JAZZ_UAT = { uat: true };

async function submitCartOrderToJazz(canonical, context, options = {}) {
  const parsed = parseCanonicalBody(canonical);
  if (!parsed.ok) {
    throw new Error(parsed.error);
  }

  const incrementId = parsed.canonical.incrementId;
  const sourceEnvironment = String(parsed.canonical.sourceEnvironment || 'stage').trim();

  const jazzError = jazzOms.configError(JAZZ_UAT);
  if (jazzError) {
    throw new Error(jazzError);
  }

  const order = accsOrderFromCanonical(parsed.canonical);
  const cartItems = filterItemsBySupplier(order.items, 'Cart');

  if (cartItems.length === 0) {
    context.log('No Cart lines for order %s — skipping Jazz submit', incrementId);
    return { ok: true, skipped: true, reason: 'no_cart_lines', increment_id: incrementId };
  }

  const mapOptions = {
    items: cartItems,
    testSeq: options.testSeq ?? null,
    forceSku: options.forceSku ?? null,
    testSuffix: String(options.testSuffix || jazzOrderTestSuffix()).trim(),
  };

  const mapResult = mapAccsOrderToJazzImport(order, mapOptions);

  if (!mapResult.ok) {
    throw new Error(mapResult.error);
  }

  const validation = validateJazzImportPayload(mapResult.payload);
  if (!validation.ok) {
    context.warn(
      'Jazz import blocked for order %s: %s',
      incrementId,
      validation.errors.join('; ')
    );

    return {
      ok: false,
      hard_fail: true,
      blocked: true,
      increment_id: incrementId,
      external_ref: mapResult.summary.jazzOrderNumber,
      jazz_order_number: mapResult.summary.jazzOrderNumber,
      issue_type: 'Missing or invalid ship-to data',
      validation_errors: validation.errors,
      errors: validation.errors,
    };
  }

  if (options.dryRun) {
    return {
      ok: true,
      dry_run: true,
      increment_id: incrementId,
      external_ref: mapResult.summary.jazzOrderNumber,
      jazz_order_number: mapResult.summary.jazzOrderNumber,
      payload: mapResult.payload,
    };
  }

  const jazzResult = await jazzOms.importOrder(mapResult.payload, JAZZ_UAT);
  if (!jazzResult.ok) {
    throw new Error(jazzResult.error || `Jazz import failed with HTTP ${jazzResult.status}`);
  }

  const holds = collectJazzHolds(jazzResult.data);
  if (holds.length > 0) {
    context.warn(
      'Jazz import created order %s with %s hold(s)',
      mapResult.summary.jazzOrderNumber,
      holds.length
    );

    return {
      ok: false,
      needs_attention: true,
      delivered: true,
      increment_id: incrementId,
      external_ref: mapResult.summary.jazzOrderNumber,
      jazz_order_number: mapResult.summary.jazzOrderNumber,
      jazz_status: jazzResult.status,
      jazz_data: jazzResult.data,
      holds,
      issue_type: 'Jazz order created with holds',
      result_message: `Jazz order ${mapResult.summary.jazzOrderNumber} created with ${holds.length} hold(s).`,
    };
  }

  context.log(
    'Jazz UAT import order %s → %s (http=%s)',
    incrementId,
    mapResult.summary.jazzOrderNumber,
    jazzResult.status
  );

  return {
    ok: true,
    increment_id: incrementId,
    external_ref: mapResult.summary.jazzOrderNumber,
    jazz_order_number: mapResult.summary.jazzOrderNumber,
    jazz_status: jazzResult.status,
    jazz_data: jazzResult.data,
    result_message: `Jazz order ${mapResult.summary.jazzOrderNumber} submitted successfully.`,
  };
}

module.exports = {
  submitCartOrderToJazz,
};
