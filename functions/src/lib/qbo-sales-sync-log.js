const { sql } = require('./db-config');
const { imsLedgerProfile } = require('./ims-ledger-profile');

function salesReceiptDocNumber(incrementId) {
  return `SR-${String(incrementId ?? '').trim()}`;
}

function saleReversalDocNumber(headerId) {
  return `NA-SAL-REV-${Number(headerId)}`;
}

function buildReversalPrivateNote({
  headerId,
  incrementId,
  originalTxnId,
  originalDocNumber,
}) {
  const inc = String(incrementId || headerId).trim();
  const origDoc = String(originalDocNumber || `NA-SAL-${headerId}`).trim();
  const origTxn = String(originalTxnId || '').trim();
  return [
    'NutraAxis cutover: reversing mistaken InventoryAdjustment sale post.',
    `Original DocNumber ${origDoc}${origTxn ? `, QBO IA Id ${origTxn}` : ''}.`,
    `ACCS order ${inc}.`,
    'Production sales now post as Sales Receipt with Inventory items (qty + revenue).',
    'IMS ledger unchanged; only QBO quantity was double-counted via adjustment.',
  ].join(' ');
}

async function writeLog(pool, row) {
  const ledgerProfile = row.ledger_profile || imsLedgerProfile();
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
    .input('ledgerProfile', sql.NVarChar(20), ledgerProfile)
    .query(`
      MERGE dbo.QBOInventorySyncLog AS target
      USING (SELECT @doc AS DocNumber) AS source
        ON target.DocNumber = source.DocNumber
      WHEN MATCHED THEN
        UPDATE SET
          SyncType = @syncType,
          ReferenceType = @refType,
          ReferenceID = @refId,
          ReferenceLineKey = @lineKey,
          SKUCode = @sku,
          QtyChange = @qty,
          FacilityCode = @facility,
          QBO_TxnId = @txnId,
          QBO_SyncToken = @syncToken,
          SyncStatus = @status,
          SyncError = @error,
          LedgerProfile = @ledgerProfile,
          SyncedAt = CASE WHEN @status = N'Synced' THEN SYSUTCDATETIME() ELSE NULL END
      WHEN NOT MATCHED THEN
        INSERT (
          DocNumber, SyncType, ReferenceType, ReferenceID, ReferenceLineKey,
          SKUCode, QtyChange, FacilityCode, QBO_TxnId, QBO_SyncToken,
          SyncStatus, SyncError, SyncedAt, LedgerProfile
        )
        VALUES (
          @doc, @syncType, @refType, @refId, @lineKey,
          @sku, @qty, @facility, @txnId, @syncToken,
          @status, @error, CASE WHEN @status = N'Synced' THEN SYSUTCDATETIME() ELSE NULL END,
          @ledgerProfile
        );
    `);
}

async function hasSyncedSalesReceipt(pool, headerId, ledgerProfile) {
  const result = await pool.request()
    .input('refId', sql.Int, headerId)
    .input('ledgerProfile', sql.NVarChar(20), ledgerProfile)
    .query(`
      SELECT TOP (1) 1 AS ok
      FROM dbo.QBOInventorySyncLog
      WHERE ReferenceType = N'AccsSalesOrderHeader'
        AND ReferenceID = @refId
        AND SyncType = N'SaleReceipt'
        AND SyncStatus = N'Synced'
        AND LedgerProfile = @ledgerProfile
    `);
  return Boolean(result.recordset[0]);
}

async function markSaleAdjustmentsSuperseded(pool, headerId, reason, ledgerProfile) {
  await pool.request()
    .input('headerId', sql.Int, headerId)
    .input('reason', sql.NVarChar(500), String(reason || '').slice(0, 500))
    .input('ledgerProfile', sql.NVarChar(20), ledgerProfile)
    .query(`
      UPDATE dbo.QBOInventorySyncLog
      SET SyncStatus = N'Superseded',
          SyncError = @reason
      WHERE ReferenceLineKey = CAST(@headerId AS NVARCHAR(100))
        AND SyncType = N'Sale'
        AND SyncStatus = N'Synced'
        AND LedgerProfile = @ledgerProfile
    `);
}

async function hasSaleReversal(pool, headerId, ledgerProfile) {
  const result = await pool.request()
    .input('refId', sql.Int, headerId)
    .input('ledgerProfile', sql.NVarChar(20), ledgerProfile)
    .query(`
      SELECT TOP (1) 1 AS ok
      FROM dbo.QBOInventorySyncLog
      WHERE ReferenceType = N'AccsSalesOrderHeader'
        AND ReferenceID = @refId
        AND SyncType = N'SaleReversal'
        AND SyncStatus = N'Synced'
        AND LedgerProfile = @ledgerProfile
    `);
  return Boolean(result.recordset[0]);
}

async function markSalesReceiptSuperseded(pool, headerId, reason, ledgerProfile) {
  await pool.request()
    .input('refId', sql.Int, headerId)
    .input('reason', sql.NVarChar(500), String(reason || '').slice(0, 500))
    .input('ledgerProfile', sql.NVarChar(20), ledgerProfile)
    .query(`
      UPDATE dbo.QBOInventorySyncLog
      SET SyncStatus = N'Superseded',
          SyncError = @reason
      WHERE ReferenceType = N'AccsSalesOrderHeader'
        AND ReferenceID = @refId
        AND SyncType = N'SaleReceipt'
        AND SyncStatus = N'Synced'
        AND LedgerProfile = @ledgerProfile
    `);
}

module.exports = {
  salesReceiptDocNumber,
  saleReversalDocNumber,
  buildReversalPrivateNote,
  writeLog,
  hasSyncedSalesReceipt,
  hasSaleReversal,
  markSaleAdjustmentsSuperseded,
  markSalesReceiptSuperseded,
};
