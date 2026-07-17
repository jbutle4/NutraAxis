const { sql, connectPool, getSyncSettings } = require('../db-config');
const qboInventory = require('../qbo-inventory-adjustment');

function inventoryDatabase() {
  return process.env.DB_NAME_INVENTORY_SYNC
    || getSyncSettings().stagingDb
    || 'nutraaxis_test';
}

function adjustAccountId() {
  return String(process.env.QBO_INV_ADJUST_ACCOUNT_ID || process.env.QBO_INV_ASSET_ACCOUNT_CART || '').trim();
}

function facilityForFulfillment(fulfillment) {
  const value = String(fulfillment || '').trim().toUpperCase();
  if (value.includes('CPPC')) {
    return 'CPPC';
  }
  if (value.includes('WPC') || value.includes('WLO') || value.includes('WHITE')) {
    return 'WLO';
  }
  return 'CART';
}

async function logExists(pool, docNumber) {
  const result = await pool.request()
    .input('doc', sql.NVarChar(50), docNumber)
    .query('SELECT 1 AS ok FROM dbo.QBOInventorySyncLog WHERE DocNumber = @doc');
  return Boolean(result.recordset[0]);
}

async function writeLog(pool, row) {
  await pool.request()
    .input('doc', sql.NVarChar(50), row.doc_number)
    .input('syncType', sql.NVarChar(30), row.sync_type)
    .input('refType', sql.NVarChar(50), row.reference_type)
    .input('refId', sql.Int, row.reference_id)
    .input('lineKey', sql.NVarChar(100), row.reference_line_key ?? null)
    .input('sku', sql.NVarChar(100), row.sku_code)
    .input('qty', sql.Decimal(18, 4), row.qty_change)
    .input('facility', sql.NVarChar(50), row.facility_code ?? null)
    .input('txnId', sql.NVarChar(32), row.qbo_txn_id ?? null)
    .input('syncToken', sql.NVarChar(32), row.qbo_sync_token ?? null)
    .input('status', sql.NVarChar(20), row.sync_status)
    .input('error', sql.NVarChar(500), row.sync_error ?? null)
    .query(`
      INSERT INTO dbo.QBOInventorySyncLog (
        DocNumber, SyncType, ReferenceType, ReferenceID, ReferenceLineKey,
        SKUCode, QtyChange, FacilityCode, QBO_TxnId, QBO_SyncToken,
        SyncStatus, SyncError, SyncedAt
      )
      VALUES (
        @doc, @syncType, @refType, @refId, @lineKey,
        @sku, @qty, @facility, @txnId, @syncToken,
        @status, @error, CASE WHEN @status = N'Synced' THEN SYSUTCDATETIME() ELSE NULL END
      )
    `);
}

async function ensureBalance(pool, sku, facility) {
  await pool.request()
    .input('sku', sql.NVarChar(100), sku)
    .input('facility', sql.NVarChar(50), facility)
    .query(`
      IF NOT EXISTS (
        SELECT 1 FROM dbo.InvCurrentBalance WHERE SKUCode = @sku AND FacilityCode = @facility
      )
      INSERT INTO dbo.InvCurrentBalance (SKUCode, FacilityCode) VALUES (@sku, @facility)
    `);
}

