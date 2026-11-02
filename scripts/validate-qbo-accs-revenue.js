#!/usr/bin/env node
/**
 * Compare ACCS production order revenue vs QBO Sales Receipt totals for cutover orders.
 */
const fs = require('fs');
const path = require('path');
const sql = require('mssql');

function loadEnv(filePath) {
  const vars = {};
  for (const line of fs.readFileSync(filePath, 'utf8').split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const idx = trimmed.indexOf('=');
    if (idx === -1) continue;
    let value = trimmed.slice(idx + 1).trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    vars[trimmed.slice(0, idx).trim()] = value;
  }
  return vars;
}

function applyEnv(filePath) {
  for (const [key, value] of Object.entries(loadEnv(filePath))) {
    if (process.env[key] === undefined) {
      process.env[key] = value;
    }
  }
}

applyEnv(path.join(__dirname, '..', '.env'));

const qboClient = require('../functions/src/lib/qbo-client');
const qboConnection = require('../functions/src/lib/qbo-connection');
const { escapeQboQueryValue } = require('../functions/src/lib/qbo-order-mapper');

const qboStore = qboConnection.production;

function money(n) {
  return Math.round(Number(n || 0) * 100) / 100;
}

async function loadAccsOrders(pool) {
  const result = await pool.request().query(`
    SELECT
      h.AccsSalesOrderHeaderID,
      h.IncrementId,
      h.OrderStatus,
      h.GrandTotal,
      h.TotalInvoiced,
      h.Subtotal,
      sr.QBO_TxnId,
      sr.DocNumber AS ReceiptDoc,
      SUM(COALESCE(d.RowTotal, 0)) AS LineRowTotal,
      SUM(
        COALESCE(NULLIF(d.QtyShipped, 0), NULLIF(d.QtyInvoiced, 0), d.QtyOrdered, 0)
        * COALESCE(d.UnitPrice, 0)
      ) AS LineCalcTotal
    FROM dbo.QBOInventorySyncLog sr
    INNER JOIN dbo.AccsSalesOrderHeader h
      ON h.AccsSalesOrderHeaderID = sr.ReferenceID
    INNER JOIN dbo.AccsSalesOrderDetail d
      ON d.AccsSalesOrderHeaderID = h.AccsSalesOrderHeaderID
    WHERE sr.SyncType = N'SaleReceipt'
      AND sr.SyncStatus = N'Synced'
      AND sr.ReferenceType = N'AccsSalesOrderHeader'
      AND h.SourceEnvironment = N'production'
    GROUP BY
      h.AccsSalesOrderHeaderID,
      h.IncrementId,
      h.OrderStatus,
      h.GrandTotal,
      h.TotalInvoiced,
      h.Subtotal,
      sr.QBO_TxnId,
      sr.DocNumber
    ORDER BY h.AccsSalesOrderHeaderID
  `);
  return result.recordset;
}

async function fetchQboReceipt(docNumber, txnId) {
  if (txnId) {
    const byId = await qboClient.apiRequest('GET', `/salesreceipt/${encodeURIComponent(String(txnId))}`, {
      query: { minorversion: String(qboClient.MINOR_VERSION) },
      connectionStore: qboStore,
    });
    if (byId.ok && byId.data?.SalesReceipt) {
      return byId.data.SalesReceipt;
    }
  }

  const query = await qboClient.query(
    `SELECT Id, DocNumber, TxnDate, TotalAmt, PrivateNote FROM SalesReceipt WHERE DocNumber = '${escapeQboQueryValue(docNumber)}'`,
    5,
    qboStore
  );
  if (!query.ok) {
    throw new Error(query.error || `QBO query failed for DocNumber ${docNumber}`);
  }
  const rows = qboClient.extractQueryRows(query.data, ['SalesReceipt']);
  return rows[0] ?? null;
}

async function main() {
  const config = {
    server: process.env.DB_SERVER || process.env.DB_HOST,
    database: process.env.DB_NAME,
    user: process.env.DB_USER,
    password: process.env.DB_PASS || process.env.DB_PASSWORD,
    port: Number(process.env.DB_PORT || 1433),
    options: { encrypt: true, trustServerCertificate: false },
  };

  const pool = await sql.connect(config);
  const orders = await loadAccsOrders(pool);
  await pool.close();

  const rows = [];
  let accsGrand = 0;
  let accsInvoiced = 0;
  let accsLine = 0;
  let qboTotal = 0;
  const mismatches = [];

  for (const order of orders) {
    const incrementId = String(order.IncrementId || '').trim();
    const receipt = await fetchQboReceipt(incrementId, order.QBO_TxnId);
    const accsG = money(order.GrandTotal);
    const accsI = money(order.TotalInvoiced);
    const accsL = money(order.LineRowTotal);
    const qbo = money(receipt?.TotalAmt);
    accsGrand += accsG;
    accsInvoiced += accsI;
    accsLine += accsL;
    qboTotal += qbo;

    const delta = money(qbo - accsG);
    const row = {
      header_id: order.AccsSalesOrderHeaderID,
      increment_id: incrementId,
      accs_grand_total: accsG,
      accs_total_invoiced: accsI,
      accs_line_row_total: accsL,
      qbo_total: qbo,
      delta_vs_grand: delta,
      qbo_txn_id: receipt?.Id ?? order.QBO_TxnId,
      qbo_txn_date: receipt?.TxnDate ?? null,
      status: order.OrderStatus,
    };
    rows.push(row);

    if (Math.abs(delta) > 0.01) {
      mismatches.push(row);
    }
  }

  const summary = {
    order_count: orders.length,
    accs_grand_total_sum: money(accsGrand),
    accs_total_invoiced_sum: money(accsInvoiced),
    accs_line_row_total_sum: money(accsLine),
    qbo_sales_receipt_total_sum: money(qboTotal),
    delta_qbo_vs_accs_grand: money(qboTotal - accsGrand),
    delta_qbo_vs_accs_invoiced: money(qboTotal - accsInvoiced),
    delta_qbo_vs_accs_lines: money(qboTotal - accsLine),
    mismatch_count: mismatches.length,
    mismatches,
    orders: rows,
  };

  console.log(JSON.stringify(summary, null, 2));
}

main().catch((err) => {
  console.error(err.message);
  process.exit(1);
});
