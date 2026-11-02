#!/usr/bin/env node
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

for (const [k, v] of Object.entries(loadEnv(path.join(__dirname, '..', '.env')))) {
  if (process.env[k] === undefined) process.env[k] = v;
}

const { mapAccsDbOrderToSalesReceipt } = require('../functions/src/lib/qbo-order-mapper');
const qboClient = require('../functions/src/lib/qbo-client');
const qboStore = require('../functions/src/lib/qbo-connection').production;

async function main() {
  const pool = await sql.connect({
    server: process.env.DB_SERVER || process.env.DB_HOST,
    database: process.env.DB_NAME,
    user: process.env.DB_USER,
    password: process.env.DB_PASS || process.env.DB_PASSWORD,
    port: Number(process.env.DB_PORT || 1433),
    options: { encrypt: true, trustServerCertificate: false },
  });

  const headers = await pool.request().query(`
    SELECT DISTINCT sr.ReferenceID AS HeaderId
    FROM dbo.QBOInventorySyncLog sr
    WHERE sr.SyncType = N'SaleReceipt' AND sr.SyncStatus = N'Synced'
    ORDER BY sr.ReferenceID
  `);

  let accsGrand = 0;
  let accsMapped = 0;
  let qboTotal = 0;
  const outliers = [];

  for (const { HeaderId } of headers.recordset) {
    const rows = await pool.request().input('id', sql.Int, HeaderId).query(`
      SELECT h.*, d.*
      FROM dbo.AccsSalesOrderHeader h
      INNER JOIN dbo.AccsSalesOrderDetail d ON d.AccsSalesOrderHeaderID = h.AccsSalesOrderHeaderID
      WHERE h.AccsSalesOrderHeaderID = @id
      ORDER BY d.AccsSalesOrderDetailID
    `);
    const header = rows.recordset[0];
    const details = rows.recordset;
    const mapped = mapAccsDbOrderToSalesReceipt(header, details, { useTodayTxnDate: true });
    const mappedSum = mapped.ok
      ? mapped.payload.lines.reduce((s, l) => s + l.qty * l.unitPrice, 0)
      : 0;

    const log = await pool.request().input('id', sql.Int, HeaderId).query(`
      SELECT QBO_TxnId FROM dbo.QBOInventorySyncLog
      WHERE ReferenceID = @id AND SyncType = N'SaleReceipt' AND SyncStatus = N'Synced'
    `);
    const txnId = log.recordset[0]?.QBO_TxnId;
    const q = await qboClient.apiRequest('GET', `/salesreceipt/${txnId}`, {
      query: { minorversion: '65' },
      connectionStore: qboStore,
    });
    const qbo = Number(q.data?.SalesReceipt?.TotalAmt || 0);
    const grand = Number(header.GrandTotal || 0);

    accsGrand += grand;
    accsMapped += mappedSum;
    qboTotal += qbo;

    const delta = qbo - grand;
    if (Math.abs(delta) > 0.05) {
      outliers.push({
        header_id: HeaderId,
        increment_id: header.IncrementId,
        accs_grand: grand,
        mapped_line_total: Math.round(mappedSum * 100) / 100,
        qbo_total: qbo,
        delta,
        detail_count: details.length,
        mapped_lines: mapped.ok ? mapped.payload.lines : [],
      });
    }
  }

  await pool.close();

  console.log(JSON.stringify({
    order_count: headers.recordset.length,
    accs_grand_total: Math.round(accsGrand * 100) / 100,
    mapped_payload_total: Math.round(accsMapped * 100) / 100,
    qbo_receipt_total: Math.round(qboTotal * 100) / 100,
    delta_qbo_vs_mapped: Math.round((qboTotal - accsMapped) * 100) / 100,
    delta_qbo_vs_grand: Math.round((qboTotal - accsGrand) * 100) / 100,
    outlier_count: outliers.length,
    outliers: outliers.slice(0, 8),
  }, null, 2));
}

main().catch((e) => { console.error(e.message); process.exit(1); });
