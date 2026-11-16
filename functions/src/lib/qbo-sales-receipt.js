const qboClient = require('./qbo-client');
const qboConfig = require('./qbo-config');
const qboConnection = require('./qbo-connection');
const qboInventory = require('./qbo-inventory-adjustment');
const { escapeQboQueryValue, roundMoney } = require('./qbo-order-mapper');

function connectionStore() {
  return qboConfig.environment() === 'production'
    ? qboConnection.production
    : qboConnection.staging;
}

async function findCustomerByEmail(email) {
  const trimmed = String(email ?? '').trim();
  if (!trimmed) {
    return null;
  }

  const result = await qboClient.query(
    `SELECT * FROM Customer WHERE Active IN (true, false) AND PrimaryEmailAddr = '${escapeQboQueryValue(trimmed)}'`,
    5,
    connectionStore()
  );

  if (!result.ok) {
    throw new Error(result.error);
  }

  const rows = qboClient.extractQueryRows(result.data, ['Customer']);
  return rows[0] ?? null;
}

async function findCustomerByDisplayName(displayName) {
  const trimmed = String(displayName ?? '').trim();
  if (!trimmed) {
    return null;
  }

  const result = await qboClient.query(
    `SELECT * FROM Customer WHERE Active IN (true, false) AND DisplayName = '${escapeQboQueryValue(trimmed)}'`,
    5,
    connectionStore()
  );

  if (!result.ok) {
    throw new Error(result.error);
  }

  const rows = qboClient.extractQueryRows(result.data, ['Customer']);
  return rows[0] ?? null;
}

async function createCustomer(customer) {
  const payload = {
    DisplayName: customer.displayName,
    GivenName: customer.firstName || undefined,
    FamilyName: customer.lastName || undefined,
  };

  if (customer.email) {
    payload.PrimaryEmailAddr = { Address: customer.email };
  }

  const result = await qboClient.apiRequest('POST', '/customer', {
    query: { minorversion: String(qboClient.MINOR_VERSION) },
    body: payload,
    connectionStore: connectionStore(),
  });

  if (!result.ok) {
    const message = String(result.error || '');
    if (message.toLowerCase().includes('duplicate name')) {
      const existing = await findCustomerByDisplayName(customer.displayName);
      if (existing) {
        return existing;
      }
    }
    throw new Error(result.error);
  }

  return result.data?.Customer ?? null;
}

async function reactivateCustomerIfNeeded(customer) {
  if (!customer?.Id || customer.Active !== false) {
    return customer;
  }

  const result = await qboClient.apiRequest('POST', '/customer', {
    query: { minorversion: String(qboClient.MINOR_VERSION) },
    body: {
      Id: String(customer.Id),
      SyncToken: String(customer.SyncToken),
      sparse: true,
      Active: true,
    },
    connectionStore: connectionStore(),
  });

  if (!result.ok) {
    throw new Error(result.error || `Unable to reactivate QuickBooks customer Id ${customer.Id}.`);
  }

  return result.data?.Customer ?? { ...customer, Active: true };
}

async function ensureCustomer(customer, email, incrementId = '') {
  const trimmedEmail = String(email ?? '').trim();
  if (trimmedEmail) {
    const byEmail = await findCustomerByEmail(trimmedEmail);
    if (byEmail) {
      return reactivateCustomerIfNeeded(byEmail);
    }
  }

  const byName = await findCustomerByDisplayName(customer.displayName);
  if (byName) {
    return reactivateCustomerIfNeeded(byName);
  }

  try {
    return await createCustomer(customer);
  } catch (error) {
    const message = String(error.message || '');
    if (!message.toLowerCase().includes('duplicate name')) {
      throw error;
    }

    const suffix = trimmedEmail || String(incrementId || '').trim();
    if (!suffix) {
      throw error;
    }

    const altName = `${customer.displayName} (${suffix})`;
    const altExisting = await findCustomerByDisplayName(altName);
    if (altExisting) {
      return altExisting;
    }

    return createCustomer({ ...customer, displayName: altName });
  }
}

async function findItemBySku(sku, store = connectionStore()) {
  const result = await qboClient.query(
    `SELECT * FROM Item WHERE Sku = '${escapeQboQueryValue(sku)}'`,
    10,
    store
  );

  if (!result.ok) {
    throw new Error(result.error);
  }

  const rows = qboClient.extractQueryRows(result.data, ['Item']);
  return rows;
}

