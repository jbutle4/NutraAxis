/**
 * ACCS Stage test order creation — customer cart checkout flow.
 *
 * Verified flow (Stage):
 *   POST /customers/{customerId}/carts
 *   POST /carts/{cartId}/items
 *   POST /carts/{cartId}/shipping-information
 *   POST /carts/{cartId}/billing-address
 *   PUT  /carts/{cartId}/selected-payment-method
 *   PUT  /carts/{cartId}/order
 */

const adobeCommerce = require('./adobe-commerce');

const STAGE_ENV = 'stage';
const REFERENCE_ORDER_ENTITY_ID = 93;
const DEFAULT_CUSTOMER_ID = 4;
const DEFAULT_EMAIL = 'jbutle4@icloud.com';
const PAYMENT_METHOD = 'checkmo';
const SHIPPING_CARRIER = 'tablerate_zone';
const SHIPPING_METHOD = 'tablerate_zone_expedited_3day';

const DEFAULT_ADDRESS = {
  firstname: 'Joe',
  lastname: 'Butler',
  street: ['10405 Mallory Dr'],
  city: 'Frisco',
  region: 'Texas',
  region_code: 'TX',
  region_id: 57,
  postcode: '75035',
  country_id: 'US',
  telephone: '7542101723',
  email: DEFAULT_EMAIL,
};

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function shuffle(items) {
  const copy = [...items];
  for (let i = copy.length - 1; i > 0; i -= 1) {
    const j = Math.floor(Math.random() * (i + 1));
    [copy[i], copy[j]] = [copy[j], copy[i]];
  }
  return copy;
}

function pickRandom(items, count) {
  if (items.length <= count) {
    return items.slice(0, count);
  }
  return shuffle(items).slice(0, count);
}

async function fetchCatalogSkus(options = {}) {
  const envName = adobeCommerce.resolveEnvironmentName(options.environment || STAGE_ENV);
  const pageSize = 100;
  let currentPage = 1;
  const skus = [];
  const maxPages = options.maxPages || 50;

  while (currentPage <= maxPages) {
    const query = {
      'searchCriteria[pageSize]': String(pageSize),
      'searchCriteria[currentPage]': String(currentPage),
      'searchCriteria[filter_groups][0][filters][0][field]': 'status',
      'searchCriteria[filter_groups][0][filters][0][value]': '1',
      'searchCriteria[filter_groups][0][filters][0][condition_type]': 'eq',
      'searchCriteria[sortOrders][0][field]': 'sku',
      'searchCriteria[sortOrders][0][direction]': 'ASC',
    };

    const result = await adobeCommerce.apiRequest('GET', '/products', query, null, { environment: envName });
    if (!result.ok) {
      return { ok: false, error: result.error, skus: [] };
    }

    const items = result.data?.items || [];
    for (const product of items) {
      const sku = String(product.sku || '').trim();
      if (sku) {
        skus.push(sku);
      }
    }

    if (items.length === 0 || items.length < pageSize) {
      break;
    }
    currentPage += 1;
  }

  return { ok: true, error: null, skus };
}

function normalizeAddressFromOrder(order) {
  const shipping = order?.extension_attributes?.shipping_assignments?.[0]?.shipping?.address;
  const billing = order?.billing_address;
  const base = shipping || billing;

  if (!base) {
    return { ...DEFAULT_ADDRESS };
  }

  return {
    firstname: base.firstname || DEFAULT_ADDRESS.firstname,
    lastname: base.lastname || DEFAULT_ADDRESS.lastname,
    street: Array.isArray(base.street) ? base.street : [base.street].filter(Boolean),
    city: base.city || DEFAULT_ADDRESS.city,
    region: base.region || DEFAULT_ADDRESS.region,
    region_code: base.region_code || DEFAULT_ADDRESS.region_code,
    region_id: base.region_id ?? DEFAULT_ADDRESS.region_id,
    postcode: base.postcode || DEFAULT_ADDRESS.postcode,
    country_id: base.country_id || DEFAULT_ADDRESS.country_id,
    telephone: base.telephone || DEFAULT_ADDRESS.telephone,
    email: base.email || order?.customer_email || DEFAULT_EMAIL,
  };
}

