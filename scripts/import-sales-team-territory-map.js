#!/usr/bin/env node
/**
 * Import Sales Team Territory Map.xlsx into dbo.SalesTeamTerritoryAssignments.
 *
 * Usage:
 *   node scripts/import-sales-team-territory-map.js [path-to.xlsx]
 *
 * Default path: OneDrive Sales Team Territory Map.xlsx
 *
 * Behavior:
 * - Dedupes exact State+ZipCode+County rows (keeps first)
 * - Inserts new keys; updates Rep when changed (sets PreviousRepAssigned)
 * - Leaves PreviousRepAssigned NULL on initial insert
 */

const fs = require('fs');
const path = require('path');
const sql = require('mssql');
const XLSX = require('xlsx');

const DEFAULT_XLSX = path.join(
  process.env.HOME || '',
  'Library/CloudStorage/OneDrive-NationalFinancialCompanies(NEW)/Nutra Collaboration - General/Budget Planning and Business Plan/Sales Commission Programs/Sales Team Territory Map.xlsx'
);

function loadEnv(filePath) {
  const vars = {};
  for (const line of fs.readFileSync(filePath, 'utf8').split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const idx = trimmed.indexOf('=');
    if (idx === -1) continue;
    let value = trimmed.slice(idx + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
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

function normalizeZip(value) {
  if (value === null || value === undefined || value === '') return '';
  const raw = String(value).trim();
  if (/^all$/i.test(raw)) return 'All';
  if (/^\d+(\.0+)?$/.test(raw)) {
    const digits = String(Math.trunc(Number(raw)));
    return digits.length < 5 && digits !== '' ? digits.padStart(5, '0') : digits;
  }
  return raw;
}

function readRows(filePath) {
  const workbook = XLSX.readFile(filePath);
  const sheetName = workbook.SheetNames.includes('DATA')
    ? 'DATA'
    : workbook.SheetNames[0];
  const sheet = workbook.Sheets[sheetName];
  const raw = XLSX.utils.sheet_to_json(sheet, { defval: '', raw: false });

  const seen = new Set();
  const rows = [];
  let skippedDup = 0;

  for (const row of raw) {
    const state = String(row.State ?? row.state ?? '')
      .trim()
      .toUpperCase();
    const zipCode = normalizeZip(row['Zip Code'] ?? row.ZipCode ?? row.zipCode ?? '');
    const county = String(row.County ?? row.county ?? '').trim();
    const rep = String(row.Rep ?? row.rep ?? '').trim();

    if (!state || !zipCode || !county || !rep) continue;

    const key = `${state}|${zipCode}|${county}`.toLowerCase();
    if (seen.has(key)) {
      skippedDup += 1;
      continue;
    }
    seen.add(key);
    rows.push({ state, zipCode, county, rep });
  }

  return { rows, skippedDup, sheetName };
}

async function main() {
  const filePath = path.resolve(process.argv[2] || DEFAULT_XLSX);
  if (!fs.existsSync(filePath)) {
    console.error(`File not found: ${filePath}`);
    process.exit(1);
  }

  const { rows, skippedDup, sheetName } = readRows(filePath);
  if (rows.length === 0) {
    console.error('No territory rows found in spreadsheet.');
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
      requestTimeout: 300000,
    },
  };

  if (!config.server || !config.database || !config.user || !config.password) {
    console.error('Missing required DB credentials in .env');
    process.exit(1);
  }

  console.log(`Reading sheet "${sheetName}" from ${filePath}`);
  console.log(`Unique rows: ${rows.length} (skipped ${skippedDup} exact duplicate key(s))`);
  console.log(`Importing into ${config.database} on ${config.server}...`);

  const pool = await sql.connect(config);
  try {
    const existingResult = await pool.request().query(`
      SELECT State, ZipCode, County, Rep
      FROM dbo.SalesTeamTerritoryAssignments
    `);
    const existing = new Map();
    for (const row of existingResult.recordset) {
      const key = `${row.State}|${row.ZipCode}|${row.County}`.toLowerCase();
      existing.set(key, String(row.Rep ?? ''));
    }

    const toInsert = [];
    const toUpdate = [];
    let unchanged = 0;

    for (const row of rows) {
      const key = `${row.state}|${row.zipCode}|${row.county}`.toLowerCase();
      const currentRep = existing.get(key);
      if (currentRep === undefined) {
        toInsert.push(row);
        continue;
      }
      if (currentRep === row.rep) {
        unchanged += 1;
        continue;
      }
      toUpdate.push({ ...row, previousRepAssigned: currentRep });
    }

    const escapeN = (value) => `N'${String(value).replace(/'/g, "''")}'`;
    const BATCH = 200;

    const tx = new sql.Transaction(pool);
    await tx.begin();
    try {
      for (let i = 0; i < toInsert.length; i += BATCH) {
        const chunk = toInsert.slice(i, i + BATCH);
        const values = chunk
          .map(
            (row) =>
              `(${escapeN(row.state)}, ${escapeN(row.zipCode)}, ${escapeN(row.county)}, ${escapeN(row.rep)}, NULL)`
          )
          .join(',\n');
        await new sql.Request(tx).query(`
          INSERT INTO dbo.SalesTeamTerritoryAssignments (
            State, ZipCode, County, Rep, PreviousRepAssigned
          )
          VALUES
          ${values};
        `);
      }

      for (const row of toUpdate) {
        await new sql.Request(tx)
          .input('State', sql.NVarChar(2), row.state)
          .input('ZipCode', sql.NVarChar(10), row.zipCode)
          .input('County', sql.NVarChar(100), row.county)
          .input('Rep', sql.NVarChar(200), row.rep)
          .input('PreviousRepAssigned', sql.NVarChar(200), row.previousRepAssigned)
          .query(`
            UPDATE dbo.SalesTeamTerritoryAssignments
            SET Rep = @Rep,
                PreviousRepAssigned = @PreviousRepAssigned,
                DateModified = SYSUTCDATETIME()
            WHERE State = @State
              AND ZipCode = @ZipCode
              AND County = @County
          `);
      }

      await tx.commit();
    } catch (err) {
      await tx.rollback();
      throw err;
    }

    const countResult = await pool
      .request()
      .query('SELECT COUNT(*) AS Cnt FROM dbo.SalesTeamTerritoryAssignments');

    console.log(
      `Done. inserted=${toInsert.length} updated=${toUpdate.length} unchanged=${unchanged}`
    );
    console.log(`Table row count: ${countResult.recordset[0].Cnt}`);
  } finally {
    await pool.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
