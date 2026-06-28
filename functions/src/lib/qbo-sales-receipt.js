const qboClient = require('./qbo-client');
const qboConfig = require('./qbo-config');
const { escapeQboQueryValue } = require('./qbo-order-mapper');

async function findCustomerByEmail(email) {
  const trimmed = String(email ?? '').trim();
  if (!trimmed) {
    return null;
  }

  const result = await qboClient.query(
    `SELECT * FROM Customer WHERE PrimaryEmailAddr = '${escapeQboQueryValue(trimmed)}'`
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
  });

  if (!result.ok) {
    throw new Error(result.error);
  }

  return result.data?.Customer ?? null;
}

async function ensureCustomer(customer, email) {
  let existing = await findCustomerByEmail(email);
  if (existing) {
    return existing;
  }

  existing = await createCustomer(customer);
  return existing;
}

async function findItemBySku(sku) {
  const result = await qboClient.query(
    `SELECT * FROM Item WHERE Sku = '${escapeQboQueryValue(sku)}'`
  );

  if (!result.ok) {
    throw new Error(result.error);
  }

  const rows = qboClient.extractQueryRows(result.data, ['Item']);
  return rows[0] ?? null;
}

async function resolveItemRef(line) {
  const item = await findItemBySku(line.sku);
  if (item?.Id) {
    return {
      value: String(item.Id),
      name: item.Name ?? line.sku,
      source: 'sku',
    };
  }

  const fallbackId = qboConfig.fallbackItemId();
  if (fallbackId) {
    return {
      value: fallbackId,
      name: line.sku,
      source: 'fallback',
    };
  }

  throw new Error(`QuickBooks item not found for SKU ${line.sku}. Add the item in sandbox or set QBO_SANDBOX_FALLBACK_ITEM_ID.`);
}

async function findSalesReceiptByDocNumber(docNumber) {
  const result = await qboClient.query(
    `SELECT * FROM SalesReceipt WHERE DocNumber = '${escapeQboQueryValue(docNumber)}'`
  );

  if (!result.ok) {
    throw new Error(result.error);
  }

  const rows = qboClient.extractQueryRows(result.data, ['SalesReceipt']);
  return rows[0] ?? null;
}

async function createSalesReceipt(mapped, context) {
  const existing = await findSalesReceiptByDocNumber(mapped.docNumber);
  if (existing) {
    context.log('QBO sales receipt already exists DocNumber=%s Id=%s', mapped.docNumber, existing.Id);
    return {
      ok: true,
      duplicate: true,
      transaction_id: String(existing.Id),
      doc_number: mapped.docNumber,
    };
  }

  const customer = await ensureCustomer(mapped.customer, mapped.customerEmail);
  const lineDetails = [];

  for (const line of mapped.lines) {
    const itemRef = await resolveItemRef(line);
    const qty = line.qty;
    const unitPrice = Number.isFinite(line.unitPrice) ? line.unitPrice : 0;
    const amount = Number.isFinite(line.amount) && line.amount > 0
      ? line.amount
      : qty * unitPrice;

    lineDetails.push({
      Amount: Number(amount.toFixed(2)),
      Description: line.description,
      DetailType: 'SalesItemLineDetail',
      SalesItemLineDetail: {
        ItemRef: {
          value: itemRef.value,
          name: itemRef.name,
        },
        Qty: qty,
        UnitPrice: Number(unitPrice.toFixed(2)),
      },
    });

    if (itemRef.source === 'fallback') {
      context.log('QBO using fallback item for SKU %s', line.sku);
    }
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
    total: receipt?.TotalAmt ?? mapped.total,
  };
}

module.exports = {
  createSalesReceipt,
  findSalesReceiptByDocNumber,
};
