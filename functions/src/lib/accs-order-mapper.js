const adobeCommerce = require('./adobe-commerce');

function sourceEnvironmentLabel() {
  const env = adobeCommerce.environment();
  return env === 'production' ? 'production' : 'stage';
}

function toDecimal(value) {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  const num = Number(value);
  return Number.isFinite(num) ? num : null;
}

function toInt(value) {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  const num = Number.parseInt(String(value), 10);
  return Number.isFinite(num) ? num : null;
}

function toBool(value) {
  return Boolean(Number(value) || value === true);
}

function parseDateTime(value) {
  const trimmed = String(value ?? '').trim();
  if (!trimmed) {
    return null;
  }

  const date = new Date(trimmed);
  return Number.isNaN(date.getTime()) ? null : date;
}

function streetLines(street) {
  if (Array.isArray(street)) {
    return {
      line1: String(street[0] ?? '').trim() || null,
      line2: String(street[1] ?? '').trim() || null,
    };
  }

  const single = String(street ?? '').trim();
  return { line1: single || null, line2: null };
}

function mapAddressFields(address, prefix) {
  if (!address || typeof address !== 'object') {
    return {};
  }

  const street = streetLines(address.street);

  return {
    [`${prefix}AddressId`]: toInt(address.entity_id ?? address.address_id),
    [`${prefix}FirstName`]: String(address.firstname ?? '').trim() || null,
    [`${prefix}LastName`]: String(address.lastname ?? '').trim() || null,
    [`${prefix}Company`]: String(address.company ?? '').trim() || null,
    [`${prefix}Street1`]: street.line1,
    [`${prefix}Street2`]: street.line2,
    [`${prefix}City`]: String(address.city ?? '').trim() || null,
    [`${prefix}Region`]: String(address.region ?? '').trim() || null,
    [`${prefix}RegionCode`]: String(address.region_code ?? '').trim() || null,
    [`${prefix}Postcode`]: String(address.postcode ?? '').trim() || null,
    [`${prefix}CountryId`]: String(address.country_id ?? '').trim() || null,
    [`${prefix}Telephone`]: String(address.telephone ?? '').trim() || null,
    [`${prefix}Email`]: String(address.email ?? '').trim() || null,
  };
}

function shippingAddress(order) {
  const assignments = order?.extension_attributes?.shipping_assignments;
  if (Array.isArray(assignments) && assignments.length > 0) {
    const address = assignments[0]?.shipping?.address;
    if (address && typeof address === 'object') {
      return address;
    }
  }

  return null;
}

function paymentMethod(order) {
  const payments = order?.payment;
  if (payments && typeof payments === 'object') {
    const method = String(payments.method ?? '').trim();
    if (method) {
      return method;
    }
  }

  const extension = order?.extension_attributes?.payment_additional_info;
  if (Array.isArray(extension) && extension.length > 0) {
    const method = String(extension[0]?.method ?? '').trim();
    if (method) {
      return method;
    }
  }

  return null;
}

function inferFulfillmentFromSku(sku) {
  const normalized = String(sku ?? '').trim().toUpperCase();
  if (normalized.startsWith('CPPC-')) {
    return 'CPPC';
  }

  return null;
}

