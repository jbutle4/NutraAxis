const { sql, connectPool, getProductionDatabase } = require('../db-config');

function inventoryDatabase() {
  return process.env.DB_NAME_INVENTORY_SYNC
    || getProductionDatabase()
    || 'nutraaxis';
}

function lookbackDays(params = {}) {
  const raw = Number(params.lookback_days ?? params.lookbackDays ?? process.env.INVENTORY_MOVEMENT_RECON_LOOKBACK_DAYS ?? 90);
  if (!Number.isFinite(raw) || raw < 1) {
    return 90;
  }
  return Math.min(Math.floor(raw), 365);
}

async function insertRun(pool, triggerType, lookback, userId) {
  const result = await pool.request()
    .input('trigger', sql.NVarChar(20), triggerType)
    .input('lookback', sql.Int, lookback)
    .input('userId', sql.Int, userId)
    .query(`
      INSERT INTO dbo.InventoryMovementReconRun (
        TriggerType, LookbackDays, Status, TriggeredByUserID
      )
      OUTPUT INSERTED.ReconRunID
      VALUES (@trigger, @lookback, N'Running', @userId)
    `);
  return Number(result.recordset[0]?.ReconRunID || 0);
}

async function finishRun(pool, runId, ok, counts, message, error) {
  await pool.request()
    .input('id', sql.Int, runId)
    .input('status', sql.NVarChar(20), ok ? 'Success' : 'Failed')
    .input('rcv', sql.Int, counts.receipt || 0)
    .input('sale', sql.Int, counts.sale || 0)
    .input('trf', sql.Int, counts.transfer || 0)
    .input('adj', sql.Int, counts.adjustment || 0)
    .input('total', sql.Int, counts.total || 0)
    .input('summary', sql.NVarChar(500), message ?? null)
    .input('error', sql.NVarChar(500), error ?? null)
    .query(`
      UPDATE dbo.InventoryMovementReconRun
      SET FinishedAt = SYSUTCDATETIME(),
          Status = @status,
          ReceiptExceptions = @rcv,
          SaleExceptions = @sale,
          TransferExceptions = @trf,
          AdjustmentExceptions = @adj,
          TotalExceptions = @total,
          SummaryMessage = @summary,
          ErrorMessage = @error
      WHERE ReconRunID = @id
    `);
}

