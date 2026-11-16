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

function roundMoney(value) {
  return Math.round(Number(value || 0) * 100) / 100;
}

function resolveShippedQty(fields) {
  let qty = Number(fields.qtyShipped ?? fields.QtyShipped ?? 0);
  if (!(qty > 0)) {
    qty = Number(fields.qtyInvoiced ?? fields.QtyInvoiced ?? 0);
  }
  if (!(qty > 0)) {
    qty = Number(fields.qtyOrdered ?? fields.QtyOrdered ?? fields.qty_ordered ?? 0);
  }
  return qty > 0 ? qty : 0;
}

/**
 * Map one ACCS line to a QBO Sales Receipt product row.
 * Keeps qty for inventory even when rowTotal is 0 (100% discount).
 */
function mapProductLine({
  sku,
  description,
  qty,
  originalPrice,
  unitPrice,
  rowTotal,
  discountAmount,
  taxAmount,
  detailId = null,
}) {
  const skuCode = String(sku || '').trim();
  const qtySafe = Number(qty || 0);
  if (!skuCode || !(qtySafe > 0)) {
    return null;
  }

  const catalogUnitPrice = roundMoney(
    Number(originalPrice ?? 0) > 0
      ? originalPrice
      : Number(unitPrice ?? 0)
  );
  const netRowTotal = roundMoney(rowTotal ?? 0);
  let lineDiscount = roundMoney(Math.abs(Number(discountAmount ?? 0)));

  const grossFromCatalog = roundMoney(catalogUnitPrice * qtySafe);
  if (!(lineDiscount > 0) && grossFromCatalog > netRowTotal + 0.0001) {
    lineDiscount = roundMoney(grossFromCatalog - netRowTotal);
  }

  let listUnitPrice = catalogUnitPrice;
  if (!(listUnitPrice > 0)) {
    if (qtySafe > 0 && netRowTotal > 0) {
      listUnitPrice = roundMoney(netRowTotal / qtySafe);
    } else if (qtySafe > 0 && lineDiscount > 0) {
      listUnitPrice = roundMoney((netRowTotal + lineDiscount) / qtySafe);
    }
  }

  if (lineDiscount > grossFromCatalog + 0.0001) {
    lineDiscount = grossFromCatalog;
  }

  return {
    sku: skuCode,
    description: String(description || skuCode).trim(),
    qty: qtySafe,
    unitPrice: listUnitPrice,
    discountAmount: lineDiscount,
    rowTotal: netRowTotal,
    taxAmount: roundMoney(taxAmount ?? 0),
    detail_id: detailId,
  };
}

function mapProductLineFromDbRow(row) {
  const qty = resolveShippedQty(row);
  return mapProductLine({
    sku: row.SKU,
    description: row.ProductName ?? row.SKU,
    qty,
    originalPrice: row.OriginalPrice,
    unitPrice: row.UnitPrice,
    rowTotal: row.RowTotal,
    discountAmount: row.DiscountAmount,
    taxAmount: row.TaxAmount,
    detailId: Number(row.AccsSalesOrderDetailID || 0) || null,
  });
}

function mapProductLineFromApiItem(item) {
  return mapProductLine({
    sku: item.sku,
    description: item.name ?? item.sku,
    qty: resolveShippedQty({
      qtyShipped: item.qty_shipped,
      qtyInvoiced: item.qty_invoiced,
      qtyOrdered: item.qty_ordered,
    }),
    originalPrice: item.original_price,
    unitPrice: item.price,
    rowTotal: item.row_total,
    discountAmount: item.discount_amount,
    taxAmount: item.tax_amount,
    detailId: Number(item.item_id || 0) || null,
  });
}

