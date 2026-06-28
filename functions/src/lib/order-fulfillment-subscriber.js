const { parseCanonicalBody, accsOrderFromCanonical } = require('./canonical-order');
const { filterItemsBySupplier } = require('./order-suppliers');
const { getRoute, CC_ALWAYS } = require('./fulfillment-routing');
const { buildOrderAlertEmail } = require('./order-email');
const { sendMail } = require('./mailer');

function parseServiceBusBody(message) {
  if (message === null || message === undefined) {
    return null;
  }

  if (typeof message === 'object' && !Buffer.isBuffer(message)) {
    return message;
  }

  const text = Buffer.isBuffer(message) ? message.toString('utf8') : String(message);
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

async function sendSupplierFulfillmentEmail(canonical, supplierCode, context) {
  const parsed = parseCanonicalBody(canonical);
  if (!parsed.ok) {
    throw new Error(parsed.error);
  }

  const order = accsOrderFromCanonical(parsed.canonical);
  const items = filterItemsBySupplier(order.items, supplierCode);

  if (items.length === 0) {
    context.log('No %s lines in order %s — skipping email', supplierCode, parsed.canonical.incrementId);
    return { ok: true, skipped: true, reason: 'no_lines' };
  }

  const route = getRoute(supplierCode);
  if (!route) {
    throw new Error(`No email route configured for supplier ${supplierCode}.`);
  }

  const dispatchOrder = { ...order, items };
  const { subject, html, text } = buildOrderAlertEmail(dispatchOrder, route);
  const to = route.to;
  const cc = [...route.cc, ...CC_ALWAYS];

  const info = await sendMail({ to, cc, subject, html, text });
  context.log(
    '[%s] Email sent for order %s → %s (msgId=%s)',
    supplierCode,
    parsed.canonical.incrementId,
    to.join(', '),
    info.messageId
  );

    return {
      ok: true,
      skipped: false,
      supplier: supplierCode,
      increment_id: parsed.canonical.incrementId,
      item_count: items.length,
      message_id: info.messageId,
      result_message: `${supplierCode} notification sent for order ${parsed.canonical.incrementId}.`,
    };
}

module.exports = {
  parseServiceBusBody,
  sendSupplierFulfillmentEmail,
};
