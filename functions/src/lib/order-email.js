/**
 * Formats an ACCS order object into an HTML email alert.
 */

/**
 * @param {object} order  - ACCS order (may be a subset of items for one fulfillment group)
 * @param {object} [route] - Fulfillment route from fulfillment-routing.js (optional)
 * @returns {{ subject: string, html: string, text: string }}
 */
function buildOrderAlertEmail(order, route = null) {
    const orderId    = order.increment_id  || order.entity_id || 'Unknown';
    const status     = order.status        || 'unknown';
    const total      = order.grand_total   != null ? `$${parseFloat(order.grand_total).toFixed(2)}` : '—';
    const subtotal   = order.subtotal      != null ? `$${parseFloat(order.subtotal).toFixed(2)}` : '—';
    const shipping   = order.shipping_amount != null ? `$${parseFloat(order.shipping_amount).toFixed(2)}` : '—';
    const tax        = order.tax_amount    != null ? `$${parseFloat(order.tax_amount).toFixed(2)}` : '—';
    const createdAt  = order.created_at    || new Date().toISOString();
    const currency   = order.order_currency_code || 'USD';

    // Customer info
    const customer = [
        order.customer_firstname, order.customer_lastname,
    ].filter(Boolean).join(' ') || order.customer_email || 'Guest';
    const email    = order.customer_email || '—';

    // Shipping address
    const addr     = order.billing_address || {};
    const addrLine = [
        [addr.firstname, addr.lastname].filter(Boolean).join(' '),
        (addr.street || []).join(', '),
        [addr.city, addr.region, addr.postcode].filter(Boolean).join(', '),
        addr.country_id,
    ].filter(Boolean).join('<br>') || '—';

    // Line items
    const items = (order.items || []).filter(item => !item.parent_item_id);
    const itemRows = items.map(item => {
        const fulfillment = item.fulfillment ?? null;
        const fulfillmentBadge = fulfillment
            ? `<br><span style="display:inline-block;margin-top:3px;font-size:10px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:#6b7280;background:#f3f4f6;border-radius:3px;padding:1px 5px;">${escHtml(fulfillment)}</span>`
            : '';
        return `
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb;">${escHtml(item.sku || '—')}${fulfillmentBadge}</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb;">${escHtml(item.name || '—')}</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb; text-align: center;">${parseFloat(item.qty_ordered || 0).toFixed(0)}</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb; text-align: right;">$${parseFloat(item.price || 0).toFixed(2)}</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb; text-align: right;">$${parseFloat(item.row_total || 0).toFixed(2)}</td>
        </tr>
    `;
    }).join('');

    const routeLabel = route?.label || null;
    const routeCode  = route?.code  || null;
    const subject = routeLabel
        ? `[ACCS / ${routeLabel}] Order #${orderId} — ${total} — ${customer}`
        : `[ACCS] New Order #${orderId} — ${total} — ${customer}`;

    const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Order Alert</title>
</head>
<body style="margin: 0; padding: 0; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background: #f3f4f6; padding: 32px 0;">
  <tr>
    <td align="center">
      <table width="620" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 8px; overflow: hidden;">

        <!-- Header -->
        <tr>
          <td style="background: #111827; padding: 24px 32px;">
            <p style="margin: 0; color: #9ca3af; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase;">NutraAxis Operations</p>
            <h1 style="margin: 4px 0 0; color: #ffffff; font-size: 20px; font-weight: 600;">New Order Alert${routeLabel ? ` — ${escHtml(routeLabel)}` : ''}</h1>
          </td>
        </tr>

        <!-- Status banner -->
        <tr>
          <td style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 12px 32px;">
            <p style="margin: 0; font-size: 13px; color: #15803d; font-weight: 500;">
              Order <strong>#${escHtml(orderId)}</strong> placed &mdash; Status: <strong>${escHtml(status)}</strong>
              ${routeCode ? `&mdash; Fulfillment: <strong>${escHtml(routeCode)}</strong>` : ''}
            </p>
          </td>
        </tr>

        <!-- Order summary -->
        <tr>
          <td style="padding: 28px 32px 0;">
            <h2 style="margin: 0 0 16px; font-size: 13px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: #6b7280;">Order Summary</h2>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td width="50%" style="padding-bottom: 12px; vertical-align: top;">
                  <p style="margin: 0; font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Order #</p>
                  <p style="margin: 2px 0 0; font-size: 15px; font-weight: 600; color: #111827;">#${escHtml(orderId)}</p>
                </td>
                <td width="50%" style="padding-bottom: 12px; vertical-align: top;">
                  <p style="margin: 0; font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Order Total</p>
                  <p style="margin: 2px 0 0; font-size: 15px; font-weight: 600; color: #111827;">${total} ${currency}</p>
                </td>
              </tr>
              <tr>
                <td width="50%" style="padding-bottom: 12px; vertical-align: top;">
                  <p style="margin: 0; font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Customer</p>
                  <p style="margin: 2px 0 0; font-size: 13px; color: #374151;">${escHtml(customer)}</p>
                  <p style="margin: 1px 0 0; font-size: 12px; color: #6b7280;">${escHtml(email)}</p>
                </td>
                <td width="50%" style="padding-bottom: 12px; vertical-align: top;">
                  <p style="margin: 0; font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Date</p>
                  <p style="margin: 2px 0 0; font-size: 13px; color: #374151;">${escHtml(createdAt.replace('T', ' ').substring(0, 19))} UTC</p>
                </td>
              </tr>
              <tr>
                <td colspan="2" style="padding-bottom: 12px; vertical-align: top;">
                  <p style="margin: 0; font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Billing Address</p>
                  <p style="margin: 2px 0 0; font-size: 13px; color: #374151; line-height: 1.5;">${addrLine}</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Line items -->
        ${items.length > 0 ? `
        <tr>
          <td style="padding: 20px 32px 0;">
            <h2 style="margin: 0 0 12px; font-size: 13px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: #6b7280;">Line Items</h2>
            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden;">
              <thead>
                <tr style="background: #f9fafb;">
                  <th style="padding: 8px 12px; font-size: 11px; font-weight: 600; text-align: left; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">SKU</th>
                  <th style="padding: 8px 12px; font-size: 11px; font-weight: 600; text-align: left; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Product</th>
                  <th style="padding: 8px 12px; font-size: 11px; font-weight: 600; text-align: center; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Qty</th>
                  <th style="padding: 8px 12px; font-size: 11px; font-weight: 600; text-align: right; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Unit</th>
                  <th style="padding: 8px 12px; font-size: 11px; font-weight: 600; text-align: right; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Total</th>
                </tr>
              </thead>
              <tbody>
                ${itemRows}
              </tbody>
            </table>
          </td>
        </tr>` : ''}

        <!-- Totals -->
        <tr>
          <td style="padding: 20px 32px 0;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="text-align: right; padding: 4px 0; font-size: 13px; color: #6b7280;">Subtotal</td>
                <td style="text-align: right; padding: 4px 0; font-size: 13px; color: #374151; padding-left: 32px; width: 100px;">${subtotal}</td>
              </tr>
              <tr>
                <td style="text-align: right; padding: 4px 0; font-size: 13px; color: #6b7280;">Shipping</td>
                <td style="text-align: right; padding: 4px 0; font-size: 13px; color: #374151;">${shipping}</td>
              </tr>
              <tr>
                <td style="text-align: right; padding: 4px 0; font-size: 13px; color: #6b7280;">Tax</td>
                <td style="text-align: right; padding: 4px 0; font-size: 13px; color: #374151;">${tax}</td>
              </tr>
              <tr>
                <td style="text-align: right; padding: 8px 0 0; font-size: 14px; font-weight: 700; color: #111827; border-top: 1px solid #e5e7eb;">Grand Total</td>
                <td style="text-align: right; padding: 8px 0 0; font-size: 14px; font-weight: 700; color: #111827; border-top: 1px solid #e5e7eb;">${total}</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding: 28px 32px; border-top: 1px solid #f3f4f6; margin-top: 24px;">
            <p style="margin: 0; font-size: 11px; color: #9ca3af;">
              This alert was sent automatically by the NutraAxis Azure Function App when an order was completed in Adobe Commerce (ACCS &mdash; stage environment).
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>`;

    // Plain text fallback
    const text = [
        `New Order Alert — NutraAxis Operations`,
        ``,
        `Order #${orderId} | Status: ${status}`,
        `Customer: ${customer} <${email}>`,
        `Date: ${createdAt}`,
        ``,
        `--- Totals ---`,
        `Subtotal : ${subtotal}`,
        `Shipping : ${shipping}`,
        `Tax      : ${tax}`,
        `Total    : ${total} ${currency}`,
        ``,
        `--- Items ---`,
        ...items.map(i => `  ${i.sku}${i.fulfillment ? ` [${i.fulfillment}]` : ''} — ${i.name} × ${parseFloat(i.qty_ordered||0).toFixed(0)} @ $${parseFloat(i.price||0).toFixed(2)}`),
    ].join('\n');

    return { subject, html, text };
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

module.exports = { buildOrderAlertEmail };
