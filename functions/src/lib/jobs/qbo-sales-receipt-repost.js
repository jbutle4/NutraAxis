const { sql, connectPool, getProductionDatabase } = require('../db-config');
const qboConfig = require('../qbo-config');
const { mapAccsDbOrderToSalesReceipt } = require('../qbo-order-mapper');
const { createSalesReceipt, voidAndDeleteSalesReceipt, findSalesReceiptByDocNumber } = require('../qbo-sales-receipt');
const {
  salesReceiptDocNumber,
  writeLog,
  hasSaleReversal,
  markSalesReceiptSuperseded,
} = require('../qbo-sales-sync-log');
const { imsLedgerProfile, accsSourceEnvironment } = require('../ims-ledger-profile');

const VOID_NOTE = 'Voided 2026-08-16: reposting Sales Receipt with corrected ACCS discounts, shipping, and tax.';

function inventoryDatabase() {
  return process.env.DB_NAME_INVENTORY_SYNC
    || getProductionDatabase()
    || 'nutraaxis';
}

async function loadReceiptLogsForRepost(pool, ledgerProfile) {
  const result = await pool.request()
    .input('ledgerProfile', sql.NVarChar(20), ledgerProfile)
    .query(`
      SELECT
        sr.SyncLogID,
        sr.ReferenceID AS HeaderId,
        sr.ReferenceLineKey AS IncrementId,
        sr.QBO_TxnId,
        sr.DocNumber,
        sr.SyncStatus
      FROM dbo.QBOInventorySyncLog sr
      WHERE sr.SyncType = N'SaleReceipt'
        AND sr.SyncStatus IN (N'Synced', N'Superseded')
        AND sr.LedgerProfile = @ledgerProfile
      ORDER BY sr.ReferenceID
    `);
  return result.recordset;
}

async function loadOrderGroup(pool, headerId, sourceEnvironment) {
  const result = await pool.request()
    .input('headerId', sql.Int, headerId)
    .input('sourceEnvironment', sql.NVarChar(20), sourceEnvironment)
    .query(`
      SELECT
        h.AccsSalesOrderHeaderID,
        h.AccsEntityId,
        h.IncrementId,
        h.OrderStatus,
        h.OrderCreatedAt,
        h.SourceEnvironment,
        h.CustomerEmail,
        h.CustomerFirstName,
        h.CustomerLastName,
        h.BillEmail,
        h.BillFirstName,
        h.BillLastName,
        h.GrandTotal,
        h.TotalInvoiced,
        h.TaxAmount AS HeaderTaxAmount,
        h.DiscountAmount AS HeaderDiscountAmount,
        h.ShippingAmount AS HeaderShippingAmount,
        h.ShippingDescription AS HeaderShippingDescription,
        h.PaymentMethod,
        d.AccsSalesOrderDetailID,
        d.SKU,
        d.ProductName,
        d.QtyOrdered,
        d.QtyShipped,
        d.QtyInvoiced,
        d.OriginalPrice,
        d.UnitPrice,
        d.RowTotal,
        d.DiscountAmount,
        d.TaxAmount,
        d.FulfillmentAttr
      FROM dbo.AccsSalesOrderHeader h
      INNER JOIN dbo.AccsSalesOrderDetail d ON d.AccsSalesOrderHeaderID = h.AccsSalesOrderHeaderID
      WHERE h.AccsSalesOrderHeaderID = @headerId
        AND h.SourceEnvironment = @sourceEnvironment
      ORDER BY d.AccsSalesOrderDetailID
    `);

  if (!result.recordset.length) {
    return null;
  }

  return {
    header: result.recordset[0],
    details: result.recordset,
  };
}