function preferInventoryItems(options = {}) {
  if (options.preferInventory === true) {
    return true;
  }
  if (options.preferInventory === false) {
    return false;
  }
  return qboConfig.environment() === 'production';
}

/**
 * Sandbox: prefer NonInventory so inventory-sales-sync owns QtyOnHand via adjustments.
 * Production: prefer Inventory items (revenue + qty in one Sales Receipt).
 */
async function resolveItemRef(line, options = {}) {
  if (preferInventoryItems(options)) {
    const pool = options.pool;
    const sql = options.sql;
    if (!pool || !sql) {
      throw new Error('resolveItemRef requires pool and sql when preferInventory is enabled.');
    }

    const resolved = await qboInventory.resolveInventoryItemId(pool, sql, line.sku);
    if (resolved.skip) {
      throw new Error(resolved.error || `SKU ${line.sku} is not inventory-tracked.`);
    }
    if (!resolved.ok || !resolved.item_id) {
      throw new Error(resolved.error || `Missing QBO Inventory item for SKU ${line.sku}.`);
    }

    return {
      value: String(resolved.item_id),
      name: resolved.canonical_sku || line.sku,
      source: 'sku-inventory',
    };
  }

  const rows = await findItemBySku(line.sku);
  const nonInventory = rows.find((item) => {
    const type = String(item?.Type || '');
    return type !== '' && type.toLowerCase() !== 'inventory';
  });

  if (nonInventory?.Id) {
    return {
      value: String(nonInventory.Id),
      name: nonInventory.Name ?? line.sku,
      source: 'sku-non-inventory',
    };
  }

  const inventory = rows.find((item) => String(item?.Type || '').toLowerCase() === 'inventory');
  const fallbackId = qboConfig.fallbackItemId();
  if (inventory?.Id && fallbackId) {
    return {
      value: fallbackId,
      name: line.sku,
      source: 'fallback-avoid-inventory',
    };
  }

  if (fallbackId) {
    return {
      value: fallbackId,
      name: line.sku,
      source: 'fallback',
    };
  }

  if (inventory?.Id) {
    throw new Error(
      `QuickBooks Inventory item exists for SKU ${line.sku}, but SalesReceipt must not post Inventory lines `
      + '(inventory-sales-sync owns quantity). Set QBO_SANDBOX_FALLBACK_ITEM_ID to a NonInventory item.'
    );
  }

  throw new Error(`QuickBooks item not found for SKU ${line.sku}. Add the item in sandbox or set QBO_SANDBOX_FALLBACK_ITEM_ID.`);
}

async function findDefaultIncomeAccountRef(store = connectionStore()) {
  const result = await qboClient.query(
    "SELECT * FROM Account WHERE AccountType = 'Income' AND Active = true",
    25,
    store
  );
  if (!result.ok) {
    throw new Error(result.error || 'Unable to load QuickBooks income accounts.');
  }

  const rows = qboClient.extractQueryRows(result.data, ['Account']);
  const preferred = rows.find((row) => /sales/i.test(String(row?.Name || '')))
    || rows[0];
  if (!preferred?.Id) {
    throw new Error('No active QuickBooks income account found for Shipping service item.');
  }

  return {
    value: String(preferred.Id),
    name: String(preferred.Name || 'Income'),
  };
}

async function createServiceItem(name, store = connectionStore()) {
  const incomeAccountRef = await findDefaultIncomeAccountRef(store);
  const result = await qboClient.apiRequest('POST', '/item', {
    query: { minorversion: String(qboClient.MINOR_VERSION) },
    body: {
      Name: name,
      Type: 'Service',
      IncomeAccountRef: incomeAccountRef,
    },
    connectionStore: store,
  });

  if (!result.ok) {
    throw new Error(result.error || `Unable to create QuickBooks ${name} service item.`);
  }

  const item = result.data?.Item;
  if (!item?.Id) {
    throw new Error(`QuickBooks did not return a ${name} service item Id.`);
  }

  return {
    value: String(item.Id),
    name: String(item.Name || name),
    source: 'created',
  };
}

async function resolveServiceItemRef(name, configuredId, store = connectionStore()) {
  if (configuredId) {
    return {
      value: String(configuredId),
      name,
      source: 'configured',
    };
  }

  const byName = await qboClient.query(
    `SELECT * FROM Item WHERE Name = '${escapeQboQueryValue(name)}' AND Type = 'Service'`,
    1,
    store
  );
  if (byName.ok) {
    const rows = qboClient.extractQueryRows(byName.data, ['Item']);
    if (rows[0]?.Id) {
      return {
        value: String(rows[0].Id),
        name: rows[0].Name ?? name,
        source: 'qbo-service',
      };
    }
  }

  return createServiceItem(name, store);
}

