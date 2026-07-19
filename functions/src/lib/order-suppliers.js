/** Known fulfillment / supplier codes for canonical order routing. */
const KNOWN_SUPPLIER_CODES = ['Cart', 'CPPC', 'MTL'];

function normalizeSupplierCode(value) {
  const code = String(value ?? '').trim();
  if (!code) {
    return null;
  }

  const match = KNOWN_SUPPLIER_CODES.find(
    (known) => known.toLowerCase() === code.toLowerCase()
  );

  return match ?? code;
}

/**
 * Build suppliers map: { Cart: 1, CPPC: 0, MTL: 1, ... }
 * @param {object[]} items - line items with .fulfillment
 */
function buildSuppliersMap(items) {
  const present = new Set();

  for (const item of items || []) {
    const code = normalizeSupplierCode(item?.fulfillment);
    if (code) {
      present.add(code);
    }
  }

  const suppliers = {};
  for (const code of KNOWN_SUPPLIER_CODES) {
    suppliers[code] = present.has(code) ? 1 : 0;
  }

  for (const code of present) {
    if (!Object.prototype.hasOwnProperty.call(suppliers, code)) {
      suppliers[code] = 1;
    }
  }

  return suppliers;
}

/** Mirror suppliers map to Service Bus application properties. */
function suppliersToApplicationProperties(suppliers) {
  const properties = {};

  for (const [code, flag] of Object.entries(suppliers || {})) {
    properties[`supplier_${code}`] = Number(flag) ? 1 : 0;
  }

  return properties;
}

function filterItemsBySupplier(items, supplierCode) {
  const target = normalizeSupplierCode(supplierCode);
  if (!target) {
    return [];
  }

  return (items || []).filter(
    (item) => normalizeSupplierCode(item?.fulfillment) === target
  );
}

module.exports = {
  KNOWN_SUPPLIER_CODES,
  normalizeSupplierCode,
  buildSuppliersMap,
  suppliersToApplicationProperties,
  filterItemsBySupplier,
};