async function loadReferenceConfig(options = {}) {
  const environment = options.environment || STAGE_ENV;
  const customerId = DEFAULT_CUSTOMER_ID;
  const email = DEFAULT_EMAIL;

  let address = { ...DEFAULT_ADDRESS };
  let shippingCarrier = SHIPPING_CARRIER;
  let shippingMethod = SHIPPING_METHOD;
  let paymentMethod = PAYMENT_METHOD;
  let referenceOrder = null;

  const orderResult = await adobeCommerce.fetchOrderByEntityId(
    options.referenceEntityId || REFERENCE_ORDER_ENTITY_ID,
    { environment }
  );

  if (orderResult.ok && orderResult.order) {
    address = normalizeAddressFromOrder(orderResult.order);
    paymentMethod = orderResult.order.payment?.method || PAYMENT_METHOD;
    referenceOrder = {
      entity_id: orderResult.order.entity_id,
      increment_id: orderResult.order.increment_id,
    };
  }

  return {
    customerId,
    email,
    address,
    shippingCarrier,
    shippingMethod,
    paymentMethod,
    referenceOrder,
  };
}

async function createCustomerCart(customerId, options = {}) {
  const result = await adobeCommerce.apiRequest(
    'POST',
    `/customers/${customerId}/carts`,
    null,
    {},
    { environment: options.environment || STAGE_ENV }
  );

  if (!result.ok) {
    return { ok: false, error: result.error, cartId: null };
  }

  const cartId = String(result.data ?? '').trim();
  if (!cartId) {
    return { ok: false, error: 'ACCS did not return a cart ID.', cartId: null };
  }

  return { ok: true, error: null, cartId };
}

async function addCartItem(cartId, sku, qty, options = {}) {
  return adobeCommerce.apiRequest(
    'POST',
    `/carts/${cartId}/items`,
    null,
    { cartItem: { sku, qty, quote_id: cartId } },
    { environment: options.environment || STAGE_ENV }
  );
}

async function setShippingInformation(cartId, config, options = {}) {
  const addr = config.address;
  return adobeCommerce.apiRequest(
    'POST',
    `/carts/${cartId}/shipping-information`,
    null,
    {
      addressInformation: {
        shipping_address: { ...addr, same_as_billing: 1 },
        billing_address: addr,
        shipping_carrier_code: config.shippingCarrier,
        shipping_method_code: config.shippingMethod,
      },
    },
    { environment: options.environment || STAGE_ENV }
  );
}

async function setBillingAddress(cartId, address, options = {}) {
  return adobeCommerce.apiRequest(
    'POST',
    `/carts/${cartId}/billing-address`,
    null,
    { address },
    { environment: options.environment || STAGE_ENV }
  );
}

async function setPaymentMethod(cartId, paymentMethod, options = {}) {
  return adobeCommerce.apiRequest(
    'PUT',
    `/carts/${cartId}/selected-payment-method`,
    null,
    { method: { method: paymentMethod } },
    { environment: options.environment || STAGE_ENV }
  );
}

async function placeOrder(cartId, options = {}) {
  return adobeCommerce.apiRequest(
    'PUT',
    `/carts/${cartId}/order`,
    null,
    {},
    { environment: options.environment || STAGE_ENV }
  );
}

