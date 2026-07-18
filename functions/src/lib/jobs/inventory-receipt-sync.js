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

function isReceivedStatus(status) {
  const normalized = String(status || '').trim().toLowerCase();
  if (!normalized) {
    return false;
  }
  return ['received', 'complete', 'completed', 'closed', 'putaway', 'put away'].some((token) =>
    normalized.includes(token)
  );
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

async function postImsReceipt(pool, porId, facility, lines) {
  const header = await pool.request()
    .input('porId', sql.Int, porId)
    .query(`
      INSERT INTO dbo.InvTransaction (TransactionType, ReferenceType, ReferenceID, Notes)
      OUTPUT INSERTED.TransactionID
      VALUES (N'POReceipt', N'POReceipt', @porId, N'inventory-receipt-sync')
    `);
  const txnId = Number(header.recordset[0]?.TransactionID || 0);
  if (!txnId) {
    throw new Error(`Unable to create IMS transaction for POR ${porId}.`);
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
    const after = before + Number(line.qty);

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
      .input('qtyChange', sql.Decimal(18, 4), Number(line.qty))
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

  await pool.request()
    .input('porId', sql.Int, porId)
    .query(`
      UPDATE dbo.POReceipt
      SET PORStatus = CASE WHEN PORStatus = N'Transmitted' THEN N'Complete' ELSE PORStatus END,
          IMSPostedAt = SYSUTCDATETIME(),
          JazzReceivedAt = COALESCE(JazzReceivedAt, SYSUTCDATETIME())
      WHERE PORID = @porId
    `);

  return txnId;
}

async function loadCandidateReceipts(pool) {
  const result = await pool.request().query(`
    SELECT TOP (100)
      r.PORID,
      r.JazzASN,
      r.JazzASNStatus,
      r.Facility,
      r.PORStatus,
      r.IMSPostedAt
    FROM dbo.POReceipt r
    WHERE r.PORStatus IN (N'Transmitted', N'Complete')
      AND r.IMSPostedAt IS NULL
      AND (
        r.JazzASNStatus IS NULL
        OR LOWER(r.JazzASNStatus) LIKE N'%receiv%'
        OR LOWER(r.JazzASNStatus) LIKE N'%complete%'
        OR LOWER(r.JazzASNStatus) LIKE N'%putaway%'
        OR LOWER(r.JazzASNStatus) LIKE N'%closed%'
      )
    ORDER BY r.PORID ASC
  `);
  return result.recordset;
}

async function resolveImsFacility(pool, facilityCode) {
  const code = String(facilityCode || 'CART').trim() || 'CART';
  const result = await pool.request()
    .input('code', sql.NVarChar(50), code)
    .query(`
      SELECT TOP (1) FacilityCode
      FROM dbo.Facility
      WHERE UPPER(FacilityCode) = UPPER(@code)
         OR (
              ExternalReferenceCode IS NOT NULL
              AND UPPER(ExternalReferenceCode) = UPPER(@code)
         )
      ORDER BY CASE WHEN UPPER(FacilityCode) = UPPER(@code) THEN 0 ELSE 1 END
    `);

  return String(result.recordset[0]?.FacilityCode || 'CART').trim() || 'CART';
}

async function loadReceiptLines(pool, porId) {
  const result = await pool.request()
    .input('porId', sql.Int, porId)
    .query(`
      SELECT PORDID, ItemSKU, QuantityReceived, QuantityExpected
      FROM dbo.PORDetail
      WHERE PORID = @porId
      ORDER BY PORDID
    `);

  return result.recordset
    .map((row) => {
      const sku = String(row.ItemSKU || '').trim();
      let qty = Number(row.QuantityReceived || 0);
      if (!(qty > 0)) {
        qty = Number(row.QuantityExpected || 0);
      }
      if (!sku || !(qty > 0)) {
        return null;
      }
      return {
        detail_id: Number(row.PORDID),
        sku,
        qty,
      };
    })
    .filter(Boolean);
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
    const receipts = await loadCandidateReceipts(pool);
    for (const receipt of receipts) {
      processed += 1;
      const porId = Number(receipt.PORID);
      const facility = await resolveImsFacility(pool, receipt.Facility || 'CART');
      const status = String(receipt.JazzASNStatus || '');

      // Require an explicit received-like status when JazzASNStatus is populated.
      if (status && !isReceivedStatus(status)) {
        skipped += 1;
        continue;
      }

      const lines = await loadReceiptLines(pool, porId);
      if (lines.length === 0) {
        skipped += 1;
        continue;
      }

      const qboLines = [];
      let missingItem = false;
      for (const line of lines) {
        const docNumber = `NA-RCV-${porId}-${line.detail_id}`;
        if (await logExists(pool, docNumber)) {
          continue;
        }
        const itemId = await resolveQboItemId(pool, line.sku);
        if (!itemId) {
          missingItem = true;
          failures.push({ por_id: porId, sku: line.sku, error: 'Missing QBO_ItemID for Inventory item.' });
          break;
        }
        qboLines.push({
          docNumber,
          detail_id: line.detail_id,
          sku: line.sku,
          qty: line.qty,
          qbo_item_id: itemId,
        });
      }

      if (missingItem) {
        failed += 1;
        continue;
      }

      if (qboLines.length === 0) {
        // Already synced — still mark IMS posted if needed.
        if (!receipt.IMSPostedAt) {
          await postImsReceipt(pool, porId, facility, lines);
        }
        skipped += 1;
        continue;
      }

      try {
        await postImsReceipt(pool, porId, facility, lines);

        const adj = await qboInventory.postInventoryAdjustment({
          docNumber: `NA-RCV-${porId}`,
          lines: qboLines.map((line) => ({
            qbo_item_id: line.qbo_item_id,
            qty_change: line.qty,
          })),
          adjustAccountId: accountId,
          privateNote: `NutraAxis PO receipt ${porId}`,
        });

        for (const line of qboLines) {
          await writeLog(pool, {
            doc_number: line.docNumber,
            sync_type: 'Receipt',
            reference_type: 'POReceipt',
            reference_id: porId,
            reference_line_key: String(line.detail_id),
            sku_code: line.sku,
            qty_change: line.qty,
            facility_code: facility,
            qbo_txn_id: adj.ok ? String(adj.txn?.Id || '') || null : null,
            qbo_sync_token: adj.ok ? String(adj.txn?.SyncToken || '') || null : null,
            sync_status: adj.ok ? 'Synced' : 'Error',
            sync_error: adj.ok ? null : String(adj.error || 'Adjustment failed').slice(0, 500),
          });
        }

        if (!adj.ok) {
          failed += 1;
          failures.push({ por_id: porId, error: adj.error });
          continue;
        }

        posted += 1;
      } catch (error) {
        failed += 1;
        failures.push({ por_id: porId, error: error.message });
      }
    }

    return {
      ok: failed === 0,
      error: failed === 0 ? null : `${failed} receipt sync failure(s).`,
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
