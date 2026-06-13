#!/usr/bin/env node
/**
 * Import Master Label spreadsheet rows into dbo.SKUMaster.
 * Usage: node scripts/import-sku-master-from-xlsx.js [path-to.xlsx] [--update]
 */

const fs = require('fs');
const path = require('path');
const sql = require('mssql');
const XLSX = require('xlsx');

const DEFAULT_FILE = path.join(
  process.env.HOME || '',
  'Library/CloudStorage/OneDrive-NationalFinancialCompanies(NEW)/Nutra Collaboration - General/Quality/Master Label Doc_Uploaded_Copy.xlsx'
);

const PRIMARY_CATEGORY_MAP = {
  'digestive & gut health': 'GI Health',
  'healthy aging & longevity': 'Longevity',
  'hormone & reproductive health': 'Hormonal Support',
  'metabolic and weight health': 'Metabolic',
  'mood, stress and sleep': 'Other',
  'pain, immune response & physical comfort': 'Musculoskeletal',
};

const SECONDARY_CATEGORY_MAP = {
  'pain, inflammation & physical comfort': 'Musculoskeletal',
  'pain, inflammation and physical comfort': 'Musculoskeletal',
  'hormone & reproductive health': 'Hormonal Support',
  'metabolic & weight health': 'Metabolic',
  'metabolic and weight health': 'Metabolic',
  'mood, stress & sleep': 'Other',
  'mood, stress and sleep': 'Other',
  'digestive & gut health': 'GI Health',
  'healthy aging & longevity': 'Longevity',
};

const CMO_MANUFACTURER_MAP = {
  HW: 'IFF-HealthWright',
  NS: 'NutraSeal',
  VQ: 'VitaQuest',
  'Randall Optimal': 'Other',
};

const CMO_SUPPLIER_CODE_MAP = {
  HW: 'SUP-007',
  NS: 'NUTRASEAL',
  'Randall Optimal': 'SUP-006',
};

const LABEL_SELECTIONS = new Set(['Teal Only', 'Teal and Coral']);
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

function cleanText(value) {
  return String(value ?? '').replace(/\s+/g, ' ').trim();
}

