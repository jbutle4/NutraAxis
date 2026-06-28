/**
 * Map an ACCS (Adobe Commerce) order into a Jazz OMS order import payload.
 *
 * Jazz POST /api/v1/order/import expects:
 *   order_number, order_date, source_code, customer, shipto[], shipto[].detail_set[]
 */

const DEFAULT_SOURCE_CODE = 'WEB_2026';

function envValue(key, fallback = '') {
  const value = process.env[key];
  if (value !== undefined && String(value).trim() !== '') {
    return String(value).trim();
  }

  return fallback;
}

function streetLines(street) {
  if (Array.isArray(street)) {
    return {
      line1: String(street[0] ?? '').trim(),
      line2: String(street[1] ?? '').trim(),
    };
  }

  const single = String(street ?? '').trim();
  return { line1: single, line2: '' };
}

function shippingAddress(order) {
  const billing = order?.billing_address ?? null;
  let shipping = null;

  const assignments = order?.extension_attributes?.shipping_assignments;
  if (Array.isArray(assignments) && assignments.length > 0) {
    const address = assignments[0]?.shipping?.address;
    if (address && typeof address === 'object') {
      shipping = address;
    }
  }

  if (!shipping) {
    return billing;
  }

  if (!billing) {
    return shipping;
  }

  return {
    ...billing,
    ...shipping,
    street: shipping.street ?? billing.street,
    city: shipping.city || billing.city,
    region: shipping.region || billing.region,
    region_code: shipping.region_code || billing.region_code,
    region_id: shipping.region_id ?? billing.region_id,
    postcode: shipping.postcode || billing.postcode,
    country_id: shipping.country_id || billing.country_id,
    telephone: shipping.telephone || billing.telephone,
    email: shipping.email || billing.email,
    firstname: shipping.firstname || billing.firstname,
    lastname: shipping.lastname || billing.lastname,
  };
}

function formatJazzDateTime(value) {
  const trimmed = String(value ?? '').trim();
  if (!trimmed) {
    return new Date().toISOString().replace('T', ' ').slice(0, 19);
  }

  const normalized = trimmed.includes('T') ? trimmed : trimmed.replace(' ', 'T');
  const date = new Date(normalized.endsWith('Z') ? normalized : `${normalized}Z`);
  if (Number.isNaN(date.getTime())) {
    return trimmed.slice(0, 19);
  }

  return date.toISOString().replace('T', ' ').slice(0, 19);
}

function mapContact(address, order) {
  const street = streetLines(address?.street);
  const firstName = String(address?.firstname ?? order?.customer_firstname ?? '').trim();
  const lastName = String(address?.lastname ?? order?.customer_lastname ?? '').trim();
  const company = String(address?.company ?? '').trim();
  const zipcode = String(address?.postcode ?? address?.zipcode ?? '').trim();

  return {
    first_name: firstName || 'Customer',
    last_name: lastName || 'Unknown',
    company: company || undefined,
    address1: street.line1 || '',
    address2: street.line2 || undefined,
    city: String(address?.city ?? '').trim(),
    state: String(address?.region_code ?? address?.region ?? '').trim(),
    zipcode,
    country: String(address?.country_id ?? 'US').trim() || 'US',
    phone: String(address?.telephone ?? '').trim() || undefined,
    email: String(address?.email ?? order?.customer_email ?? '').trim() || undefined,
  };
}

function mapShipToContact(contact) {
  const {
    first_name: firstName,
    last_name: lastName,
    company,
    address1,
    address2,
    city,
    state,
    zipcode,
    country,
    phone,
    email,
  } = contact;

  const shipto = {
    first_name: firstName,
    last_name: lastName,
    company: company || undefined,
    phone: phone || undefined,
    email: email || undefined,
    address: {
      address1,
      address2: address2 || undefined,
      city,
      state,
      zipcode,
      country,
    },
  };

  if (company) {
    shipto.company = company;
  }

  return shipto;
}

