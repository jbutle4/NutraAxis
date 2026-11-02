const { sql, connectPool, getProductionDatabase } = require('../db-config');
const qboConfig = require('../qbo-config');
const qboInventory = require('../qbo-inventory-adjustment');
const { mapAccsDbOrderToSalesReceipt } = require('../qbo-order-mapper');
const { createSalesReceipt } = require('../qbo-sales-receipt');
const {
  salesReceiptDocNumber,
  saleReversalDocNumber,
  buildReversalPrivateNote,
  writeLog,
  hasSyncedSalesReceipt,
  markSaleAdjustmentsSuperseded,
} = require('../qbo-sales-sync-log');
const { imsLedgerProfile, accsSourceEnvironment } = require('../ims-ledger-profile');

function inventoryDatabase() {
  return process.env.DB_NAME_INVENTORY_SYNC
    || getProductionDatabase()
    || 'nutraaxis';
}

function adjustAccountId() {
  return qboConfig.invAccountSetting(
    'QBO_INV_ADJUST_ACCOUNT_ID',
    qboConfig.invAccountSetting('QBO_INV_ASSET_ACCOUNT_CART')
  );
}

async function loadMistakenSaleAdjustments(pool, ledgerProfile) {
  const result = await pool.request()
    .input('ledgerProfile', sql.NVarChar(20), ledgerProfile)
    .query(`
      SELECT
        l.ReferenceLineKey AS HeaderIdText,
        l.ReferenceID AS DetailId,
        l.SKUCode,
        l.QtyChange,
        l.QBO_TxnId,
        l.DocNumber,
        h.IncrementId,
        h.AccsSalesOrderHeaderID
      FROM dbo.QBOInventorySyncLog l
      LEFT JOIN dbo.AccsSalesOrderDetail d ON d.AccsSalesOrderDetailID = l.ReferenceID
      LEFT JOIN dbo.AccsSalesOrderHeader h ON h.AccsSalesOrderHeaderID = d.AccsSalesOrderHeaderID
      WHERE l.SyncType = N'Sale'
        AND l.SyncStatus = N'Synced'
        AND l.LedgerProfile = @ledgerProfile
        AND l.QBO_TxnId IS NOT NULL
      ORDER BY h.AccsSalesOrderHeaderID, l.DocNumber
    `);

  const byHeader = new Map();
  for (const row of result.recordset) {
    const headerId = Number(row.AccsSalesOrderHeaderID || row.HeaderIdText || 0);
    if (!headerId) {
      continue;
    }
    if (!byHeader.has(headerId)) {
      byHeader.set(headerId, {
        header_id: headerId,
        increment_id: String(row.IncrementId || ''),
        txn_id: String(row.QBO_TxnId || ''),
        lines: [],
      });
    }
    const group = byHeader.get(headerId);
    if (!group.txn_id && row.QBO_TxnId) {
      group.txn_id = String(row.QBO_TxnId);
    }
    group.lines.push({
      sku: String(row.SKUCode || '').trim(),
      qty_change: Number(row.QtyChange || 0),
      doc_number: String(row.DocNumber || ''),
    });
  }
  return byHeader;
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

async function hasSyncedReversal(pool, headerId, ledgerProfile) {
  const doc = saleReversalDocNumber(headerId);
  const result = await pool.request()
    .input('doc', sql.NVarChar(50), doc)
    .input('ledgerProfile', sql.NVarChar(20), ledgerProfile)
    .query(`
      SELECT TOP (1) 1 AS ok
      FROM dbo.QBOInventorySyncLog
      WHERE DocNumber = @doc
        AND SyncType = N'SaleReversal'
        AND SyncStatus = N'Synced'
        AND LedgerProfile = @ledgerProfile
    `);
  return Boolean(result.recordset[0]);
}

async function run(options = {}) {
  const dryRun = Boolean(options.dry_run ?? options.dryRun);

  if (qboConfig.environment() !== 'production') {
    return {
      ok: false,
      error: 'qbo-sales-cutover runs only when QBO_ENVIRONMENT=production.',
      dry_run: dryRun,
    };
  }

  const accountId = adjustAccountId();
  if (!accountId) {
    return {
      ok: false,
      error: 'Set QBO_INV_ADJUST_ACCOUNT_ID (or QBO_INV_ASSET_ACCOUNT_CART) for production.',
      dry_run: dryRun,
    };
  }

  const pool = await connectPool(inventoryDatabase());
  const ledgerProfile = imsLedgerProfile();
  const sourceEnvironment = accsSourceEnvironment();
  let reversed = 0;
  let receiptsPosted = 0;
  let skipped = 0;
  let failed = 0;
  const failures = [];

  try {
    const mistaken = await loadMistakenSaleAdjustments(pool, ledgerProfile);

    for (const [headerId, group] of mistaken.entries()) {
      const reversalDoc = saleReversalDocNumber(headerId);
      const alreadyReversed = await hasSyncedReversal(pool, headerId, ledgerProfile);
      const alreadyReceipt = await hasSyncedSalesReceipt(pool, headerId, ledgerProfile);

      if (alreadyReversed && alreadyReceipt) {
        skipped += 1;
        continue;
      }

      const qboLines = [];
      let missingItem = false;
      for (const line of group.lines) {
        if (dryRun) {
          const qty = Math.abs(Number(line.qty_change || 0));
          if (qty > 0) {
            qboLines.push({ qbo_item_id: 'dry-run', qty_change: qty, sku: line.sku });
          }
          continue;
        }

        const resolved = await qboInventory.resolveInventoryItemId(pool, sql, line.sku);
        if (resolved.skip) {
          continue;
        }
        const itemId = resolved.ok ? String(resolved.item_id || '').trim() : '';
        if (!itemId) {
          missingItem = true;
          failures.push({
            header_id: headerId,
            step: 'reversal',
            sku: line.sku,
            error: resolved.error || 'Missing QBO Inventory item.',
          });
          break;
        }
        const qty = Math.abs(Number(line.qty_change || 0));
        if (qty > 0) {
          qboLines.push({ qbo_item_id: itemId, qty_change: qty });
        }
      }

      if (missingItem) {
        failed += 1;
        continue;
      }

      if (qboLines.length === 0) {
        skipped += 1;
        continue;
      }

      const privateNote = buildReversalPrivateNote({
        headerId,
        incrementId: group.increment_id,
        originalTxnId: group.txn_id,
        originalDocNumber: `NA-SAL-${headerId}`,
      });

      if (dryRun) {
        if (!alreadyReversed) {
          reversed += 1;
        }
        if (!alreadyReceipt) {
          receiptsPosted += 1;
        }
        continue;
      }

      if (!alreadyReversed) {
        const reversal = await qboInventory.postInventoryAdjustment({
          docNumber: reversalDoc,
          lines: qboLines,
          adjustAccountId: accountId,
          privateNote,
        });

        if (!reversal.ok) {
          failed += 1;
          failures.push({ header_id: headerId, step: 'reversal', error: reversal.error });
          continue;
        }

        const totalQty = qboLines.reduce((sum, line) => sum + Number(line.qty_change || 0), 0);
        await writeLog(pool, {
          doc_number: reversalDoc,
          sync_type: 'SaleReversal',
          reference_type: 'AccsSalesOrderHeader',
          reference_id: headerId,
          reference_line_key: group.txn_id,
          sku_code: group.lines[0]?.sku || 'ORDER',
          qty_change: totalQty,
          facility_code: null,
          qbo_txn_id: String(reversal.txn?.Id || ''),
          qbo_sync_token: String(reversal.txn?.SyncToken || ''),
          sync_status: 'Synced',
          sync_error: privateNote.slice(0, 500),
          ledger_profile: ledgerProfile,
        });

        await markSaleAdjustmentsSuperseded(
          pool,
          headerId,
          `Superseded by ${reversalDoc}: ${privateNote}`,
          ledgerProfile
        );
        reversed += 1;
      } else if (alreadyReversed) {
        reversed += 1;
      }

      if (!alreadyReceipt) {
        const orderGroup = await loadOrderGroup(pool, headerId, sourceEnvironment);
        if (!orderGroup) {
          failed += 1;
          failures.push({ header_id: headerId, step: 'receipt', error: 'Order not found in AccsSalesOrderHeader.' });
          continue;
        }

        const mapped = mapAccsDbOrderToSalesReceipt(orderGroup.header, orderGroup.details, {
          useTodayTxnDate: true,
        });
        if (!mapped.ok) {
          failed += 1;
          failures.push({ header_id: headerId, step: 'receipt', error: mapped.error });
          continue;
        }

        try {
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
            sync_error: receipt.duplicate ? 'Posted during cutover (duplicate idempotent).' : null,
            ledger_profile: ledgerProfile,
          });
          receiptsPosted += 1;
        } catch (error) {
          failed += 1;
          failures.push({
            header_id: headerId,
            increment_id: group.increment_id,
            step: 'receipt',
            error: error.message,
          });
        }
      } else if (alreadyReceipt) {
        receiptsPosted += 1;
      }
    }

    return {
      ok: failed === 0,
      dry_run: dryRun,
      error: failed === 0 ? null : `${failed} cutover failure(s).`,
      orders_found: mistaken.size,
      reversed,
      receipts_posted: receiptsPosted,
      skipped,
      failed,
      failures: failures.slice(0, 25),
    };
  } finally {
    await pool.close();
  }
}

module.exports = { run };