async function insertLine(pool, runId, line) {
  await pool.request()
    .input('runId', sql.Int, runId)
    .input('movement', sql.NVarChar(30), line.movement_type)
    .input('action', sql.NVarChar(50), line.action_code)
    .input('severity', sql.NVarChar(20), line.severity)
    .input('refType', sql.NVarChar(50), line.reference_type)
    .input('refId', sql.Int, line.reference_id)
    .input('refKey', sql.NVarChar(100), line.reference_key ?? null)
    .input('sku', sql.NVarChar(100), line.sku_code ?? null)
    .input('facility', sql.NVarChar(50), line.facility_code ?? null)
    .input('qty', sql.Decimal(18, 4), line.qty ?? null)
    .input('sourceStatus', sql.NVarChar(80), line.source_status ?? null)
    .input('imsStatus', sql.NVarChar(80), line.ims_status ?? null)
    .input('qboStatus', sql.NVarChar(80), line.qbo_status ?? null)
    .input('actionText', sql.NVarChar(500), line.recommended_action)
    .input('detail', sql.NVarChar(500), line.detail_message ?? null)
    .query(`
      INSERT INTO dbo.InventoryMovementReconLine (
        ReconRunID, MovementType, ActionCode, Severity,
        ReferenceType, ReferenceID, ReferenceKey,
        SKUCode, FacilityCode, Qty,
        SourceStatus, ImsStatus, QboStatus,
        RecommendedAction, DetailMessage
      )
      VALUES (
        @runId, @movement, @action, @severity,
        @refType, @refId, @refKey,
        @sku, @facility, @qty,
        @sourceStatus, @imsStatus, @qboStatus,
        @actionText, @detail
      )
    `);
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

async function collectReceiptExceptions(pool, lookback) {
  const result = await pool.request()
    .input('days', sql.Int, lookback)
    .query(`
      SELECT
        r.PORID,
        r.PONumber,
        r.JazzASN,
        r.JazzASNStatus,
        r.PORStatus,
        r.Facility,
        r.IMSPostedAt,
        r.CreateDate,
        d.PORDID,
        d.ItemSKU,
        COALESCE(NULLIF(d.QuantityReceived, 0), d.QuantityExpected, 0) AS Qty,
        l.SyncStatus AS QboSyncStatus,
        l.SyncError AS QboSyncError,
        l.DocNumber
      FROM dbo.POReceipt r
      INNER JOIN dbo.PORDetail d ON d.PORID = r.PORID
      LEFT JOIN dbo.QBOInventorySyncLog l
        ON l.DocNumber = CONCAT(N'NA-RCV-', r.PORID, N'-', d.PORDID)
      WHERE r.CreateDate >= DATEADD(DAY, -@days, SYSUTCDATETIME())
        AND r.PORStatus IN (N'Transmitted', N'Complete')
        AND (
          r.JazzReceivedAt IS NOT NULL
          OR r.IMSPostedAt IS NOT NULL
          OR (
            r.JazzASNStatus IS NOT NULL
            AND (
              LOWER(r.JazzASNStatus) LIKE N'%receiv%'
              OR LOWER(r.JazzASNStatus) LIKE N'%complete%'
              OR LOWER(r.JazzASNStatus) LIKE N'%putaway%'
              OR LOWER(r.JazzASNStatus) LIKE N'%closed%'
            )
          )
        )
      ORDER BY r.PORID, d.PORDID
    `);

  const lines = [];
  for (const row of result.recordset) {
    const status = String(row.JazzASNStatus || '');
    const receivedLike = row.JazzReceivedAt != null
      || row.IMSPostedAt != null
      || isReceivedStatus(status);
    if (!receivedLike) {
      continue;
    }

    const qty = Number(row.Qty || 0);
    if (!(qty > 0) || !String(row.ItemSKU || '').trim()) {
      continue;
    }

    const imsPosted = row.IMSPostedAt != null;
    const qboStatus = String(row.QboSyncStatus || '');
    const doc = String(row.DocNumber || `NA-RCV-${row.PORID}-${row.PORDID}`);

    if (!imsPosted) {
      lines.push({
        movement_type: 'Receipt',
        action_code: 'RECEIPT_MISSING_IMS',
        severity: 'Action',
        reference_type: 'POReceipt',
        reference_id: Number(row.PORID),
        reference_key: String(row.PORDID),
        sku_code: String(row.ItemSKU).trim(),
        facility_code: String(row.Facility || 'CART'),
        qty,
        source_status: status || String(row.PORStatus || ''),
        ims_status: 'Not posted',
        qbo_status: qboStatus || 'Not posted',
        recommended_action: 'Run Inventory Receipt Sync (or confirm Jazz ASN received).',
        detail_message: `ASN ${row.JazzASN || '—'} / POR ${row.PORID} line ${row.PORDID} missing IMS post.`,
      });
      continue;
    }

    if (qboStatus === 'Error') {
      lines.push({
        movement_type: 'Receipt',
        action_code: 'RECEIPT_QBO_ERROR',
        severity: 'Action',
        reference_type: 'POReceipt',
        reference_id: Number(row.PORID),
        reference_key: String(row.PORDID),
        sku_code: String(row.ItemSKU).trim(),
        facility_code: String(row.Facility || 'CART'),
        qty,
        source_status: status || String(row.PORStatus || ''),
        ims_status: 'Posted',
        qbo_status: `Error: ${String(row.QboSyncError || '').slice(0, 120)}`,
        recommended_action: 'Fix QBO item mapping / accounts, then rerun Inventory Receipt Sync.',
        detail_message: `${doc} QBO sync error after IMS post.`,
      });
      continue;
    }

    if (qboStatus !== 'Synced') {
      lines.push({
        movement_type: 'Receipt',
        action_code: 'RECEIPT_MISSING_QBO',
        severity: 'Action',
        reference_type: 'POReceipt',
        reference_id: Number(row.PORID),
        reference_key: String(row.PORDID),
        sku_code: String(row.ItemSKU).trim(),
        facility_code: String(row.Facility || 'CART'),
        qty,
        source_status: status || String(row.PORStatus || ''),
        ims_status: 'Posted',
        qbo_status: qboStatus || 'Not posted',
        recommended_action: 'Run Inventory Receipt Sync to post QBO InventoryAdjustment.',
        detail_message: `${doc} IMS posted but QBO not Synced.`,
      });
    }
  }

  return lines;
}

async function collectSaleExceptions(pool, lookback) {
  const result = await pool.request()
    .input('days', sql.Int, lookback)
    .query(`
      SELECT TOP (2000)
        h.AccsSalesOrderHeaderID,
        h.IncrementId,
        h.OrderStatus,
        h.OrderCreatedAt,
        d.AccsSalesOrderDetailID,
        d.SKU,
        d.QtyShipped,
        d.QtyInvoiced,
        d.QtyOrdered,
        d.FulfillmentAttr,
        l.SyncStatus AS QboSyncStatus,
        l.SyncError AS QboSyncError,
        l.DocNumber,
        ims.TransactionID AS ImsTransactionID
      FROM dbo.AccsSalesOrderHeader h
      INNER JOIN dbo.AccsSalesOrderDetail d ON d.AccsSalesOrderHeaderID = h.AccsSalesOrderHeaderID
      LEFT JOIN dbo.QBOInventorySyncLog l
        ON l.DocNumber = CONCAT(N'NA-SAL-', h.AccsSalesOrderHeaderID, N'-', d.AccsSalesOrderDetailID)
      LEFT JOIN dbo.InvTransaction ims
        ON ims.ReferenceType = N'AccsSalesOrderHeader'
       AND ims.ReferenceID = h.AccsSalesOrderHeaderID
       AND ims.TransactionType = N'Sale'
      WHERE h.OrderCreatedAt >= DATEADD(DAY, -@days, SYSUTCDATETIME())
        AND (
          LOWER(COALESCE(h.OrderStatus, N'')) LIKE N'%complete%'
          OR LOWER(COALESCE(h.OrderStatus, N'')) LIKE N'%ship%'
          OR LOWER(COALESCE(h.OrderStatus, N'')) LIKE N'%closed%'
          OR COALESCE(d.QtyShipped, 0) > 0
        )
        AND COALESCE(d.SKU, N'') <> N''
      ORDER BY h.AccsSalesOrderHeaderID DESC, d.AccsSalesOrderDetailID
    `);

  const lines = [];
  for (const row of result.recordset) {
    let qty = Number(row.QtyShipped || 0);
    if (!(qty > 0)) {
      qty = Number(row.QtyInvoiced || 0);
    }
    if (!(qty > 0)) {
      qty = Number(row.QtyOrdered || 0);
    }
    if (!(qty > 0)) {
      continue;
    }

    const imsPosted = row.ImsTransactionID != null;
    const qboStatus = String(row.QboSyncStatus || '');
    const doc = String(row.DocNumber || `NA-SAL-${row.AccsSalesOrderHeaderID}-${row.AccsSalesOrderDetailID}`);
    const facility = String(row.FulfillmentAttr || 'CART');

    if (!imsPosted) {
      lines.push({
        movement_type: 'Sale',
        action_code: 'SALE_MISSING_IMS',
        severity: 'Action',
        reference_type: 'AccsSalesOrderHeader',
        reference_id: Number(row.AccsSalesOrderHeaderID),
        reference_key: String(row.AccsSalesOrderDetailID),
        sku_code: String(row.SKU).trim(),
        facility_code: facility,
        qty,
        source_status: String(row.OrderStatus || ''),
        ims_status: 'Not posted',
        qbo_status: qboStatus || 'Not posted',
        recommended_action: 'Run Inventory Sales Sync for shipped ACCS orders.',
        detail_message: `Order ${row.IncrementId || row.AccsSalesOrderHeaderID} detail ${row.AccsSalesOrderDetailID} missing IMS sale post.`,
      });
      continue;
    }

    if (qboStatus === 'Error') {
      lines.push({
        movement_type: 'Sale',
        action_code: 'SALE_QBO_ERROR',
        severity: 'Action',
        reference_type: 'AccsSalesOrderHeader',
        reference_id: Number(row.AccsSalesOrderHeaderID),
        reference_key: String(row.AccsSalesOrderDetailID),
        sku_code: String(row.SKU).trim(),
        facility_code: facility,
        qty,
        source_status: String(row.OrderStatus || ''),
        ims_status: 'Posted',
        qbo_status: `Error: ${String(row.QboSyncError || '').slice(0, 120)}`,
        recommended_action: 'Fix QBO Inventory item mapping, then rerun Inventory Sales Sync.',
        detail_message: `${doc} QBO sync error after IMS sale.`,
      });
      continue;
    }

    if (qboStatus !== 'Synced') {
      lines.push({
        movement_type: 'Sale',
        action_code: 'SALE_MISSING_QBO',
        severity: 'Action',
        reference_type: 'AccsSalesOrderHeader',
        reference_id: Number(row.AccsSalesOrderHeaderID),
        reference_key: String(row.AccsSalesOrderDetailID),
        sku_code: String(row.SKU).trim(),
        facility_code: facility,
        qty,
        source_status: String(row.OrderStatus || ''),
        ims_status: 'Posted',
        qbo_status: qboStatus || 'Not posted',
        recommended_action: 'Run Inventory Sales Sync to post QBO InventoryAdjustment (−qty).',
        detail_message: `${doc} IMS posted but QBO not Synced.`,
      });
    }
  }

  return lines;
}

async function collectTransferExceptions(pool, lookback) {
  const result = await pool.request()
    .input('days', sql.Int, lookback)
    .query(`
      SELECT
        t.TransferID,
        t.SKUCode,
        t.FromFacilityCode,
        t.ToFacilityCode,
        t.QtyRequested,
        t.QtyShipped,
        t.QtyReceived,
        t.TransferStatus,
        t.OutboundTransactionID,
        t.InboundTransactionID,
        t.CreateDate,
        l.SyncStatus AS QboSyncStatus,
        l.SyncError AS QboSyncError,
        l.DocNumber
      FROM dbo.InvTransfer t
      LEFT JOIN dbo.QBOInventorySyncLog l
        ON l.DocNumber = CONCAT(N'NA-TRF-', t.TransferID)
      WHERE t.CreateDate >= DATEADD(DAY, -@days, SYSUTCDATETIME())
      ORDER BY t.TransferID DESC
    `);

  const lines = [];
  for (const row of result.recordset) {
    const status = String(row.TransferStatus || '');
    const shipped = Number(row.QtyShipped || 0) > 0
      || ['Shipped', 'InTransit', 'PartiallyReceived', 'Received', 'Complete'].includes(status);
    const received = Number(row.QtyReceived || 0) > 0
      || ['Received', 'PartiallyReceived', 'Complete'].includes(status);

    if (shipped && !row.OutboundTransactionID) {
      lines.push({
        movement_type: 'Transfer',
        action_code: 'TRANSFER_MISSING_SHIP_IMS',
        severity: 'Action',
        reference_type: 'InvTransfer',
        reference_id: Number(row.TransferID),
        reference_key: null,
        sku_code: String(row.SKUCode || ''),
        facility_code: String(row.FromFacilityCode || ''),
        qty: Number(row.QtyShipped || row.QtyRequested || 0),
        source_status: status,
        ims_status: 'Ship not posted',
        qbo_status: String(row.QboSyncStatus || '—'),
        recommended_action: 'Open Facility Transfers and complete/re-post ship for this transfer.',
        detail_message: `Transfer ${row.TransferID} ${row.FromFacilityCode}→${row.ToFacilityCode} missing outbound IMS txn.`,
      });
    }

    if (received && !row.InboundTransactionID) {
      lines.push({
        movement_type: 'Transfer',
        action_code: 'TRANSFER_MISSING_RECEIVE_IMS',
        severity: 'Action',
        reference_type: 'InvTransfer',
        reference_id: Number(row.TransferID),
        reference_key: null,
        sku_code: String(row.SKUCode || ''),
        facility_code: String(row.ToFacilityCode || ''),
        qty: Number(row.QtyReceived || row.QtyShipped || 0),
        source_status: status,
        ims_status: 'Receive not posted',
        qbo_status: String(row.QboSyncStatus || '—'),
        recommended_action: 'Open Facility Transfers and complete receive for this transfer.',
        detail_message: `Transfer ${row.TransferID} missing inbound IMS txn.`,
      });
    }

    if (shipped && String(row.QboSyncStatus || '') === 'Error') {
      lines.push({
        movement_type: 'Transfer',
        action_code: 'TRANSFER_QBO_JE_ERROR',
        severity: 'Warning',
        reference_type: 'InvTransfer',
        reference_id: Number(row.TransferID),
        reference_key: String(row.DocNumber || `NA-TRF-${row.TransferID}`),
        sku_code: String(row.SKUCode || ''),
        facility_code: String(row.FromFacilityCode || ''),
        qty: Number(row.QtyShipped || 0),
        source_status: status,
        ims_status: row.OutboundTransactionID ? 'Ship posted' : 'Ship missing',
        qbo_status: `Error: ${String(row.QboSyncError || '').slice(0, 120)}`,
        recommended_action: 'Check QBO_INV_ASSET_ACCOUNT_* settings and rerun transfer JE posting.',
        detail_message: `Transfer ${row.TransferID} QBO journal entry failed.`,
      });
    }
  }

  return lines;
}

async function collectAdjustmentExceptions(pool, lookback) {
  const result = await pool.request()
    .input('days', sql.Int, lookback)
    .query(`
      SELECT
        a.AdjustmentID,
        a.SKUCode,
        a.FacilityCode,
        a.QtyAdjusted,
        a.AdjStatus,
        a.TransactionID,
        a.AdjustmentDate,
        rc.ReasonCode
      FROM dbo.InvAdjustment a
      LEFT JOIN dbo.InvReasonCode rc ON rc.ReasonCodeID = a.ReasonCodeID
      WHERE a.CreateDate >= DATEADD(DAY, -@days, SYSUTCDATETIME())
        AND a.AdjStatus IN (N'Pending', N'Approved')
      ORDER BY a.AdjustmentID DESC
    `);

  const lines = [];
  for (const row of result.recordset) {
    const status = String(row.AdjStatus || '');
    if (status === 'Pending') {
      lines.push({
        movement_type: 'Adjustment',
        action_code: 'ADJUSTMENT_PENDING',
        severity: 'Warning',
        reference_type: 'InvAdjustment',
        reference_id: Number(row.AdjustmentID),
        reference_key: String(row.ReasonCode || ''),
        sku_code: String(row.SKUCode || ''),
        facility_code: String(row.FacilityCode || ''),
        qty: Number(row.QtyAdjusted || 0),
        source_status: status,
        ims_status: 'Awaiting approval',
        qbo_status: '—',
        recommended_action: 'Review and approve/reject pending inventory adjustment (shrink/gain workflow).',
        detail_message: `Adjustment ${row.AdjustmentID} still Pending.`,
      });
      continue;
    }

    if (status === 'Approved' && row.TransactionID == null) {
      lines.push({
        movement_type: 'Adjustment',
        action_code: 'ADJUSTMENT_APPROVED_UNPOSTED',
        severity: 'Action',
        reference_type: 'InvAdjustment',
        reference_id: Number(row.AdjustmentID),
        reference_key: String(row.ReasonCode || ''),
        sku_code: String(row.SKUCode || ''),
        facility_code: String(row.FacilityCode || ''),
        qty: Number(row.QtyAdjusted || 0),
        source_status: status,
        ims_status: 'Approved, not posted',
        qbo_status: 'Not posted',
        recommended_action: 'Post approved adjustment to IMS + QBO (shrink/gain posting workflow).',
        detail_message: `Adjustment ${row.AdjustmentID} approved but has no InvTransaction.`,
      });
    }
  }

  return lines;
}

async function run(params = {}) {
  const lookback = lookbackDays(params);
  const triggerType = String(params.trigger_type || params.triggerType || 'Scheduled');
  const userId = Number(params.triggered_by_user_id || params.triggeredByUserId || 0) || null;
  const pool = await connectPool(inventoryDatabase());
  let runId = 0;

  try {
    runId = await insertRun(pool, triggerType === 'Manual' ? 'Manual' : 'Scheduled', lookback, userId);
    if (!runId) {
      return { ok: false, error: 'Unable to create recon run.', processed: 0 };
    }

    const receiptLines = await collectReceiptExceptions(pool, lookback);
    const saleLines = await collectSaleExceptions(pool, lookback);
    const transferLines = await collectTransferExceptions(pool, lookback);
    const adjustmentLines = await collectAdjustmentExceptions(pool, lookback);
    const all = [...receiptLines, ...saleLines, ...transferLines, ...adjustmentLines];

    for (const line of all) {
      await insertLine(pool, runId, line);
    }

    const counts = {
      receipt: receiptLines.length,
      sale: saleLines.length,
      transfer: transferLines.length,
      adjustment: adjustmentLines.length,
      total: all.length,
    };
    const summary = `Lookback ${lookback}d — ${counts.total} exceptions `
      + `(receipts ${counts.receipt}, sales ${counts.sale}, transfers ${counts.transfer}, adjustments ${counts.adjustment}).`;

    await finishRun(pool, runId, true, counts, summary, null);

    return {
      ok: true,
      error: null,
      recon_run_id: runId,
      lookback_days: lookback,
      ...counts,
      summary,
    };
  } catch (error) {
    if (runId > 0) {
      await finishRun(pool, runId, false, {
        receipt: 0, sale: 0, transfer: 0, adjustment: 0, total: 0,
      }, null, String(error.message || error).slice(0, 500));
    }
    return {
      ok: false,
      error: error.message || String(error),
      recon_run_id: runId || null,
    };
  } finally {
    await pool.close();
  }
}

module.exports = { run };