async function postImsSale(pool, referenceId, facility, lines, note) {
  const header = await pool.request()
    .input('refId', sql.Int, referenceId)
    .input('notes', sql.NVarChar(500), note)
    .query(`
      INSERT INTO dbo.InvTransaction (TransactionType, ReferenceType, ReferenceID, Notes)
      OUTPUT INSERTED.TransactionID
      VALUES (N'Sale', N'AccsSalesOrderHeader', @refId, @notes)
    `);
  const txnId = Number(header.recordset[0]?.TransactionID || 0);
  if (!txnId) {
    throw new Error(`Unable to create IMS sale transaction for order ${referenceId}.`);
  }

  let lineNumber = 0;
  for (const line of lines) {
    lineNumber += 1;
    await ensureBalance(pool, line.sku, facility);
    const balance = await pool.request()
      .input('sku', sql.NVarChar(100), line.sku)
      .input('facility', sql.NVarChar(50), facility)
      .query(`
        SELECT BalanceID, QtyOK
        FROM dbo.InvCurrentBalance WITH (UPDLOCK, HOLDLOCK)
        WHERE SKUCode = @sku AND FacilityCode = @facility
      `);
    const row = balance.recordset[0];
    const before = Number(row?.QtyOK || 0);
    const after = before - Number(line.qty);
    if (after < -0.0000001) {
      throw new Error(`Insufficient IMS qty for ${line.sku} at ${facility} (have ${before}, need ${line.qty}).`);
    }

    await pool.request()
      .input('qty', sql.Decimal(18, 4), after)
      .input('txnId', sql.Int, txnId)
      .input('balanceId', sql.Int, Number(row.BalanceID))
      .query(`
        UPDATE dbo.InvCurrentBalance
        SET QtyOK = @qty, LastTransactionID = @txnId, LastUpdated = SYSUTCDATETIME()
        WHERE BalanceID = @balanceId
      `);

    await pool.request()
      .input('txnId', sql.Int, txnId)
      .input('lineNo', sql.Int, lineNumber)
      .input('sku', sql.NVarChar(100), line.sku)
      .input('facility', sql.NVarChar(50), facility)
      .input('qtyChange', sql.Decimal(18, 4), -Number(line.qty))
      .input('before', sql.Decimal(18, 4), before)
      .input('after', sql.Decimal(18, 4), after)
      .query(`
        INSERT INTO dbo.InvTransactionLine (
          TransactionID, LineNumber, SKUCode, FacilityCode,
          StatusBucket, QtyChange, QtyBefore, QtyAfter
        )
        VALUES (
          @txnId, @lineNo, @sku, @facility,
          N'OK', @qtyChange, @before, @after
        )
      `);
  }

  return txnId;
}

async function loadShippedOrderLines(pool) {
  // Prefer shipped/complete statuses when present; fall back to recent closed orders.
  const result = await pool.request().query(`
    SELECT TOP (500)
      h.AccsSalesOrderHeaderID,
      h.IncrementId,
      h.OrderStatus AS HeaderStatus,
      d.AccsSalesOrderDetailID,
      d.SKU,
      d.QtyOrdered,
      d.QtyShipped,
      d.QtyInvoiced,
      d.FulfillmentAttr
    FROM dbo.AccsSalesOrderHeader h
    INNER JOIN dbo.AccsSalesOrderDetail d ON d.AccsSalesOrderHeaderID = h.AccsSalesOrderHeaderID
    WHERE (
        LOWER(COALESCE(h.OrderStatus, N'')) LIKE N'%complete%'
        OR LOWER(COALESCE(h.OrderStatus, N'')) LIKE N'%ship%'
        OR LOWER(COALESCE(h.OrderStatus, N'')) LIKE N'%closed%'
        OR COALESCE(d.QtyShipped, 0) > 0
      )
      AND COALESCE(d.SKU, N'') <> N''
    ORDER BY h.AccsSalesOrderHeaderID DESC
  `);

  return result.recordset.map((row) => {
    let qty = Number(row.QtyShipped || 0);
    if (!(qty > 0)) {
      qty = Number(row.QtyInvoiced || 0);
    }
    if (!(qty > 0)) {
      qty = Number(row.QtyOrdered || 0);
    }
    return {
      header_id: Number(row.AccsSalesOrderHeaderID),
      detail_id: Number(row.AccsSalesOrderDetailID),
      increment_id: String(row.IncrementId || ''),
      sku: String(row.SKU || '').trim(),
      qty,
      facility: facilityForFulfillment(row.FulfillmentAttr),
    };
  }).filter((row) => row.sku && row.qty > 0 && row.header_id > 0 && row.detail_id > 0);
}

