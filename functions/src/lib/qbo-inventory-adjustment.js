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
    .map((line, index) => {
      const itemId = String(line.qbo_item_id || '').trim();
      const qty = Number(line.qty_change || 0);
      if (!itemId || !Number.isFinite(qty) || Math.abs(qty) < 0.0000001) {
        return null;
      }
      return {
        Id: String(index + 1),
        DetailType: 'ItemAdjustmentLineDetail',
        ItemAdjustmentLineDetail: {
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
    `SELECT * FROM Item WHERE Sku = '${sku}' MAXRESULTS 10`,
    10,
    connectionStore()
  );
  if (!result.ok) {
    return result;
  }

  const rows = qboClient.extractQueryRows(result.data, ['Item']);
  const inventory = rows.find((row) => (
    String(row.Type || '') === 'Inventory' && row.Active !== false
  )) || rows.find((row) => String(row.Type || '') === 'Inventory') || null;
  return { ok: true, error: null, item: inventory, candidates: rows };
}

/**
 * Resolve the live QBO Inventory Item Id for a SKU and keep SKUMaster in sync.
 * Prefer QBO over the local QBO_ItemID — conversion can leave stale NonInventory/Service Ids.
 */
async function resolveInventoryItemId(pool, sql, skuCode) {
  const sku = String(skuCode || '').trim();
  if (!sku) {
    return { ok: false, item_id: '', error: 'SKU is required.' };
  }

  const remote = await findItemBySku(sku);
  if (!remote.ok) {
    return { ok: false, item_id: '', error: remote.error || 'QBO item lookup failed.' };
  }
  if (!remote.item) {
    return {
      ok: false,
      item_id: '',
      error: `No active QuickBooks Inventory item found for SKU ${sku}.`,
    };
  }

  const itemId = String(remote.item.Id || '').trim();
  if (!itemId) {
    return { ok: false, item_id: '', error: `QuickBooks Inventory item for ${sku} has no Id.` };
  }

  await pool.request()
    .input('sku', sql.NVarChar(100), sku)
    .input('itemId', sql.NVarChar(32), itemId)
    .query(`
      UPDATE dbo.SKUMaster
      SET QBO_ItemID = @itemId,
          QBO_SyncStatus = N'Synced',
          QBO_SyncError = NULL,
          QBO_SyncedAt = SYSUTCDATETIME()
      WHERE SKUCode = @sku
        AND (
          QBO_ItemID IS NULL
          OR LTRIM(RTRIM(QBO_ItemID)) = N''
          OR QBO_ItemID <> @itemId
        )
    `);

  return { ok: true, item_id: itemId, error: null, item: remote.item };
}

module.exports = {
  postInventoryAdjustment,
  findItemBySku,
  resolveInventoryItemId,
  connectionStore,
};
