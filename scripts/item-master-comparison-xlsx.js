#!/usr/bin/env node
/**
 * Build Excel workbook from item-master-comparison-data.json
 *
 * Usage:
 *   node scripts/item-master-comparison-xlsx.js [input.json] [output.xlsx]
 */

const fs = require('fs');
const path = require('path');
const XLSX = require('xlsx');

const rootDir = path.join(__dirname, '..');
const defaultInput = path.join(rootDir, 'docs/exports/item-master-comparison-data.json');
const inputPath = path.resolve(process.argv[2] || defaultInput);

if (!fs.existsSync(inputPath)) {
  console.error(`Input not found: ${inputPath}`);
  console.error('Run: php scripts/item-master-comparison.php');
  process.exit(1);
}

const data = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
const stamp = (data.generated_at || new Date().toISOString()).slice(0, 10);
const defaultOutput = path.join(rootDir, `docs/exports/item-master-comparison-${stamp}.xlsx`);
const outputPath = path.resolve(process.argv[3] || defaultOutput);

const compareFields = data.compare_fields || [];
const fieldLabels = {
  product_name: 'Product Name',
  brand: 'Brand',
  manufacturer: 'Manufacturer / CMO',
  primary_category: 'Primary Category',
  secondary_category: 'Secondary Category',
  status: 'Status',
  upc: 'UPC',
  gtin14: 'GTIN-14',
  case_barcode: 'Case Barcode',
  msrp: 'MSRP / Price',
  wholesale_price: 'Wholesale Price',
  cogs: 'COGS / Cost',
  serving_count: 'Serving Count',
  bottle_size: 'Bottle Size',
  label_selection: 'Label Selection',
  formulation: 'Formulation',
  directions: 'Directions',
  capsule_count: 'Capsule Count',
};

function sheetFromRows(rows) {
  const ws = XLSX.utils.json_to_sheet(rows);
  ws['!cols'] = Object.keys(rows[0] || {}).map((key) => ({
    wch: Math.min(60, Math.max(12, key.length + 2)),
  }));
  return ws;
}

function buildSummarySheet() {
  const summary = data.summary || {};
  const sources = data.sources || {};
  const rows = [
    { section: 'Generated', metric: 'Timestamp (UTC)', value: data.generated_at || '' },
    { section: 'MSSQL', metric: 'Table', value: sources.mssql?.table || 'dbo.SKUMaster' },
    { section: 'MSSQL', metric: 'Row count', value: sources.mssql?.count ?? '' },
    { section: 'ACCS', metric: 'Endpoint', value: sources.accs?.endpoint || '' },
    { section: 'ACCS', metric: 'Environment', value: sources.accs?.environment || '' },
    { section: 'ACCS', metric: 'Row count', value: sources.accs?.count ?? '' },
    { section: 'Jazz', metric: 'Base URL', value: sources.jazz?.base_url || '' },
    { section: 'Jazz', metric: 'SKU count', value: sources.jazz?.sku_count ?? '' },
    { section: 'Jazz', metric: 'Item count', value: sources.jazz?.item_count ?? '' },
    { section: 'Totals', metric: 'Unique SKUs', value: summary.total_unique_skus ?? '' },
    { section: 'Totals', metric: 'MSSQL + ACCS aligned SKUs', value: summary.mssql_accs_aligned_count ?? '' },
    { section: 'Totals', metric: 'Jazz needs correction', value: summary.jazz_needs_correction_count ?? '' },
    { section: 'MSSQL only', metric: 'SKUs', value: (summary.mssql_only || []).join(', ') },
    { section: 'ACCS only', metric: 'SKUs', value: (summary.accs_only || []).join(', ') },
    { section: 'Missing in Jazz', metric: 'SKUs', value: (summary.missing_in_jazz || []).join(', ') },
    { section: 'Jazz only', metric: 'SKUs', value: (summary.jazz_only || []).join(', ') },
  ];
  return sheetFromRows(rows);
}

function buildMssqlAccsSheet() {
  const rows = (data.aligned_rows || []).map((row) => {
    const out = {
      SKU: row.sku,
      In_MSSQL: row.in_mssql ? 'Y' : 'N',
      In_ACCS: row.in_accs ? 'Y' : 'N',
      Overall: row.mssql_accs_overall,
      Mismatch_Fields: row.mssql_accs_mismatch_fields,
    };

    for (const field of compareFields) {
      const label = fieldLabels[field] || field;
      out[`MSSQL_${label}`] = row[`mssql_${field}`] ?? '';
      out[`ACCS_${label}`] = row[`accs_${field}`] ?? '';
      out[`Match_${label}`] = row[`match_${field}`] ?? '';
    }

    return out;
  });

  return sheetFromRows(rows);
}

