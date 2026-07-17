const qboClient = require('./qbo-client');
const qboConnection = require('./qbo-connection');

function connectionStore() {
  return qboConfigEnvironment() === 'production'
    ? qboConnection.production
    : qboConnection.staging;
}

function qboConfigEnvironment() {
  const env = String(process.env.QBO_ENVIRONMENT || 'sandbox').toLowerCase();
  return env === 'production' ? 'production' : 'sandbox';
}

async function postInventoryAdjustment({ docNumber, lines, adjustAccountId, privateNote = null }) {
  const detailLines = (lines || [])
    .map((line) => {
      const itemId = String(line.qbo_item_id || '').trim();
      const qty = Number(line.qty_change || 0);
      if (!itemId || !Number.isFinite(qty) || Math.abs(qty) < 0.0000001) {
        return null;
      }
      return {
        DetailType: 'InventoryAdjustmentLineDetail',
        Amount: 0,
        InventoryAdjustmentLineDetail: {
          ItemRef: { value: itemId },
          QtyDiff: qty,
        },
      };
    })
    .filter(Boolean);

  if (!docNumber || detailLines.length === 0 || !adjustAccountId) {
    return { ok: false, error: 'DocNumber, adjust account, and at least one line are required.', txn: null };
  }

  const payload = {
    DocNumber: String(docNumber).trim(),
    TxnDate: new Date().toISOString().slice(0, 10),
    AdjustAccountRef: { value: String(adjustAccountId).trim() },
    Line: detailLines,
  };
  if (privateNote) {
    payload.PrivateNote = String(privateNote).slice(0, 4000);
  }

  const result = await qboClient.apiRequest('POST', '/inventoryadjustment', {
    query: { minorversion: '65' },
    body: payload,
    connectionStore: connectionStore(),
  });

  if (!result.ok) {
    return { ok: false, error: result.error || 'Inventory adjustment failed.', txn: null, data: result.data };
  }

  return {
    ok: true,
    error: null,
    txn: result.data?.InventoryAdjustment ?? null,
    data: result.data,
  };
}

async function findItemBySku(skuCode) {
  const sku = String(skuCode || '').trim().replace(/'/g, "\\'");
  if (!sku) {
    return { ok: false, error: 'SKU is required.', item: null };
  }

  const result = await qboClient.query(
    `SELECT * FROM Item WHERE Sku = '${sku}' MAXRESULTS 5`,
    5,
    connectionStore()
  );
  if (!result.ok) {
    return result;
  }

  const rows = qboClient.extractQueryRows(result.data, ['Item']);
  const inventory = rows.find((row) => String(row.Type || '') === 'Inventory') || rows[0] || null;
  return { ok: true, error: null, item: inventory };
}

module.exports = {
  postInventoryAdjustment,
  findItemBySku,
  connectionStore,
};
