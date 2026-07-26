const { accsOrderFromCanonical } = require('./canonical-order');
const qboConfig = require('./qbo-config');

function buildQboDocNumber(incrementId, suffix) {
  const base = String(incrementId ?? '').trim();
  const tag = String(suffix ?? '').trim();
  if (!base) {
    return '';
  }
  return tag ? `${base}-${tag}` : base;
}

function escapeQboQueryValue(value) {
  return String(value ?? '').replace(/'/g, "\\'");
}

function formatTxnDate(value) {
  const trimmed = String(value ?? '').trim();
  if (!trimmed) {
    return new Date().toISOString().slice(0, 10);
  }

  const date = new Date(trimmed.includes('T') ? trimmed : `${trimmed.replace(' ', 'T')}Z`);
  if (Number.isNaN(date.getTime())) {
    return trimmed.slice(0, 10);
  }

  return date.toISOString().slice(0, 10);
}

function billingAddress(order) {
  return order?.billing_address ?? null;
}

function customerDisplayName(order) {
  const bill = billingAddress(order);
  const first = String(bill?.firstname ?? order?.customer_firstname ?? '').trim();
  const last = String(bill?.lastname ?? order?.customer_lastname ?? '').trim();
  const email = String(bill?.email ?? order?.customer_email ?? '').trim();
  const name = `${first} ${last}`.trim();

  if (name) {
    return name;
  }

  return email || `ACCS Customer ${order?.entity_id ?? 'unknown'}`;
}

function mapSalesReceiptPayload(order, options = {}) {
  const incrementId = String(order.increment_id ?? '').trim();
  if (!incrementId) {
    return { ok: false, error: 'ACCS order is missing increment_id.' };
  }

  const docSuffix = String(options.docSuffix ?? qboConfig.orderTestSuffix()).trim();
  const docNumber = buildQboDocNumber(incrementId, docSuffix);

  const bill = billingAddress(order);
  const email = String(bill?.email ?? order?.customer_email ?? '').trim();
  const lines = (order.items || [])
    .filter((item) => item && String(item.sku ?? '').trim())
    .map((item) => ({
      sku: String(item.sku).trim(),
      description: String(item.name ?? item.sku).trim(),
      qty: Number(item.qty_ordered ?? 0),
      unitPrice: Number(item.price ?? item.original_price ?? 0),
      amount: Number(item.row_total ?? 0),
    }))
    .filter((line) => line.qty > 0);

  if (lines.length === 0) {
    return { ok: false, error: 'No billable line items found for QuickBooks.' };
  }

  const customer = {
    displayName: customerDisplayName(order),
    email: email || undefined,
    firstName: String(bill?.firstname ?? order?.customer_firstname ?? '').trim() || undefined,
    lastName: String(bill?.lastname ?? order?.customer_lastname ?? '').trim() || undefined,
  };

  return {
    ok: true,
    payload: {
      incrementId,
      docNumber,
      docSuffix: docSuffix || null,
      txnDate: formatTxnDate(order.created_at),
      customerEmail: email || null,
      customer,
      lines,
      privateNote: `ACCS Stage order ${incrementId} (entity ${order.entity_id ?? ''})`,
      paymentMethod: String(order.payment?.method ?? order.payment?.additional_information?.[0] ?? '').trim() || null,
      total: Number(order.grand_total ?? 0),
    },
  };
}

function mapCanonicalToSalesReceipt(canonical, options = {}) {
  const order = accsOrderFromCanonical(canonical);
  return mapSalesReceiptPayload(order, options);
}

module.exports = {
  escapeQboQueryValue,
  buildQboDocNumber,
  mapSalesReceiptPayload,
  mapCanonicalToSalesReceipt,
};
