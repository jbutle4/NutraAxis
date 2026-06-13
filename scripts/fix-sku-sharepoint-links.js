#!/usr/bin/env node
/**
 * Replace local file:// LabelPrintReadyLink values with SharePoint document URLs.
 * Usage: node scripts/fix-sku-sharepoint-links.js [--dry-run]
 */

const fs = require('fs');
const path = require('path');
const sql = require('mssql');

const SHAREPOINT_SITE_BASE =
  'https://nationalfinancial.sharepoint.com/sites/NutraCollaboration/Shared%20Documents/General';
const ONEDRIVE_LIBRARY_MARKER = 'Nutra Collaboration - General/';

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

function decodeFileUrl(value) {
  try {
    return decodeURIComponent(String(value).replace(/^file:\/\//i, ''));
  } catch {
    return String(value).replace(/^file:\/\//i, '');
  }
}

function fileUrlToSharePoint(fileUrl) {
  const decoded = decodeFileUrl(fileUrl);
  const markerIndex = decoded.indexOf(ONEDRIVE_LIBRARY_MARKER);
  if (markerIndex === -1) {
    return null;
  }

  let relativePath = decoded.slice(markerIndex + ONEDRIVE_LIBRARY_MARKER.length);
  relativePath = relativePath.replace(
    'Marketing/Product 3D Renderings/Re_ 3Ds/',
    'Marketing/Product 3D Renderings/Fw_ 3Ds/'
  );

  const segments = relativePath.split('/').filter(Boolean).map((segment) => encodeURIComponent(segment));
  if (segments.length === 0) {
    return null;
  }

  return `${SHAREPOINT_SITE_BASE}/${segments.join('/')}`;
}

function isLocalFileLink(value) {
  return /^file:\/\//i.test(String(value ?? '').trim());
}

async function main() {
  const dryRun = process.argv.includes('--dry-run');
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
      requestTimeout: 120000,
    },
  };

  const pool = await sql.connect(config);
  try {
    const result = await pool.request().query(`
      SELECT SKUID, SKUCode, LabelPrintReadyLink, SFPLink
      FROM dbo.SKUMaster
      WHERE LabelPrintReadyLink LIKE 'file://%'
         OR SFPLink LIKE 'file://%'
      ORDER BY SKUCode
    `);

    if (result.recordset.length === 0) {
      console.log('No local file links found in SKUMaster.');
      return;
    }

    let updated = 0;
    for (const row of result.recordset) {
      const updates = {};

      if (isLocalFileLink(row.LabelPrintReadyLink)) {
        const sharePointUrl = fileUrlToSharePoint(row.LabelPrintReadyLink);
        if (!sharePointUrl) {
          console.error(`  skip ${row.SKUCode}: unable to map label link`);
          continue;
        }
        updates.label = sharePointUrl;
      }

      if (isLocalFileLink(row.SFPLink)) {
        const sharePointUrl = fileUrlToSharePoint(row.SFPLink);
        if (!sharePointUrl) {
          console.error(`  skip ${row.SKUCode}: unable to map SFP link`);
          continue;
        }
        updates.sfp = sharePointUrl;
      }

      if (Object.keys(updates).length === 0) {
        continue;
      }

      console.log(`${dryRun ? 'would update' : 'updated'}: ${row.SKUCode}`);
      if (updates.label) {
        console.log(`  LabelPrintReadyLink -> ${updates.label}`);
      }
      if (updates.sfp) {
        console.log(`  SFPLink -> ${updates.sfp}`);
      }

      if (!dryRun) {
        const request = pool.request().input('id', sql.Int, row.SKUID);
        const setClauses = ['ModifiedDate = SYSUTCDATETIME()'];

        if (updates.label) {
          request.input('label', sql.NVarChar(2000), updates.label);
          setClauses.push('LabelPrintReadyLink = @label');
        }
        if (updates.sfp) {
          request.input('sfp', sql.NVarChar(2000), updates.sfp);
          setClauses.push('SFPLink = @sfp');
        }

        await request.query(`
          UPDATE dbo.SKUMaster
          SET ${setClauses.join(', ')}
          WHERE SKUID = @id
        `);
      }

      updated += 1;
    }

    console.log(`Done. ${dryRun ? 'Would update' : 'Updated'} ${updated} SKU(s).`);
  } finally {
    await pool.close();
  }
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
