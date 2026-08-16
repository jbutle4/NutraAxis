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

function legacySkuAlias(skuCode) {
  const sku = String(skuCode || '').trim();
  if (!/^NS-/i.test(sku) || sku.length <= 3) {
    return null;
  }

  return `NA-${sku.slice(3)}`;
}

async function loadSkuMasterRow(pool, sql, skuCode) {
  const sku = String(skuCode || '').trim();
  if (!sku) {
    return null;
  }

  const result = await pool.request()
    .input('sku', sql.NVarChar(100), sku)
    .query(`
      SELECT TOP (1) SKUCode, QBO_TrackingMode
      FROM dbo.SKUMaster
      WHERE SKUCode = @sku
    `);

  return result.recordset[0] || null;
}

/**
 * Resolve order-line SKU to canonical SKUMaster.SKUCode (NS-* → NA-* when twin exists).
 */
async function resolveCanonicalSku(pool, sql, skuCode) {
  const sourceSku = String(skuCode || '').trim();
  if (!sourceSku) {
    return {
      ok: false,
      source_sku: '',
      canonical_sku: '',
      tracks_inventory: false,
      error: 'SKU is required.',
    };
  }

  let row = await loadSkuMasterRow(pool, sql, sourceSku);
  if (!row) {
    const alias = legacySkuAlias(sourceSku);
    if (alias) {
      row = await loadSkuMasterRow(pool, sql, alias);
    }
  }

  if (!row) {
    return {
      ok: false,
      source_sku: sourceSku,
      canonical_sku: sourceSku,
      tracks_inventory: false,
      error: `SKU ${sourceSku} is not in SKUMaster.`,
    };
  }

  const canonicalSku = String(row.SKUCode || sourceSku).trim();
  const trackingMode = String(row.QBO_TrackingMode || 'Inventory').trim();
  const tracksInventory = trackingMode.toLowerCase() !== 'noninventory';

  return {
    ok: true,
    source_sku: sourceSku,
    canonical_sku: canonicalSku,
    tracks_inventory: tracksInventory,
    error: null,
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
 * Prefer QBO SKU lookup over cached realm-specific item Id columns.
 */
async function resolveInventoryItemId(pool, sql, skuCode) {
  const canonical = await resolveCanonicalSku(pool, sql, skuCode);
  if (!canonical.ok) {
    return {
      ok: false,
      item_id: '',
      error: canonical.error || 'SKU is required.',
      source_sku: canonical.source_sku,
      canonical_sku: canonical.canonical_sku,
    };
  }

  if (!canonical.tracks_inventory) {
    return {
      ok: false,
      item_id: '',
      skip: true,
      error: `SKU ${canonical.canonical_sku} is NonInventory in SKUMaster; skipped for quantity sync.`,
      source_sku: canonical.source_sku,
      canonical_sku: canonical.canonical_sku,
    };
  }

  const sku = canonical.canonical_sku;
  const remote = await findItemBySku(sku);
  if (!remote.ok) {
    return {
      ok: false,
      item_id: '',
      error: remote.error || 'QBO item lookup failed.',
      source_sku: canonical.source_sku,
      canonical_sku: sku,
    };
  }
  if (!remote.item) {
    return {
      ok: false,
      item_id: '',
      error: `No active QuickBooks Inventory item found for SKU ${sku}.`,
      source_sku: canonical.source_sku,
      canonical_sku: sku,
    };
  }

  const itemId = String(remote.item.Id || '').trim();
  if (!itemId) {
    return {
      ok: false,
      item_id: '',
      error: `QuickBooks Inventory item for ${sku} has no Id.`,
      source_sku: canonical.source_sku,
      canonical_sku: sku,
    };
  }

  const syncToken = String(remote.item.SyncToken || '').trim();
  const isProduction = qboConfigEnvironment() === 'production';
  const idColumn = isProduction ? 'QBO_ItemID_Production' : 'QBO_ItemID_Sandbox';
  const tokenColumn = isProduction ? 'QBO_SyncToken_Production' : 'QBO_SyncToken_Sandbox';

  const req = pool.request()
    .input('sku', sql.NVarChar(100), sku)
    .input('itemId', sql.NVarChar(32), itemId);

  let updateSql = `
      UPDATE dbo.SKUMaster
      SET ${idColumn} = @itemId,
          QBO_SyncStatus = N'Synced',
          QBO_SyncError = NULL,
          QBO_SyncedAt = SYSUTCDATETIME()
      WHERE SKUCode = @sku
        AND (
          ${idColumn} IS NULL
          OR LTRIM(RTRIM(${idColumn})) = N''
          OR ${idColumn} <> @itemId
        )
    `;

  if (syncToken !== '') {
    req.input('syncToken', sql.NVarChar(32), syncToken);
    updateSql = `
      UPDATE dbo.SKUMaster
      SET ${idColumn} = @itemId,
          ${tokenColumn} = @syncToken,
          QBO_SyncStatus = N'Synced',
          QBO_SyncError = NULL,
          QBO_SyncedAt = SYSUTCDATETIME()
      WHERE SKUCode = @sku
        AND (
          ${idColumn} IS NULL
          OR LTRIM(RTRIM(${idColumn})) = N''
          OR ${idColumn} <> @itemId
        )
    `;
  }

  await req.query(updateSql);

  return {
    ok: true,
    item_id: itemId,
    error: null,
    item: remote.item,
    source_sku: canonical.source_sku,
    canonical_sku: sku,
  };
}

module.exports = {
  postInventoryAdjustment,
  findItemBySku,
  legacySkuAlias,
  resolveCanonicalSku,
  resolveInventoryItemId,
  connectionStore,
};
