#!/usr/bin/env node
/**
 * Seed initial COA metadata (PDFs served from coa-test/files until uploaded to blob).
 * Usage: node scripts/seed-coa-documents.js
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

async function main() {
  const root = path.join(__dirname, '..');
  const env = {
    ...loadEnv(path.join(root, '.env')),
    ...Object.fromEntries(
      ['DB_HOST', 'DB_SERVER', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PASSWORD', 'DB_PORT']
        .filter((key) => process.env[key])
        .map((key) => [key, process.env[key]])
    ),
  };

  const config = {
    server: env.DB_HOST || env.DB_SERVER,
    database: env.DB_NAME,
    user: env.DB_USER,
    password: env.DB_PASS || env.DB_PASSWORD,
    port: Number(env.DB_PORT || 1433),
    options: { encrypt: true, trustServerCertificate: false },
  };

  const seeds = [
    {
      product_name: 'AdrenaAxis',
      lot_number: '37489',
      expiration_date: '2026-04-23',
      expiration_display: '04/2028',
      sort_order: 20,
      file_name: 'AdrenaAxis37489.pdf',
    },
    {
      product_name: 'IronAxis',
      lot_number: '37340',
      expiration_date: '2026-03-25',
      expiration_display: '02/28',
      sort_order: 10,
      file_name: 'IronAxis37340.pdf',
    },
  ];

  const pool = await sql.connect(config);
  const countResult = await pool.request().query('SELECT COUNT(*) AS cnt FROM dbo.CoaDocument');
  const existing = Number(countResult.recordset[0]?.cnt || 0);
  if (existing > 0) {
    console.log(`CoaDocument already has ${existing} row(s); skipping seed.`);
    await pool.close();
    return;
  }

  for (const seed of seeds) {
    const filePath = path.join(root, 'coa-test/files', seed.file_name);
    if (!fs.existsSync(filePath)) {
      throw new Error(`Missing seed file: ${filePath}`);
    }

    const insert = await pool.request()
      .input('product_name', sql.NVarChar(200), seed.product_name)
      .input('lot_number', sql.NVarChar(50), seed.lot_number)
      .input('expiration_date', sql.Date, seed.expiration_date)
      .input('expiration_display', sql.NVarChar(50), seed.expiration_display)
      .input('file_name', sql.NVarChar(255), seed.file_name)
      .input('content_type', sql.NVarChar(100), 'application/pdf')
      .input('sort_order', sql.Int, seed.sort_order)
      .input('file_size', sql.Int, fs.statSync(filePath).size)
      .query(`
        INSERT INTO dbo.CoaDocument (
          ProductName, LotNumber, ExpirationDate, ExpirationDisplay,
          FileName, ContentType, FileSizeBytes, Publish, SortOrder
        )
        OUTPUT INSERTED.CoaDocumentID
        VALUES (
          @product_name, @lot_number, @expiration_date, @expiration_display,
          @file_name, @content_type, @file_size, 1, @sort_order
        )
      `);

    const id = Number(insert.recordset[0].CoaDocumentID);
    console.log(`Seeded COA #${id}: ${seed.product_name} / ${seed.lot_number}`);
  }

  await pool.close();
  console.log('Done.');
}

main().catch((err) => {
  console.error(err.message || err);
  process.exit(1);
});