function buildJazzCorrectionsSheet() {
  const rows = (data.aligned_rows || [])
    .filter((row) => row.in_mssql || row.in_accs || row.in_jazz)
    .map((row) => {
      const out = {
        SKU: row.sku,
        In_MSSQL: row.in_mssql ? 'Y' : 'N',
        In_ACCS: row.in_accs ? 'Y' : 'N',
        In_Jazz: row.in_jazz ? 'Y' : 'N',
        Jazz_Action: row.jazz_overall,
        Jazz_Mismatch_Fields: row.jazz_mismatch_fields,
      };

      for (const field of compareFields) {
        const label = fieldLabels[field] || field;
        out[`Expected_${label}`] = row[`expected_${field}`] ?? '';
        out[`Jazz_${label}`] = row[`jazz_${field}`] ?? '';
        out[`Jazz_Match_${label}`] = row[`jazz_match_${field}`] ?? '';
      }

      return out;
    });

  return sheetFromRows(rows);
}

function buildFieldDiffSheet() {
  const rows = [];

  for (const row of data.aligned_rows || []) {
    for (const field of compareFields) {
      const mssqlValue = row[`mssql_${field}`] ?? '';
      const accsValue = row[`accs_${field}`] ?? '';
      const expectedValue = row[`expected_${field}`] ?? '';
      const jazzValue = row[`jazz_${field}`] ?? '';
      const mssqlAccsMatch = row[`match_${field}`] ?? '';
      const jazzMatch = row[`jazz_match_${field}`] ?? '';

      if (mssqlAccsMatch === 'MATCH' && (jazzMatch === 'MATCH' || jazzMatch === 'N/A')) {
        continue;
      }

      rows.push({
        SKU: row.sku,
        Field: fieldLabels[field] || field,
        MSSQL: mssqlValue,
        ACCS: accsValue,
        MSSQL_ACCS_Match: mssqlAccsMatch,
        Expected_For_Jazz: expectedValue,
        Jazz: jazzValue,
        Jazz_Match: jazzMatch,
        Jazz_Action: row.jazz_overall,
      });
    }
  }

  rows.sort((a, b) => {
    const sku = a.SKU.localeCompare(b.SKU);
    if (sku !== 0) return sku;
    return a.Field.localeCompare(b.Field);
  });

  return sheetFromRows(rows.length ? rows : [{ note: 'No mismatches found' }]);
}

function flattenRawRows(sourceName, rows) {
  return rows.map((row) => {
    const flat = { _source: sourceName };
    for (const [key, value] of Object.entries(row)) {
      flat[key] = value == null
        ? ''
        : typeof value === 'object'
          ? JSON.stringify(value)
          : value;
    }
    return flat;
  });
}

const workbook = XLSX.utils.book_new();
XLSX.utils.book_append_sheet(workbook, buildSummarySheet(), 'Summary');
XLSX.utils.book_append_sheet(workbook, buildMssqlAccsSheet(), 'MSSQL vs ACCS');
XLSX.utils.book_append_sheet(workbook, buildJazzCorrectionsSheet(), 'Jazz Corrections');
XLSX.utils.book_append_sheet(workbook, buildFieldDiffSheet(), 'Line-by-Line Diffs');
XLSX.utils.book_append_sheet(
  workbook,
  sheetFromRows(flattenRawRows('MSSQL', data.raw?.mssql || [])),
  'MSSQL Raw'
);
XLSX.utils.book_append_sheet(
  workbook,
  sheetFromRows(flattenRawRows('ACCS', data.raw?.accs || [])),
  'ACCS Raw'
);
XLSX.utils.book_append_sheet(
  workbook,
  sheetFromRows(flattenRawRows('Jazz SKU', data.raw?.jazz_sku || [])),
  'Jazz SKU Raw'
);
XLSX.utils.book_append_sheet(
  workbook,
  sheetFromRows(flattenRawRows('Jazz Item', data.raw?.jazz_item || [])),
  'Jazz Item Raw'
);

const outputDir = path.dirname(outputPath);
if (!fs.existsSync(outputDir)) {
  fs.mkdirSync(outputDir, { recursive: true });
}

XLSX.writeFile(workbook, outputPath);

console.log(JSON.stringify({
  ok: true,
  input: inputPath,
  output: outputPath,
  summary: data.summary,
}, null, 2));
