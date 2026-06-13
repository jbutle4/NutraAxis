#!/usr/bin/env node
/**
 * Import Chrome bookmarks (xlsx) into dbo.LinksIndex.
 * Usage: node scripts/import-links-from-xlsx.js [path-to.xlsx]
 */

const fs = require('fs');
const path = require('path');
const sql = require('mssql');
const XLSX = require('xlsx');

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

function normalizeStatus(value) {
  const status = String(value ?? '').trim().toLowerCase();
  return status === 'active' ? 'active' : 'not active';
}

function normalizeRegistration(value) {
  if (value === true || value === 1 || value === '1') return 1;
  if (typeof value === 'string' && value.trim().toLowerCase() === 'true') return 1;
  return 0;
}

function readRows(filePath) {
  const workbook = XLSX.readFile(filePath);
  const sheet = workbook.Sheets[workbook.SheetNames[0]];
  const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' });

  return rows
    .map((row) => ({
      linkName: String(row.LinkName ?? row.linkName ?? '').trim(),
      linkUrl: String(row.LinkURL ?? row.linkUrl ?? '').trim(),
      linkCategory: String(row.LinkCategory ?? row.linkCategory ?? '').trim(),
      linkStatus: normalizeStatus(row.LinkStatus ?? row.linkStatus ?? 'active'),
      userRegistration: normalizeRegistration(row.UserRegistration ?? row.userRegistration ?? 0),
    }))
    .filter((row) => row.linkName !== '' && row.linkUrl !== '');
}

async function main() {
  const filePath = path.resolve(process.argv[2] || path.join(process.env.HOME || '', 'Downloads/chrome_bookmarks.xlsx'));
  if (!fs.existsSync(filePath)) {
    console.error(`File not found: ${filePath}`);
    process.exit(1);
  }

  const rows = readRows(filePath);
  if (rows.length === 0) {
    console.error('No link rows found in spreadsheet.');
    process.exit(1);
  }

  const env = loadEnv(path.join(__dirname, '..', '.env'));
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
      requestTimeout: 60000,
    },
  };

  console.log(`Importing ${rows.length} link(s) from ${filePath}...`);

  const pool = await sql.connect(config);
  try {
    const existing = await pool.request().query('SELECT LinkURL FROM dbo.LinksIndex');
    const existingUrls = new Set(
      existing.recordset.map((row) => String(row.LinkURL).trim().toLowerCase())
    );

    let inserted = 0;
    let skipped = 0;

    for (const row of rows) {
      const urlKey = row.linkUrl.toLowerCase();
      if (existingUrls.has(urlKey)) {
        skipped += 1;
        console.log(`  skip (exists): ${row.linkName}`);
        continue;
      }

      await pool
        .request()
        .input('name', sql.NVarChar(200), row.linkName)
        .input('category', sql.NVarChar(60), row.linkCategory)
        .input('status', sql.NVarChar(20), row.linkStatus)
        .input('registration', sql.Bit, row.userRegistration)
        .input('url', sql.NVarChar(2000), row.linkUrl)
        .query(`
          INSERT INTO dbo.LinksIndex (
            LinkName, LinkDescription, LinkCategory, LinkStatus,
            UserRegistrationRequired, LinkURL, ModifiedbyUser
          )
          VALUES (
            @name, NULL, @category, @status,
            @registration, @url, NULL
          )
        `);

      existingUrls.add(urlKey);
      inserted += 1;
      console.log(`  inserted: ${row.linkName}`);
    }

    console.log(`Done. Inserted ${inserted}, skipped ${skipped}.`);
  } finally {
    await pool.close();
  }
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