function jazzOrderTestSuffix(options = {}) {
  const explicitSuffix = String(options.testSuffix ?? '').trim();
  if (explicitSuffix) {
    return explicitSuffix;
  }

  return envValue('JAZZ_ORDER_TEST_SUFFIX', 'NSB');
}

function buildTestOrderNumber(sourceIncrementId, options = {}) {
  const base = String(sourceIncrementId ?? '').trim();
  if (!base) {
    return null;
  }

  const suffix = jazzOrderTestSuffix(options);
  if (suffix) {
    return `${base}-${suffix}`;
  }

  const seq = options.testSeq;
  if (seq !== undefined && seq !== null && String(seq).trim() !== '') {
    const padded = String(seq).trim().padStart(3, '0');
    return `${base}-TEST-${padded}`;
  }

  const timestamp = Date.now().toString().slice(-6);
  return `${base}-TEST-${timestamp}`;
}

function mapLineItem(item, index, options = {}) {
  const sku = String(options.forceSku ?? item.sku ?? '').trim();
  const qty = Number(item.qty_ordered ?? 0);
  const price = Number(item.price ?? item.original_price ?? 0);

  if (!sku) {
    return null;
  }

  if (!Number.isFinite(qty) || qty <= 0) {
    return null;
  }

  return {
    line_number: index + 1,
    sku,
    qty_ordered: qty,
    current_price: Number.isFinite(price) ? price : 0,
  };
}

/**
 * @param {object} order - ACCS order payload
 * @param {object} options
 * @param {string} [options.testSuffix]
 * @param {string|number} [options.testSeq]
 * @param {string} [options.forceSku] - replace every line SKU (useful when ACCS SKU is not in Jazz UAT)
 * @param {object[]} [options.items] - pre-filtered/enriched line items
 */
function mapAccsOrderToJazzImport(order, options = {}) {
  const incrementId = String(order.increment_id ?? '').trim();
  const jazzOrderNumber = buildTestOrderNumber(incrementId, options);
  if (!jazzOrderNumber) {
    return { ok: false, error: 'ACCS order is missing increment_id.', payload: null, summary: null };
  }

  const shipAddress = shippingAddress(order);
  if (!shipAddress) {
    return { ok: false, error: 'ACCS order is missing shipping/billing address.', payload: null, summary: null };
  }

  const items = Array.isArray(options.items) ? options.items : (order.items ?? []);
  const mappedLines = [];

  items.forEach((item, index) => {
    const mapped = mapLineItem(item, mappedLines.length, options);
    if (mapped) {
      mappedLines.push(mapped);
    }
  });

  if (mappedLines.length === 0) {
    return { ok: false, error: 'No shippable line items found for Jazz import.', payload: null, summary: null };
  }

  const customer = mapContact(shipAddress, order);
  const shipto = mapShipToContact(customer);
  shipto.detail_set = mappedLines;

  const payload = {
    order_number: jazzOrderNumber,
    order_date: formatJazzDateTime(order.created_at),
    source_code: envValue('JAZZ_ORDER_SOURCE_CODE', DEFAULT_SOURCE_CODE),
    customer,
    shipto: [shipto],
  };

  // Use the same suffixed identity for po_number so Jazz does not dedupe against
  // the legacy integration submitting the raw ACCS increment_id.
  payload.po_number = jazzOrderNumber;

  return {
    ok: true,
    error: null,
    payload,
    summary: {
      sourceIncrementId: incrementId,
      jazzOrderNumber,
      lineCount: mappedLines.length,
      skus: mappedLines.map((line) => line.sku),
      customerEmail: customer.email ?? null,
      shipTo: {
        city: customer.city,
        state: customer.state,
        country: customer.country,
        zipcode: customer.zipcode,
      },
    },
  };
}

module.exports = {
  jazzOrderTestSuffix,
  buildTestOrderNumber,
  mapAccsOrderToJazzImport,
  formatJazzDateTime,
};