function decodeFileUrl(value) {
  try {
    return decodeURIComponent(String(value).replace(/^file:\/\//i, ''));
  } catch {
    return String(value).replace(/^file:\/\//i, '');
  }
}

function fileUrlToSharePoint(fileUrl) {
  const text = cleanText(fileUrl);
  if (!/^file:\/\//i.test(text)) {
    return text || null;
  }

  const decoded = decodeFileUrl(text);
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

function cleanSkuCode(value) {
  return cleanText(value).replace(/\s/g, '');
}

function cleanUpc(value) {
  return cleanText(value).replace(/\s/g, '');
}

function parseIntOrNull(value) {
  const text = cleanText(value);
  if (text === '') return null;
  const num = Number.parseInt(text, 10);
  return Number.isFinite(num) && num > 0 ? num : null;
}

function normalizeHeaderKey(key) {
  return String(key ?? '').trim().toLowerCase();
}

function rowValue(row, ...keys) {
  const normalized = new Map();
  for (const [key, value] of Object.entries(row)) {
    normalized.set(normalizeHeaderKey(key), value);
  }
  for (const key of keys) {
    if (normalized.has(normalizeHeaderKey(key))) {
      return normalized.get(normalizeHeaderKey(key));
    }
  }
  return '';
}

function mapPrimaryCategory(value) {
  const key = cleanText(value).toLowerCase();
  if (key === '') return 'Other';
  return PRIMARY_CATEGORY_MAP[key] || 'Other';
}

function mapSecondaryCategory(value) {
  const text = cleanText(value);
  if (text === '') return { category: null, overflow: null };

  const first = text.split(';')[0].trim().toLowerCase();
  const mapped = SECONDARY_CATEGORY_MAP[first] || null;
  const overflow = text.includes(';') ? text : null;

  return { category: mapped, overflow };
}

function detectBrand(productName) {
  const name = cleanText(productName);
  if (name.startsWith('NutraSync')) return 'NutraSync';
  return 'NutraAxis';
}

function detectStatus(row) {
  const product = cleanText(rowValue(row, 'Product')).toLowerCase();
  const name = cleanText(rowValue(row, 'Name')).toLowerCase();
  if (product.includes('reformulating') || product.includes('do not create label')) {
    return 'In Development';
  }
  if (name.includes('reformulating')) {
    return 'In Development';
  }
  return 'Active';
}

function buildNotes(secondaryOverflow, existingNotes) {
  const parts = [];
  if (secondaryOverflow) {
    parts.push(`Secondary categories (import): ${secondaryOverflow}`);
  }
  if (existingNotes) {
    parts.push(existingNotes);
  }
  return parts.length > 0 ? parts.join('\n\n') : null;
}

function readRows(filePath) {
  const workbook = XLSX.readFile(filePath);
  const sheetName = workbook.SheetNames.find((name) => /^sheet1$/i.test(name)) || workbook.SheetNames[0];
  const sheet = workbook.Sheets[sheetName];
  const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' });

  return rows
    .map((row) => {
      const skuCode = cleanSkuCode(rowValue(row, 'SKU/UPC', 'SKU/UPC '));
      const productName = cleanText(rowValue(row, 'Name'));
      const secondary = mapSecondaryCategory(rowValue(row, 'Secondary Category'));
      const labelSelection = cleanText(rowValue(row, 'Label Selection'));
      const productImage = cleanText(rowValue(row, 'Product Image'));
      const reformulationNote = cleanText(rowValue(row, 'Product'));

      return {
        skuCode,
        productName,
        upc: cleanUpc(rowValue(row, 'UPC')) || null,
        product: cleanText(rowValue(row, 'Product')) || null,
        supplementFactsPanel: cleanText(rowValue(row, 'Link to Supplement Facts Panel')) || null,
        claims: cleanText(rowValue(row, 'Claims', ' Claims')) || null,
        labelSelection: LABEL_SELECTIONS.has(labelSelection) ? labelSelection : null,
        directions: cleanText(rowValue(row, 'Directions')) || null,
        capsuleCount: parseIntOrNull(rowValue(row, 'Capsule Count')),
        servingCount: parseIntOrNull(rowValue(row, '#/Servings')),
        certsOnLabel: cleanText(rowValue(row, 'Certs on Label')) || null,
        bottleSize: cleanText(rowValue(row, 'Bottle Size')) || null,
        cmo: cleanText(rowValue(row, 'CMO')),
        labelPrintReadyLink: fileUrlToSharePoint(productImage),
        primaryCategory: mapPrimaryCategory(rowValue(row, 'Primary Category', 'Primary Category ')),
        secondaryCategory: secondary.category,
        notes: buildNotes(
          secondary.overflow,
          reformulationNote.toLowerCase().includes('reformulating') ? reformulationNote : null
        ),
        brand: detectBrand(productName),
        manufacturer: CMO_MANUFACTURER_MAP[cleanText(rowValue(row, 'CMO'))] || 'Other',
        supplierCode: CMO_SUPPLIER_CODE_MAP[cleanText(rowValue(row, 'CMO'))] || null,
        status: detectStatus(row),
      };
    })
    .filter((row) => row.skuCode !== '' && row.productName !== '');
}

async function loadSupplierMap(pool) {
  const result = await pool.request().query(`
    SELECT SupplierID, SupplierCode, SupplierName
    FROM dbo.Supplier
  `);

  const byCode = new Map();
  const byName = new Map();
  for (const row of result.recordset) {
    if (row.SupplierCode) {
      byCode.set(String(row.SupplierCode).trim().toUpperCase(), row.SupplierID);
    }
    byName.set(String(row.SupplierName).trim().toLowerCase(), row.SupplierID);
  }

  return { byCode, byName };
}

function resolveSupplierId(row, supplierMap) {
  if (row.supplierCode) {
    const id = supplierMap.byCode.get(row.supplierCode.toUpperCase());
    if (id) return id;
  }

  if (row.cmo === 'NS') {
    return supplierMap.byName.get('nutraseal, inc') || supplierMap.byName.get('nutraseal') || null;
  }
  if (row.cmo === 'VQ') {
    return supplierMap.byName.get('vitaquest') || null;
  }
  if (row.cmo === 'HW') {
    return supplierMap.byCode.get('SUP-007')
      || supplierMap.byName.get('iff-healthwright')
      || supplierMap.byName.get('iff-healthwright products')
      || supplierMap.byName.get('iff / healthwright products')
      || null;
  }
  if (row.cmo === 'Randall Optimal') {
    return supplierMap.byCode.get('SUP-006')
      || supplierMap.byCode.get('RANDALL-OPTIMAL')
      || supplierMap.byName.get('randall optimal')
      || null;
  }

  return null;
}

async function skuExists(pool, skuCode) {
  const result = await pool
    .request()
    .input('code', sql.NVarChar(100), skuCode)
    .query('SELECT SKUID FROM dbo.SKUMaster WHERE SKUCode = @code');
  return result.recordset[0]?.SKUID ?? null;
}

async function insertSku(pool, row, supplierId) {
  await pool
    .request()
    .input('code', sql.NVarChar(100), row.skuCode)
    .input('name', sql.NVarChar(200), row.productName)
    .input('supplier', sql.Int, supplierId)
    .input('brand', sql.NVarChar(30), row.brand)
    .input('manufacturer', sql.NVarChar(50), row.manufacturer)
    .input('primaryCategory', sql.NVarChar(50), row.primaryCategory)
    .input('secondary', sql.NVarChar(50), row.secondaryCategory)
    .input('status', sql.NVarChar(30), row.status)
    .input('serving', sql.Int, row.servingCount)
    .input('capsule', sql.Int, row.capsuleCount)
    .input('bottle', sql.NVarChar(100), row.bottleSize)
    .input('upc', sql.NVarChar(20), row.upc)
    .input('sfpPanel', sql.NVarChar(200), row.supplementFactsPanel)
    .input('claims', sql.NVarChar(sql.MAX), row.claims)
    .input('labelSelection', sql.NVarChar(100), row.labelSelection)
    .input('directions', sql.NVarChar(sql.MAX), row.directions)
    .input('certs', sql.NVarChar(sql.MAX), row.certsOnLabel)
    .input('product', sql.NVarChar(sql.MAX), row.product)
    .input('labelLink', sql.NVarChar(2000), row.labelPrintReadyLink)
    .input('notes', sql.NVarChar(sql.MAX), row.notes)
    .query(`
      INSERT INTO dbo.SKUMaster (
        SKUCode, ProductName, SupplierID, Brand, Manufacturer,
        PrimaryTherapeuticCategory, SecondaryCategory, SKUStatus,
        ServingCount, CapsuleCount, BottleSize, UPC,
        SupplementFactsPanel, Claims, LabelSelection, Directions,
        CertsOnLabel, Product, LabelPrintReadyLink, Notes
      )
      VALUES (
        @code, @name, @supplier, @brand, @manufacturer,
        @primaryCategory, @secondary, @status,
        @serving, @capsule, @bottle, @upc,
        @sfpPanel, @claims, @labelSelection, @directions,
        @certs, @product, @labelLink, @notes
      )
    `);
}

async function updateSku(pool, skuId, row, supplierId) {
  await pool
    .request()
    .input('id', sql.Int, skuId)
    .input('code', sql.NVarChar(100), row.skuCode)
    .input('name', sql.NVarChar(200), row.productName)
    .input('supplier', sql.Int, supplierId)
    .input('brand', sql.NVarChar(30), row.brand)
    .input('manufacturer', sql.NVarChar(50), row.manufacturer)
    .input('primaryCategory', sql.NVarChar(50), row.primaryCategory)
    .input('secondary', sql.NVarChar(50), row.secondaryCategory)
    .input('status', sql.NVarChar(30), row.status)
    .input('serving', sql.Int, row.servingCount)
    .input('capsule', sql.Int, row.capsuleCount)
    .input('bottle', sql.NVarChar(100), row.bottleSize)
    .input('upc', sql.NVarChar(20), row.upc)
    .input('sfpPanel', sql.NVarChar(200), row.supplementFactsPanel)
    .input('claims', sql.NVarChar(sql.MAX), row.claims)
    .input('labelSelection', sql.NVarChar(100), row.labelSelection)
    .input('directions', sql.NVarChar(sql.MAX), row.directions)
    .input('certs', sql.NVarChar(sql.MAX), row.certsOnLabel)
    .input('product', sql.NVarChar(sql.MAX), row.product)
    .input('labelLink', sql.NVarChar(2000), row.labelPrintReadyLink)
    .input('notes', sql.NVarChar(sql.MAX), row.notes)
    .query(`
      UPDATE dbo.SKUMaster
      SET ProductName = @name,
          SupplierID = @supplier,
          Brand = @brand,
          Manufacturer = @manufacturer,
          PrimaryTherapeuticCategory = @primaryCategory,
          SecondaryCategory = @secondary,
          SKUStatus = @status,
          ServingCount = @serving,
          CapsuleCount = @capsule,
          BottleSize = @bottle,
          UPC = @upc,
          SupplementFactsPanel = @sfpPanel,
          Claims = @claims,
          LabelSelection = @labelSelection,
          Directions = @directions,
          CertsOnLabel = @certs,
          Product = @product,
          LabelPrintReadyLink = @labelLink,
          Notes = @notes,
          ModifiedDate = SYSUTCDATETIME()
      WHERE SKUID = @id
    `);
}

async function main() {
  const args = process.argv.slice(2);
  const updateExisting = args.includes('--update');
  const fileArg = args.find((arg) => !arg.startsWith('--'));
  const filePath = path.resolve(fileArg || DEFAULT_FILE);

  if (!fs.existsSync(filePath)) {
    console.error(`File not found: ${filePath}`);
    process.exit(1);
  }

  const rows = readRows(filePath);
  if (rows.length === 0) {
    console.error('No SKU rows found in spreadsheet.');
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
      requestTimeout: 120000,
    },
  };

  console.log(`Importing ${rows.length} SKU(s) from ${filePath}...`);
  if (updateExisting) {
    console.log('Existing SKU codes will be updated.');
  } else {
    console.log('Existing SKU codes will be skipped (use --update to overwrite).');
  }

  const pool = await sql.connect(config);
  try {
    const supplierMap = await loadSupplierMap(pool);
    let inserted = 0;
    let updated = 0;
    let skipped = 0;

    for (const row of rows) {
      const supplierId = resolveSupplierId(row, supplierMap);
      const existingId = await skuExists(pool, row.skuCode);

      if (existingId) {
        if (!updateExisting) {
          skipped += 1;
          console.log(`  skip (exists): ${row.skuCode}`);
          continue;
        }
        await updateSku(pool, existingId, row, supplierId);
        updated += 1;
        console.log(`  updated: ${row.skuCode} (${row.productName})`);
        continue;
      }

      await insertSku(pool, row, supplierId);
      inserted += 1;
      console.log(`  inserted: ${row.skuCode} (${row.productName})`);
    }

    console.log(`Done. Inserted ${inserted}, updated ${updated}, skipped ${skipped}.`);
  } finally {
    await pool.close();
  }
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