async function resolveShippingItemRef(store = connectionStore()) {
  return resolveServiceItemRef('Shipping', qboConfig.shippingItemId(), store);
}

async function resolveSalesTaxItemRef(store = connectionStore()) {
  return resolveServiceItemRef('Sales Tax', qboConfig.salesTaxItemId(), store);
}

function buildProductLineDetail(line, itemRef) {
  const qty = Number(line.qty || 0);
  const listUnitPrice = roundMoney(line.unitPrice);
  const discountAmount = roundMoney(line.discountAmount);
  const rowTotal = roundMoney(line.rowTotal);
  const netUnitPrice = qty > 0 ? roundMoney(rowTotal / qty) : 0;
  let description = String(line.description || line.sku || '').trim();
  if (discountAmount > 0) {
    description = `${description} | list ${listUnitPrice.toFixed(2)} | discount ${discountAmount.toFixed(2)} | net ${rowTotal.toFixed(2)}`;
  }

  return {
    Amount: rowTotal,
    Description: description,
    DetailType: 'SalesItemLineDetail',
    SalesItemLineDetail: {
      ItemRef: {
        value: itemRef.value,
        name: itemRef.name,
      },
      Qty: qty,
      UnitPrice: netUnitPrice,
    },
  };
}

function buildDiscountLineDetail(discountAmount, description = '') {
  const amount = roundMoney(discountAmount);
  const line = {
    Amount: amount,
    DetailType: 'DiscountLineDetail',
    DiscountLineDetail: {
      PercentBased: false,
    },
  };

  if (description) {
    line.Description = description;
  }

  return line;
}

function buildDiscountLineDetail(discountAmount, description = '') {
  const amount = roundMoney(discountAmount);
  const line = {
    Amount: amount,
    DetailType: 'DiscountLineDetail',
    DiscountLineDetail: {
      PercentBased: false,
    },
  };

  if (description) {
    line.Description = description;
  }

  return line;
}

function buildShippingLineDetail(shipping, itemRef) {
  const amount = roundMoney(shipping.amount);
  return {
    Amount: amount,
    Description: shipping.description || 'Shipping',
    DetailType: 'SalesItemLineDetail',
    SalesItemLineDetail: {
      ItemRef: {
        value: itemRef.value,
        name: itemRef.name,
      },
      Qty: 1,
      UnitPrice: amount,
    },
  };
}

function buildSalesTaxLineDetail(taxAmount, itemRef) {
  const amount = roundMoney(taxAmount);
  return {
    Amount: amount,
    Description: 'Sales Tax',
    DetailType: 'SalesItemLineDetail',
    SalesItemLineDetail: {
      ItemRef: {
        value: itemRef.value,
        name: itemRef.name,
      },
      Qty: 1,
      UnitPrice: amount,
    },
  };
}

function buildOrderDiscountLineDetail(discountAmount) {
  return buildDiscountLineDetail(discountAmount, 'Order discount');
}

async function getSalesReceiptById(txnId, store = connectionStore()) {
  const id = String(txnId || '').trim();
  if (!id) {
    return null;
  }

  const result = await qboClient.apiRequest('GET', `/salesreceipt/${encodeURIComponent(id)}`, {
    query: { minorversion: String(qboClient.MINOR_VERSION) },
    connectionStore: store,
  });

  if (!result.ok) {
    throw new Error(result.error || `Unable to load QBO SalesReceipt Id ${id}.`);
  }

  return result.data?.SalesReceipt ?? null;
}