async function run(options = {}) {
  const dryRun = Boolean(options.dry_run ?? options.dryRun);

  if (qboConfig.environment() !== 'production') {
    return {
      ok: false,
      error: 'qbo-sales-receipt-repost runs only when QBO_ENVIRONMENT=production.',
      dry_run: dryRun,
    };
  }

  const configError = qboConfig.configError();
  if (configError) {
    return { ok: false, error: configError, dry_run: dryRun };
  }

  const pool = await connectPool(inventoryDatabase());
  const ledgerProfile = imsLedgerProfile();
  const sourceEnvironment = accsSourceEnvironment();
  let voided = 0;
  let reposted = 0;
  let skipped = 0;
  let failed = 0;
  const failures = [];
  const log = options.log || console.log;

  try {
    const logs = await loadReceiptLogsForRepost(pool, ledgerProfile);
    log('qbo-sales-receipt-repost: %s receipt(s) to process', logs.length);

    for (const row of logs) {
      const headerId = Number(row.HeaderId);
      const incrementId = String(row.IncrementId || '').trim();
      const txnId = String(row.QBO_TxnId || '').trim();
      const repostOnly = String(row.SyncStatus || '') === 'Superseded';

      if (!headerId || (!repostOnly && !txnId)) {
        skipped += 1;
        continue;
      }

      if (dryRun) {
        voided += repostOnly ? 0 : 1;
        reposted += 1;
        continue;
      }

      try {
        if (!repostOnly) {
          const voidResult = await voidAndDeleteSalesReceipt(txnId, VOID_NOTE);
          if (!voidResult.ok) {
            failed += 1;
            failures.push({
              header_id: headerId,
              increment_id: incrementId,
              step: 'void',
              error: voidResult.error,
            });
            log('void failed header=%s increment=%s error=%s', headerId, incrementId, voidResult.error);
            continue;
          }
          voided += 1;

          await markSalesReceiptSuperseded(
            pool,
            headerId,
            `${VOID_NOTE} Original QBO Id ${txnId}.`,
            ledgerProfile
          );
        }

        const orderGroup = await loadOrderGroup(pool, headerId, sourceEnvironment);
        if (!orderGroup) {
          failed += 1;
          failures.push({
            header_id: headerId,
            increment_id: incrementId,
            step: 'repost',
            error: 'Order not found in AccsSalesOrderHeader.',
          });
          continue;
        }

        const mapped = mapAccsDbOrderToSalesReceipt(orderGroup.header, orderGroup.details, {
          useTodayTxnDate: await hasSaleReversal(pool, headerId, ledgerProfile),
        });
        if (!mapped.ok) {
          failed += 1;
          failures.push({
            header_id: headerId,
            increment_id: incrementId,
            step: 'repost',
            error: mapped.error,
          });
          log('map failed header=%s increment=%s error=%s', headerId, incrementId, mapped.error);
          continue;
        }

        const existingReceipt = await findSalesReceiptByDocNumber(mapped.payload.docNumber);
        if (existingReceipt?.Id) {
          const removeResult = await voidAndDeleteSalesReceipt(
            existingReceipt.Id,
            `${VOID_NOTE} Removing existing receipt before repost.`
          );
          if (!removeResult.ok) {
            failed += 1;
            failures.push({
              header_id: headerId,
              increment_id: incrementId,
              step: 'repost',
              error: removeResult.error || 'Unable to remove existing QBO receipt before repost.',
            });
            log('remove existing failed header=%s increment=%s error=%s', headerId, incrementId, removeResult.error);
            continue;
          }
        }

        const receipt = await createSalesReceipt(mapped.payload, { log: () => {} }, {
          pool,
          sql,
          preferInventory: true,
        });

        const firstLine = mapped.payload.lines[0];
        await writeLog(pool, {
          doc_number: salesReceiptDocNumber(mapped.payload.incrementId),
          sync_type: 'SaleReceipt',
          reference_type: 'AccsSalesOrderHeader',
          reference_id: headerId,
          reference_line_key: mapped.payload.incrementId,
          sku_code: firstLine?.sku || 'ORDER',
          qty_change: mapped.payload.lines.reduce((sum, line) => sum + Number(line.qty || 0), 0) * -1,
          facility_code: String(orderGroup.details[0]?.FulfillmentAttr || 'CART').slice(0, 50),
          qbo_txn_id: receipt.transaction_id || null,
          qbo_sync_token: receipt.sync_token || null,
          sync_status: 'Synced',
          sync_error: `Reposted with discounts/shipping/tax. QBO total ${receipt.total ?? mapped.payload.total}.`,
          ledger_profile: ledgerProfile,
        });

        reposted += 1;
        log('reposted header=%s increment=%s qbo_id=%s total=%s', headerId, incrementId, receipt.transaction_id, receipt.total ?? mapped.payload.total);
      } catch (error) {
        failed += 1;
        failures.push({
          header_id: headerId,
          increment_id: incrementId,
          step: 'repost',
          error: error.message,
        });
        log('repost failed header=%s increment=%s error=%s', headerId, incrementId, error.message);
      }
    }

    return {
      ok: failed === 0,
      dry_run: dryRun,
      error: failed === 0 ? null : `${failed} sales receipt repost failure(s).`,
      receipts_found: logs.length,
      voided,
      reposted,
      skipped,
      failed,
      failures: failures.slice(0, 25),
    };
  } finally {
    await pool.close();
  }
}

module.exports = { run };