async function resolveQboItemId(pool, sku) {
  const local = await pool.request()
    .input('sku', sql.NVarChar(100), sku)
    .query(`
      SELECT QBO_ItemID
      FROM dbo.SKUMaster
      WHERE SKUCode = @sku AND QBO_ItemID IS NOT NULL
    `);
  const localId = String(local.recordset[0]?.QBO_ItemID || '').trim();
  if (localId) {
    return localId;
  }
  const remote = await qboInventory.findItemBySku(sku);
  return remote.ok && remote.item ? String(remote.item.Id || '').trim() : '';
}

async function run() {
  const accountId = adjustAccountId();
  if (!accountId) {
    return {
      ok: false,
      error: 'Set QBO_INV_ADJUST_ACCOUNT_ID (or QBO_INV_ASSET_ACCOUNT_CART) on the function app.',
      processed: 0,
      posted: 0,
      skipped: 0,
      failed: 0,
    };
  }

  const pool = await connectPool(inventoryDatabase());
  let processed = 0;
  let posted = 0;
  let skipped = 0;
  let failed = 0;
  const failures = [];

  try {
    const lines = await loadShippedOrderLines(pool);
    // Group by header for IMS posting once per order, but QBO lines per detail.
    const byHeader = new Map();
    for (const line of lines) {
      const docNumber = `NA-SAL-${line.header_id}-${line.detail_id}`;
      if (await logExists(pool, docNumber)) {
        skipped += 1;
        continue;
      }
      if (!byHeader.has(line.header_id)) {
        byHeader.set(line.header_id, []);
      }
      byHeader.get(line.header_id).push(line);
    }

    for (const [headerId, headerLines] of byHeader.entries()) {
      processed += 1;
      const facility = headerLines[0].facility;
      const qboLines = [];
      let missing = false;

      for (const line of headerLines) {
        const itemId = await resolveQboItemId(pool, line.sku);
        if (!itemId) {
          missing = true;
          failures.push({ header_id: headerId, sku: line.sku, error: 'Missing QBO_ItemID for Inventory item.' });
          break;
        }
        qboLines.push({
          ...line,
          qbo_item_id: itemId,
          doc_number: `NA-SAL-${line.header_id}-${line.detail_id}`,
        });
      }

      if (missing) {
        failed += 1;
        continue;
      }

      try {
        await postImsSale(
          pool,
          headerId,
          facility,
          qboLines.map((line) => ({ sku: line.sku, qty: line.qty })),
          `inventory-sales-sync order ${headerLines[0].increment_id || headerId}`
        );

        const adj = await qboInventory.postInventoryAdjustment({
          docNumber: `NA-SAL-${headerId}`,
          lines: qboLines.map((line) => ({
            qbo_item_id: line.qbo_item_id,
            qty_change: -Math.abs(line.qty),
          })),
          adjustAccountId: accountId,
          privateNote: `NutraAxis sale ${headerLines[0].increment_id || headerId}`,
        });

        for (const line of qboLines) {
          await writeLog(pool, {
            doc_number: line.doc_number,
            sync_type: 'Sale',
            reference_type: 'AccsSalesOrderDetail',
            reference_id: line.detail_id,
            reference_line_key: String(line.header_id),
            sku_code: line.sku,
            qty_change: -Math.abs(line.qty),
            facility_code: facility,
            qbo_txn_id: adj.ok ? String(adj.txn?.Id || '') || null : null,
            qbo_sync_token: adj.ok ? String(adj.txn?.SyncToken || '') || null : null,
            sync_status: adj.ok ? 'Synced' : 'Error',
            sync_error: adj.ok ? null : String(adj.error || 'Adjustment failed').slice(0, 500),
          });
        }

        if (!adj.ok) {
          failed += 1;
          failures.push({ header_id: headerId, error: adj.error });
          continue;
        }

        posted += 1;
      } catch (error) {
        failed += 1;
        failures.push({ header_id: headerId, error: error.message });
      }
    }

    return {
      ok: failed === 0,
      error: failed === 0 ? null : `${failed} sales sync failure(s).`,
      processed,
      posted,
      skipped,
      failed,
      failures: failures.slice(0, 25),
    };
  } finally {
    await pool.close();
  }
}

module.exports = { run };
