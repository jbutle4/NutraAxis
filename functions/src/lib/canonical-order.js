const { buildSuppliersMap } = require('./order-suppliers');

const SCHEMA_VERSION = 1;

/**
 * Build canonical order message for accs-order-canonical-test topic.
 * @param {object} accsOrder - raw ACCS order
 * @param {object[]} enrichedItems - items with .fulfillment
 * @param {object} [meta]
 */
function buildCanonicalOrder(accsOrder, enrichedItems, meta = {}) {
  const entityId = Number(accsOrder?.entity_id || 0);
  const incrementId = String(accsOrder?.increment_id ?? '').trim();

  if (!entityId || !incrementId) {
    return { ok: false, error: 'Order is missing entity_id or increment_id.' };
  }

  const items = (enrichedItems || []).map((item) => ({
    item_id: item.item_id ?? null,
    sku: item.sku ?? null,
    name: item.name ?? null,
    qty_ordered: item.qty_ordered ?? null,
    price: item.price ?? null,
    row_total: item.row_total ?? null,
    product_type: item.product_type ?? null,
    parent_item_id: item.parent_item_id ?? null,
    fulfillment: item.fulfillment ?? null,
  }));

  const suppliers = buildSuppliersMap(enrichedItems);

  return {
    ok: true,
    canonical: {
      schemaVersion: SCHEMA_VERSION,
      publishedAt: new Date().toISOString(),
      sourceEnvironment: String(meta.sourceEnvironment || 'stage').trim(),
      orderId: entityId,
      incrementId,
      order: accsOrder,
      items,
      suppliers,
      webhook: {
        receivedAt: meta.receivedAt || new Date().toISOString(),
        eventType: meta.eventType || null,
      },
    },
  };
}

function parseCanonicalBody(body) {
  if (!body || typeof body !== 'object') {
    return { ok: false, error: 'Canonical message body is missing.' };
  }

  if (!body.order || !Array.isArray(body.items)) {
    return { ok: false, error: 'Canonical message is missing order or items.' };
  }

  return { ok: true, canonical: body };
}

/** Rebuild ACCS-shaped order with enriched items for mappers / email. */
function accsOrderFromCanonical(canonical) {
  const order = { ...canonical.order };
  const byItemId = new Map(
    (canonical.items || [])
      .filter((item) => item.item_id != null)
      .map((item) => [Number(item.item_id), item])
  );

  order.items = (order.items || []).map((line) => {
    const meta = byItemId.get(Number(line.item_id));
    return meta ? { ...line, fulfillment: meta.fulfillment ?? line.fulfillment } : line;
  });

  return order;
}

module.exports = {
  SCHEMA_VERSION,
  buildCanonicalOrder,
  parseCanonicalBody,
  accsOrderFromCanonical,
};