async function voidAndDeleteSalesReceipt(txnId, reason, store = connectionStore()) {
  let receipt = await getSalesReceiptById(txnId, store);
  if (!receipt) {
    return { ok: true, deleted: false, missing: true, txn_id: String(txnId || '') };
  }

  const auditNote = [
    String(receipt.PrivateNote || '').trim(),
    String(reason || '').trim(),
  ].filter(Boolean).join(' | ').slice(0, 4000);

  if (auditNote && auditNote !== String(receipt.PrivateNote || '').trim()) {
    const update = await qboClient.apiRequest('POST', '/salesreceipt', {
      query: { minorversion: String(qboClient.MINOR_VERSION) },
      body: {
        Id: String(receipt.Id),
        SyncToken: String(receipt.SyncToken),
        sparse: true,
        PrivateNote: auditNote,
      },
      connectionStore: store,
    });
    if (!update.ok) {
      return { ok: false, error: update.error, txn_id: String(receipt.Id) };
    }
    receipt = update.data?.SalesReceipt ?? receipt;
  }

  const del = await qboClient.apiRequest('POST', '/salesreceipt', {
    query: {
      operation: 'delete',
      minorversion: String(qboClient.MINOR_VERSION),
    },
    body: {
      Id: String(receipt.Id),
      SyncToken: String(receipt.SyncToken),
    },
    connectionStore: store,
  });

  if (!del.ok) {
    return { ok: false, error: del.error, txn_id: String(receipt.Id) };
  }

  return {
    ok: true,
    deleted: true,
    missing: false,
    txn_id: String(receipt.Id),
    doc_number: String(receipt.DocNumber || ''),
  };
}

async function findSalesReceiptByDocNumber(docNumber, store = connectionStore()) {
  const result = await qboClient.query(
    `SELECT * FROM SalesReceipt WHERE DocNumber = '${escapeQboQueryValue(docNumber)}'`,
    5,
    store
  );

  if (!result.ok) {
    throw new Error(result.error);
  }

  const rows = qboClient.extractQueryRows(result.data, ['SalesReceipt']);
  return rows[0] ?? null;
}

async function createSalesReceipt(mapped, context, options = {}) {
  const log = context?.log ? context.log.bind(context) : () => {};
  const store = options.connectionStore || connectionStore();
  const existing = await findSalesReceiptByDocNumber(mapped.docNumber, store);
  if (existing) {
    log('QBO sales receipt already exists DocNumber=%s Id=%s', mapped.docNumber, existing.Id);
    return {
      ok: true,
      duplicate: true,
      transaction_id: String(existing.Id),
      doc_number: mapped.docNumber,
      sync_token: String(existing.SyncToken || ''),
      total: existing.TotalAmt ?? mapped.total,
    };
  }

  const customer = await ensureCustomer(mapped.customer, mapped.customerEmail, mapped.incrementId);
  const lineDetails = [];

  for (const line of mapped.lines) {
    const itemRef = await resolveItemRef(line, { ...options, connectionStore: store });
    lineDetails.push(buildProductLineDetail(line, itemRef));

    if (itemRef.source === 'fallback') {
      log('QBO using fallback item for SKU %s', line.sku);
    }
  }

  if (mapped.shipping && roundMoney(mapped.shipping.amount) > 0) {
    const shippingItemRef = await resolveShippingItemRef(store);
    lineDetails.push(buildShippingLineDetail(mapped.shipping, shippingItemRef));
  }

  if (roundMoney(mapped.orderLevelDiscount) > 0) {
    lineDetails.push(buildOrderDiscountLineDetail(mapped.orderLevelDiscount));
  }

  const taxAmount = roundMoney(mapped.tax?.amount ?? 0);
  if (taxAmount > 0) {
    const salesTaxItemRef = await resolveSalesTaxItemRef(store);
    lineDetails.push(buildSalesTaxLineDetail(taxAmount, salesTaxItemRef));
  }

  const payload = {
    DocNumber: mapped.docNumber,
    TxnDate: mapped.txnDate,
    PrivateNote: mapped.privateNote,
    CustomerRef: {
      value: String(customer.Id),
      name: customer.DisplayName ?? mapped.customer.displayName,
    },
    Line: lineDetails,
  };

  const result = await qboClient.apiRequest('POST', '/salesreceipt', {
    query: { minorversion: String(qboClient.MINOR_VERSION) },
    body: payload,
    connectionStore: store,
  });

  if (!result.ok) {
    throw new Error(result.error);
  }

  const receipt = result.data?.SalesReceipt;
  return {
    ok: true,
    duplicate: false,
    transaction_id: String(receipt?.Id ?? ''),
    doc_number: String(receipt?.DocNumber ?? mapped.docNumber),
    sync_token: String(receipt?.SyncToken ?? ''),
    total: receipt?.TotalAmt ?? mapped.total,
  };
}

module.exports = {
  createSalesReceipt,
  findSalesReceiptByDocNumber,
  getSalesReceiptById,
  voidAndDeleteSalesReceipt,
  resolveItemRef,
  preferInventoryItems,
  connectionStore,
};