function mapOrderCharges({
  subtotal,
  shippingAmount,
  shippingDescription,
  taxAmount,
  headerDiscountAmount,
  grandTotal,
  lineDiscountTotal,
}) {
  const shipping = roundMoney(shippingAmount ?? 0);
  const tax = roundMoney(taxAmount ?? 0);
  const merchandise = roundMoney(subtotal ?? 0);
  const grand = roundMoney(grandTotal ?? 0);
  const headerDiscount = roundMoney(Math.abs(Number(headerDiscountAmount ?? 0)));
  const lineDiscounts = roundMoney(lineDiscountTotal ?? 0);
  const orderLevelDiscount = roundMoney(Math.max(0, headerDiscount - lineDiscounts));

  const computedGrand = roundMoney(merchandise - orderLevelDiscount + shipping + tax);

  return {
    subtotal: merchandise,
    shipping: {
      amount: shipping,
      description: String(shippingDescription || 'Shipping').trim() || 'Shipping',
    },
    tax: {
      amount: tax,
    },
    orderLevelDiscount,
    lineDiscountTotal: lineDiscounts,
    headerDiscountTotal: headerDiscount,
    grandTotal: grand,
    computedGrandTotal: computedGrand,
    grandTotalDelta: roundMoney(grand - computedGrand),
  };
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

function buildSalesReceiptPayload({
  incrementId,
  headerId,
  docNumber,
  docSuffix,
  txnDate,
  email,
  customer,
  lines,
  charges,
  privateNote,
  paymentMethod,
}) {
  if (!lines.length) {
    return { ok: false, error: 'No billable line items found for QuickBooks.' };
  }

  return {
    ok: true,
    payload: {
      incrementId,
      headerId: headerId ?? null,
      docNumber,
      docSuffix: docSuffix || null,
      txnDate,
      customerEmail: email || null,
      customer,
      lines,
      shipping: charges.shipping,
      tax: charges.tax,
      orderLevelDiscount: charges.orderLevelDiscount,
      totals: {
        merchandiseSubtotal: charges.subtotal,
        lineDiscountTotal: charges.lineDiscountTotal,
        orderLevelDiscount: charges.orderLevelDiscount,
        shipping: charges.shipping.amount,
        tax: charges.tax.amount,
        grandTotal: charges.grandTotal,
        computedGrandTotal: charges.computedGrandTotal,
        grandTotalDelta: charges.grandTotalDelta,
      },
      privateNote,
      paymentMethod: paymentMethod || null,
      total: charges.grandTotal,
    },
  };
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
    .map((item) => mapProductLineFromApiItem(item))
    .filter(Boolean);

  const lineDiscountTotal = roundMoney(lines.reduce((sum, line) => sum + line.discountAmount, 0));
  const merchandiseSubtotal = roundMoney(lines.reduce((sum, line) => sum + line.rowTotal, 0));
  const charges = mapOrderCharges({
    subtotal: merchandiseSubtotal,
    shippingAmount: order.shipping_amount,
    shippingDescription: order.shipping_description,
    taxAmount: order.tax_amount,
    headerDiscountAmount: order.discount_amount,
    grandTotal: order.grand_total,
    lineDiscountTotal,
  });

  const customer = {
    displayName: customerDisplayName(order),
    email: email || undefined,
    firstName: String(bill?.firstname ?? order?.customer_firstname ?? '').trim() || undefined,
    lastName: String(bill?.lastname ?? order?.customer_lastname ?? '').trim() || undefined,
  };

  return buildSalesReceiptPayload({
    incrementId,
    headerId: Number(order.entity_id || 0) || null,
    docNumber,
    docSuffix: docSuffix || null,
    txnDate: formatTxnDate(order.created_at),
    email,
    customer,
    lines,
    charges,
    privateNote: `ACCS Stage order ${incrementId} (entity ${order.entity_id ?? ''})`,
    paymentMethod: String(order.payment?.method ?? order.payment?.additional_information?.[0] ?? '').trim() || null,
  });
}

function readHeaderField(header, name) {
  const prefixed = header[`Header${name}`];
  if (prefixed !== undefined && prefixed !== null) {
    return prefixed;
  }
  return header[name];
}

function mapAccsDbOrderToSalesReceipt(header, detailRows, options = {}) {
  const incrementId = String(header.IncrementId ?? '').trim();
  if (!incrementId) {
    return { ok: false, error: 'ACCS order is missing IncrementId.' };
  }

  const docSuffix = String(options.docSuffix ?? '').trim();
  const docNumber = docSuffix
    ? buildQboDocNumber(incrementId, docSuffix)
    : incrementId;

  const txnDate = options.useTodayTxnDate
    ? new Date().toISOString().slice(0, 10)
    : formatTxnDate(header.OrderCreatedAt);

  const email = String(header.CustomerEmail ?? header.BillEmail ?? '').trim();
  const firstName = String(header.CustomerFirstName ?? header.BillFirstName ?? '').trim();
  const lastName = String(header.CustomerLastName ?? header.BillLastName ?? '').trim();
  const displayName = `${firstName} ${lastName}`.trim() || email || `ACCS Customer ${header.AccsEntityId ?? header.AccsSalesOrderHeaderID}`;

  const lines = (detailRows || [])
    .map((row) => mapProductLineFromDbRow(row))
    .filter(Boolean);

  const lineDiscountTotal = roundMoney(lines.reduce((sum, line) => sum + line.discountAmount, 0));
  const merchandiseSubtotal = roundMoney(lines.reduce((sum, line) => sum + line.rowTotal, 0));
  const charges = mapOrderCharges({
    subtotal: merchandiseSubtotal,
    shippingAmount: readHeaderField(header, 'ShippingAmount'),
    shippingDescription: readHeaderField(header, 'ShippingDescription'),
    taxAmount: readHeaderField(header, 'TaxAmount'),
    headerDiscountAmount: readHeaderField(header, 'DiscountAmount'),
    grandTotal: readHeaderField(header, 'GrandTotal') ?? readHeaderField(header, 'TotalInvoiced'),
    lineDiscountTotal,
  });

  const sourceLabel = String(header.SourceEnvironment || 'production').toLowerCase() === 'production'
    ? 'Production'
    : 'Stage';

  return buildSalesReceiptPayload({
    incrementId,
    headerId: Number(header.AccsSalesOrderHeaderID || 0),
    docNumber,
    docSuffix: docSuffix || null,
    txnDate,
    email,
    customer: {
      displayName,
      email: email || undefined,
      firstName: firstName || undefined,
      lastName: lastName || undefined,
    },
    lines,
    charges,
    privateNote: `ACCS ${sourceLabel} order ${incrementId} (header ${header.AccsSalesOrderHeaderID ?? ''})${options.useTodayTxnDate ? ' — cutover txn date' : ''}`,
    paymentMethod: readHeaderField(header, 'PaymentMethod') ?? null,
  });
}

function mapCanonicalToSalesReceipt(canonical, options = {}) {
  const order = accsOrderFromCanonical(canonical);
  return mapSalesReceiptPayload(order, options);
}

module.exports = {
  escapeQboQueryValue,
  buildQboDocNumber,
  roundMoney,
  resolveShippedQty,
  mapProductLine,
  mapProductLineFromDbRow,
  mapProductLineFromApiItem,
  mapOrderCharges,
  mapSalesReceiptPayload,
  mapAccsDbOrderToSalesReceipt,
  mapCanonicalToSalesReceipt,
};
