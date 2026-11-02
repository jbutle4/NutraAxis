const { sql, connectPool, getProductionDatabase } = require('../db-config');
const qboConfig = require('../qbo-config');
const { mapAccsDbOrderToSalesReceipt } = require('../qbo-order-mapper');
const { createSalesReceipt } = require('../qbo-sales-receipt');
const {
  salesReceiptDocNumber,
  writeLog,
  hasSyncedSalesReceipt,
  hasSaleReversal,
} = require('../qbo-sales-sync-log');
const { imsLedgerProfile, accsSourceEnvironment, imsEnvironmentSummary } = require('../ims-ledger-profile');

function inventoryDatabase() {
  return process.env.DB_NAME_INVENTORY_SYNC
    || getProductionDatabase()
    || 'nutraaxis';
}

function productionSalesMode() {
  return qboConfig.environment() === 'production';
}

async function loadCandidateOrders(pool, sourceEnvironment) {
  const result = await pool.request()
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
      WHERE h.SourceEnvironment = @sourceEnvironment
        AND (
          LOWER(COALESCE(h.OrderStatus, N'')) LIKE N'%complete%'
          OR LOWER(COALESCE(h.OrderStatus, N'')) LIKE N'%ship%'
          OR LOWER(COALESCE(h.OrderStatus, N'')) LIKE N'%closed%'
          OR COALESCE(d.QtyShipped, 0) > 0
        )
        AND COALESCE(d.SKU, N'') <> N''
      ORDER BY h.AccsSalesOrderHeaderID DESC, d.AccsSalesOrderDetailID
    `);

  const byHeader = new Map();
  for (const row of result.recordset) {
    const headerId = Number(row.AccsSalesOrderHeaderID);
    if (!byHeader.has(headerId)) {
      byHeader.set(headerId, { header: row, details: [] });
    }
    byHeader.get(headerId).details.push(row);
  }
  return byHeader;
}

async function run() {
  if (!productionSalesMode()) {
    return {
      ok: true,
      skipped: true,
      message: 'accs-sales-receipt-sync runs only when QBO_ENVIRONMENT=production.',
      processed: 0,
      posted: 0,
      skipped_orders: 0,
      failed: 0,
    };
  }

  const configError = qboConfig.configError();
  if (configError) {
    return { ok: false, error: configError, processed: 0, posted: 0, skipped_orders: 0, failed: 0 };
  }

  const pool = await connectPool(inventoryDatabase());
  const ledgerProfile = imsLedgerProfile();
  const sourceEnvironment = accsSourceEnvironment();
  const environment = imsEnvironmentSummary();
  let processed = 0;
  let posted = 0;
  let skippedOrders = 0;
  let failed = 0;
  const failures = [];

  try {
    const orders = await loadCandidateOrders(pool, sourceEnvironment);

    for (const [headerId, group] of orders.entries()) {
      if (await hasSyncedSalesReceipt(pool, headerId, ledgerProfile)) {
        skippedOrders += 1;
        continue;
      }

      processed += 1;
      const mapped = mapAccsDbOrderToSalesReceipt(group.header, group.details, {
        useTodayTxnDate: await hasSaleReversal(pool, headerId, ledgerProfile),
      });
      if (!mapped.ok) {
        failed += 1;
        failures.push({ header_id: headerId, error: mapped.error });
        continue;
      }

      try {
        const result = await createSalesReceipt(mapped.payload, { log: () => {} }, {
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
          facility_code: String(group.details[0]?.FulfillmentAttr || 'CART').slice(0, 50),
          qbo_txn_id: result.transaction_id || null,
          qbo_sync_token: result.sync_token || null,
          sync_status: 'Synced',
          sync_error: result.duplicate ? 'Duplicate QBO SalesReceipt (idempotent).' : null,
          ledger_profile: ledgerProfile,
        });

        posted += 1;
      } catch (error) {
        failed += 1;
        failures.push({ header_id: headerId, increment_id: group.header.IncrementId, error: error.message });
        await writeLog(pool, {
          doc_number: salesReceiptDocNumber(String(group.header.IncrementId || '')),
          sync_type: 'SaleReceipt',
          reference_type: 'AccsSalesOrderHeader',
          reference_id: headerId,
          reference_line_key: String(group.header.IncrementId || ''),
          sku_code: String(group.details[0]?.SKU || 'ORDER'),
          qty_change: 0,
          facility_code: null,
          qbo_txn_id: null,
          qbo_sync_token: null,
          sync_status: 'Error',
          sync_error: String(error.message || 'Sales receipt failed').slice(0, 500),
          ledger_profile: ledgerProfile,
        });
      }
    }

    return {
      ok: failed === 0,
      error: failed === 0 ? null : `${failed} sales receipt failure(s).`,
      environment,
      processed,
      posted,
      skipped_orders: skippedOrders,
      failed,
      failures: failures.slice(0, 25),
    };
  } finally {
    await pool.close();
  }
}

module.exports = { run };