async function createSingleTestOrder(config, skus, options = {}) {
  const qty = options.qtyPerLine || 1;

  if (options.dryRun) {
    return {
      ok: true,
      dry_run: true,
      skus,
      qty_per_line: qty,
    };
  }

  const cartResult = await createCustomerCart(config.customerId, options);
  if (!cartResult.ok) {
    return { ok: false, error: cartResult.error, skus };
  }

  const cartId = cartResult.cartId;

  for (const sku of skus) {
    const addResult = await addCartItem(cartId, sku, qty, options);
    if (!addResult.ok) {
      return { ok: false, error: `Failed to add SKU ${sku}: ${addResult.error}`, skus, cartId };
    }
  }

  const shipResult = await setShippingInformation(cartId, config, options);
  if (!shipResult.ok) {
    return { ok: false, error: `Shipping failed: ${shipResult.error}`, skus, cartId };
  }

  const billResult = await setBillingAddress(cartId, config.address, options);
  if (!billResult.ok) {
    return { ok: false, error: `Billing address failed: ${billResult.error}`, skus, cartId };
  }

  const payResult = await setPaymentMethod(cartId, config.paymentMethod, options);
  if (!payResult.ok) {
    return { ok: false, error: `Payment method failed: ${payResult.error}`, skus, cartId };
  }

  const orderResult = await placeOrder(cartId, options);
  if (!orderResult.ok) {
    return { ok: false, error: `Place order failed: ${orderResult.error}`, skus, cartId };
  }

  const orderId = String(orderResult.data ?? '').trim();
  let incrementId = null;
  let entityId = null;
  const parsedEntityId = Number.parseInt(orderId, 10);

  if (Number.isFinite(parsedEntityId) && parsedEntityId > 0) {
    entityId = parsedEntityId;
    const fetchResult = await adobeCommerce.fetchOrderByEntityId(parsedEntityId, {
      environment: options.environment || STAGE_ENV,
    });
    if (fetchResult.ok) {
      incrementId = fetchResult.order.increment_id;
    }
  }

  return {
    ok: true,
    entity_id: entityId,
    increment_id: incrementId || orderId,
    skus,
    cartId,
  };
}

async function createTestOrders(params = {}, context) {
  const count = Math.max(1, Math.min(20, Number.parseInt(String(params.count ?? 5), 10) || 5));
  const lineCount = Math.max(1, Math.min(20, Number.parseInt(String(params.lineCount ?? 4), 10) || 4));
  const dryRun = Boolean(params.dryRun);
  const delayMs = Math.max(500, Math.min(5000, Number.parseInt(String(params.delayMs ?? 1500), 10) || 1500));
  const environment = params.environment || STAGE_ENV;

  const configError = adobeCommerce.configError();
  if (configError) {
    return { ok: false, error: configError, orders: [], errors: [] };
  }

  const config = await loadReferenceConfig({ environment });
  const catalogResult = await fetchCatalogSkus({ environment });
  if (!catalogResult.ok) {
    return { ok: false, error: catalogResult.error, orders: [], errors: [] };
  }

  if (catalogResult.skus.length < lineCount) {
    return {
      ok: false,
      error: `Catalog has ${catalogResult.skus.length} enabled products; need at least ${lineCount}.`,
      orders: [],
      errors: [],
    };
  }

  context?.log?.(
    'accs-test-order-create count=%s line_count=%s dry_run=%s catalog_skus=%s',
    count,
    lineCount,
    dryRun,
    catalogResult.skus.length
  );

  const orders = [];
  const errors = [];

  for (let i = 0; i < count; i += 1) {
    const skus = pickRandom(catalogResult.skus, lineCount);
    const result = await createSingleTestOrder(config, skus, { dryRun, environment });

    if (result.ok) {
      orders.push({
        index: i + 1,
        increment_id: result.increment_id ?? null,
        entity_id: result.entity_id ?? null,
        skus: result.skus,
        dry_run: Boolean(result.dry_run),
        cart_id: result.cartId ?? null,
      });
      context?.log?.(
        'accs-test-order-create order %s/%s %s skus=%s',
        i + 1,
        count,
        dryRun ? 'dry_run' : (result.increment_id || result.entity_id || 'created'),
        skus.join(', ')
      );
    } else {
      errors.push({ index: i + 1, skus, error: result.error });
      context?.error?.('accs-test-order-create failed order %s: %s', i + 1, result.error);
    }

    if (i < count - 1) {
      await sleep(delayMs);
    }
  }

  return {
    ok: errors.length === 0,
    dry_run: dryRun,
    accs_environment: environment,
    reference: {
      order_entity_id: REFERENCE_ORDER_ENTITY_ID,
      customer_id: config.customerId,
      customer_email: config.email,
      payment_method: config.paymentMethod,
      shipping_carrier: config.shippingCarrier,
      shipping_method: config.shippingMethod,
      reference_order: config.referenceOrder,
    },
    catalog_product_count: catalogResult.skus.length,
    requested_count: count,
    line_count: lineCount,
    created_count: orders.filter((order) => !order.dry_run && order.increment_id).length,
    orders,
    errors,
  };
}

module.exports = {
  createTestOrders,
  REFERENCE_ORDER_ENTITY_ID,
  STAGE_ENV,
};
