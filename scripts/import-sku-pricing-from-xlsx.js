#!/usr/bin/env node
/**
 * Update SKUMaster COGS, WholesalePrice, and MSRP from a pricing spreadsheet.
 * Usage: DB_NAME=nutraaxis node scripts/import-sku-pricing-from-xlsx.js [path-to.xlsx]
 */

const fs = require('fs');
const path = require('path');
const XLSX = require('xlsx');
const sql = require('mssql');

const DEFAULT_XLSX = path.join(
  process.env.HOME,
  'Library/CloudStorage/OneDrive-NationalFinancialCompanies(NEW)/Nutra Collaboration - General/Marketing/Product_Pricing_20260613/ProductPricing.xlsx'
);

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

function envFirst(env, keys) {
  for (const key of keys) {
    const value = env[key];
    if (value !== undefined && value !== '') return value;
  }
  return undefined;
}

function roundMoney(value) {
  return Math.round(Number(value) * 100) / 100;
}

function parsePricingRows(xlsxPath) {
  const workbook = XLSX.readFile(xlsxPath);
  const sheet = workbook.Sheets[workbook.SheetNames[0]];
  const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
  const headerIdx = rows.findIndex((row) => String(row[1] ?? '').trim() === 'SKU');
  if (headerIdx === -1) {
    throw new Error('Could not find header row with SKU column.');
  }

  const pricing = [];
  for (const row of rows.slice(headerIdx + 1)) {
    const skuCode = String(row[1] ?? '').trim();
    if (skuCode === '') continue;

    const cogs = row[5];
    const wholesale = row[6];
    const msrp = row[8];
    if (cogs === '' || wholesale === '' || msrp === '') {
      throw new Error(`Missing pricing values for SKU ${skuCode}.`);
    }

    pricing.push({
      skuCode,
      productName: String(row[0] ?? '').trim(),
      cogs: roundMoney(cogs),
      wholesale: roundMoney(wholesale),
      msrp: roundMoney(msrp),
    });
  }

  return pricing;
}

async function main() {
  const xlsxPath = path.resolve(process.argv[2] || DEFAULT_XLSX);
  if (!fs.existsSync(xlsxPath)) {
    console.error(`File not found: ${xlsxPath}`);
    process.exit(1);
  }

  const env = {
    ...loadEnv(path.join(__dirname, '..', '.env')),
    ...Object.fromEntries(
      ['DB_HOST', 'DB_SERVER', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PASSWORD', 'DB_PORT']
        .filter((key) => process.env[key])
        .map((key) => [key, process.env[key]])
    ),
  };

  const config = {
    server: envFirst(env, ['DB_HOST', 'DB_SERVER']),
    database: env.DB_NAME,
    user: env.DB_USER,
    password: envFirst(env, ['DB_PASS', 'DB_PASSWORD']),
    port: Number(env.DB_PORT || 1433),
    options: {
      encrypt: true,
      trustServerCertificate: false,
      connectTimeout: 15000,
      requestTimeout: 60000,
    },
  };

  if (!config.server || !config.database || !config.user || !config.password) {
    console.error('Missing required DB credentials in .env');
    process.exit(1);
  }

  const pricingRows = parsePricingRows(xlsxPath);
  console.log(`Loaded ${pricingRows.length} pricing row(s) from ${xlsxPath}`);
  console.log(`Target database: ${config.database} on ${config.server}`);

  const pool = await sql.connect(config);
  try {
    const updated = [];
    const missing = [];

    for (const row of pricingRows) {
      const result = await pool
        .request()
        .input('cogs', sql.Decimal(18, 2), row.cogs)
        .input('wholesale', sql.Decimal(18, 2), row.wholesale)
        .input('msrp', sql.Decimal(18, 2), row.msrp)
        .input('code', sql.NVarChar(100), row.skuCode)
        .query(`
        UPDATE dbo.SKUMaster
        SET COGS = @cogs,
            WholesalePrice = @wholesale,
            MSRP = @msrp,
            ModifiedDate = SYSUTCDATETIME()
        WHERE SKUCode = @code
      `);

      if ((result.rowsAffected[0] ?? 0) > 0) {
        updated.push(row);
        console.log(`  updated ${row.skuCode}: COGS=${row.cogs}, Wholesale=${row.wholesale}, MSRP=${row.msrp}`);
      } else {
        missing.push(row);
        console.log(`  missing ${row.skuCode} (${row.productName}) — not found in SKUMaster`);
      }
    }

    console.log('');
    console.log(`Updated: ${updated.length}`);
    console.log(`Not found: ${missing.length}`);
    if (missing.length > 0) {
      console.log('Missing SKU codes:', missing.map((row) => row.skuCode).join(', '));
    }
  } finally {
    await pool.close();
  }
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
