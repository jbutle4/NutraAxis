#!/usr/bin/env node
/**
 * Export dbo.SKUMaster to Excel.
 *
 * Usage:
 *   node scripts/export-sku-master-xlsx.js [output.xlsx]
 */

const fs = require('fs');
const path = require('path');
const sql = require('mssql');
const XLSX = require('xlsx');

const rootDir = path.join(__dirname, '..');
const stamp = new Date().toISOString().slice(0, 10);
const defaultOutput = path.join(rootDir, 'docs', 'exports', `sku-master-${stamp}.xlsx`);
const outputPath = path.resolve(process.argv[2] || defaultOutput);

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

function normalizeValue(value) {
  if (value === null || value === undefined) {
    return '';
  }
  if (value instanceof Date) {
    return value.toISOString().replace('T', ' ').replace(/\.\d{3}Z$/, ' UTC');
  }
  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }
  return value;
}

function normalizeRow(row) {
  const out = {};
  for (const [key, value] of Object.entries(row)) {
    out[key] = normalizeValue(value);
  }
  return out;
}

async function main() {
  const env = loadEnv(path.join(rootDir, '.env'));
  const config = {
    server: env.DB_HOST,
    database: env.DB_NAME,
    user: env.DB_USER,
    password: env.DB_PASS,
    port: Number(env.DB_PORT || 1433),
    options: {
      encrypt: true,
      trustServerCertificate: false,
      connectTimeout: 15000,
      requestTimeout: 120000,
    },
  };

  const pool = await sql.connect(config);
  try {
    const result = await pool.request().query(`
      SELECT *
      FROM dbo.SKUMaster
      ORDER BY SKUCode
    `);

    const rows = result.recordset.map(normalizeRow);
    if (rows.length === 0) {
      console.error('No rows found in dbo.SKUMaster.');
      process.exit(1);
    }

    fs.mkdirSync(path.dirname(outputPath), { recursive: true });

    const worksheet = XLSX.utils.json_to_sheet(rows);
    worksheet['!cols'] = Object.keys(rows[0]).map((key) => ({
      wch: Math.min(60, Math.max(12, key.length + 2)),
    }));

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'SKUMaster');
    XLSX.writeFile(workbook, outputPath);

    console.log(`Exported ${rows.length} row(s) to ${outputPath}`);
  } finally {
    await pool.close();
  }
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
