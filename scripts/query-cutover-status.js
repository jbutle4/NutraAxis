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

async function main() {
  const env = {
    ...loadEnv(path.join(__dirname, '..', '.env')),
    ...process.env,
  };
  const config = {
    server: env.DB_SERVER || env.DB_HOST,
    database: env.DB_NAME,
    user: env.DB_USER,
    password: env.DB_PASS || env.DB_PASSWORD,
    port: Number(env.DB_PORT || 1433),
    options: { encrypt: true, trustServerCertificate: false },
  };
  const pool = await sql.connect(config);
  const sale = await pool.request().query(`
    SELECT SyncStatus, COUNT(*) AS cnt
    FROM dbo.QBOInventorySyncLog
    WHERE SyncType = N'Sale'
    GROUP BY SyncStatus
  `);
  const extra = await pool.request().query(`
    SELECT SyncType, SyncStatus, COUNT(*) AS cnt
    FROM dbo.QBOInventorySyncLog
    WHERE SyncType IN (N'SaleReversal', N'SaleReceipt')
    GROUP BY SyncType, SyncStatus
  `);
  const pending = await pool.request().query(`
    SELECT COUNT(DISTINCT rev.ReferenceID) AS pending_receipts
    FROM dbo.QBOInventorySyncLog rev
    LEFT JOIN dbo.QBOInventorySyncLog sr
      ON sr.ReferenceType = N'AccsSalesOrderHeader'
     AND sr.ReferenceID = rev.ReferenceID
     AND sr.SyncType = N'SaleReceipt'
     AND sr.SyncStatus = N'Synced'
    WHERE rev.SyncType = N'SaleReversal'
      AND rev.SyncStatus = N'Synced'
      AND sr.SyncLogID IS NULL
  `);
  const errors = await pool.request().query(`
    SELECT TOP 10 ReferenceLineKey AS increment_id, SyncError
    FROM dbo.QBOInventorySyncLog
    WHERE SyncType = N'SaleReceipt' AND SyncStatus = N'Error'
    ORDER BY SyncLogID DESC
  `);
  await pool.close();
  console.log(JSON.stringify({
    sale: sale.recordset,
    extra: extra.recordset,
    pending_receipts: pending.recordset[0],
    recent_errors: errors.recordset,
  }, null, 2));
}

main().catch((err) => {
  console.error(err.message);
  process.exit(1);
});