function mapHeader(order) {
  const entityId = toInt(order.entity_id);
  const incrementId = String(order.increment_id ?? '').trim();
  if (!entityId || !incrementId) {
    return null;
  }

  const billing = order.billing_address ?? null;
  const shipping = shippingAddress(order);
  const sourceEnvironment = sourceEnvironmentLabel();

  return {
    AccsEntityId: entityId,
    IncrementId: incrementId,
    AccsState: String(order.state ?? '').trim() || null,
    OrderStatus: String(order.status ?? '').trim() || 'unknown',
    OrderCreatedAt: parseDateTime(order.created_at),
    OrderUpdatedAt: parseDateTime(order.updated_at),
    CustomerId: toInt(order.customer_id),
    CustomerEmail: String(order.customer_email ?? billing?.email ?? '').trim() || null,
    CustomerFirstName: String(order.customer_firstname ?? billing?.firstname ?? '').trim() || null,
    CustomerLastName: String(order.customer_lastname ?? billing?.lastname ?? '').trim() || null,
    CustomerGroupId: toInt(order.customer_group_id),
    CustomerIsGuest: toBool(order.customer_is_guest),
    StoreId: toInt(order.store_id),
    StoreName: String(order.store_name ?? '').trim() || null,
    OrderCurrencyCode: String(order.order_currency_code ?? '').trim() || null,
    BaseCurrencyCode: String(order.base_currency_code ?? '').trim() || null,
    Subtotal: toDecimal(order.subtotal),
    SubtotalInclTax: toDecimal(order.subtotal_incl_tax),
    ShippingAmount: toDecimal(order.shipping_amount),
    ShippingInclTax: toDecimal(order.shipping_incl_tax),
    ShippingDescription: String(order.shipping_description ?? '').trim() || null,
    ShippingTaxAmount: toDecimal(order.shipping_tax_amount),
    TaxAmount: toDecimal(order.tax_amount),
    DiscountAmount: toDecimal(order.discount_amount),
    GrandTotal: toDecimal(order.grand_total),
    TotalDue: toDecimal(order.total_due),
    TotalPaid: toDecimal(order.total_paid),
    TotalInvoiced: toDecimal(order.total_invoiced),
    TotalRefunded: toDecimal(order.total_refunded),
    TotalOnlineRefunded: toDecimal(order.total_online_refunded),
    Weight: toDecimal(order.weight),
    TotalQtyOrdered: toDecimal(order.total_qty_ordered),
    TotalItemCount: toInt(order.total_item_count),
    PaymentMethod: paymentMethod(order),
    QuoteId: toInt(order.quote_id),
    RemoteIp: String(order.remote_ip ?? '').trim() || null,
    IsVirtual: toBool(order.is_virtual),
    EmailSent: toBool(order.email_sent),
    SourceEnvironment: sourceEnvironment,
    RawPayloadJson: JSON.stringify(order),
    ...mapAddressFields(billing, 'Bill'),
    ...mapAddressFields(shipping, 'Ship'),
  };
}

function mapDetailLines(order) {
  const entityId = toInt(order.entity_id);
  const sourceEnvironment = sourceEnvironmentLabel();
  const items = Array.isArray(order.items) ? order.items : [];
  const lines = [];

  items.forEach((item, index) => {
    if (!item || typeof item !== 'object') {
      return;
    }

    const itemId = toInt(item.item_id);
    const sku = String(item.sku ?? '').trim();
    if (!itemId || !sku) {
      return;
    }

    const fulfillmentAttr =
      String(item.fulfillment ?? '').trim() || inferFulfillmentFromSku(sku);

    lines.push({
      AccsItemId: itemId,
      AccsOrderEntityId: entityId,
      LineNumber: index + 1,
      SKU: sku,
      ProductName: String(item.name ?? '').trim() || null,
      ProductId: toInt(item.product_id),
      ProductType: String(item.product_type ?? '').trim() || null,
      Description: String(item.description ?? item.extension_attributes?.description ?? '').trim() || null,
      QtyOrdered: toDecimal(item.qty_ordered) ?? 0,
      QtyShipped: toDecimal(item.qty_shipped) ?? 0,
      QtyInvoiced: toDecimal(item.qty_invoiced) ?? 0,
      QtyCanceled: toDecimal(item.qty_canceled) ?? 0,
      QtyRefunded: toDecimal(item.qty_refunded) ?? 0,
      QtyReturned: toDecimal(item.qty_returned) ?? 0,
      OriginalPrice: toDecimal(item.original_price),
      UnitPrice: toDecimal(item.price),
      UnitPriceInclTax: toDecimal(item.price_incl_tax),
      RowTotal: toDecimal(item.row_total),
      RowTotalInclTax: toDecimal(item.row_total_incl_tax),
      RowInvoiced: toDecimal(item.row_invoiced),
      DiscountAmount: toDecimal(item.discount_amount),
      DiscountPercent: toDecimal(item.discount_percent),
      TaxAmount: toDecimal(item.tax_amount),
      TaxPercent: toDecimal(item.tax_percent),
      BaseCost: toDecimal(item.base_cost),
      Weight: toDecimal(item.weight),
      RowWeight: toDecimal(item.row_weight),
      IsVirtual: toBool(item.is_virtual),
      IsQtyDecimal: toBool(item.is_qty_decimal),
      FreeShipping: toBool(item.free_shipping),
      FulfillmentAttr: fulfillmentAttr,
      SupplierCode: fulfillmentAttr,
      ParentAccsItemId: toInt(item.parent_item_id),
      StoreId: toInt(item.store_id),
      ItemCreatedAt: parseDateTime(item.created_at),
      ItemUpdatedAt: parseDateTime(item.updated_at),
      SourceEnvironment: sourceEnvironment,
    });
  });

  return lines;
}

function orderUpdatedKey(order) {
  const updated = parseDateTime(order.updated_at);
  if (updated) {
    return updated.toISOString();
  }

  return String(order.status ?? '') + '|' + String(order.state ?? '');
}

module.exports = {
  sourceEnvironmentLabel,
  mapHeader,
  mapDetailLines,
  orderUpdatedKey,
  parseDateTime,
};
