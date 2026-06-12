#!/usr/bin/env node
/**
 * Generate a filled sample PO import file (NutraSeal PO NS20251111).
 * Usage: node scripts/generate-po-sample.js
 */

const fs = require('fs');
const path = require('path');

let XLSX;
try {
  XLSX = require('xlsx');
} catch (_) {
  console.error('Run: npm install');
  process.exit(1);
}

const headerRows = [
  ['Field', 'Value'],
  ['PO Number', 'NS20251111'],
  ['PO Date', '2025-11-11'],
  ['Supplier Name', 'NutraSeal, Inc'],
  ['Supplier Address', '794 Sunrise Blvd., Mount Bethel, PA 18343'],
  ['Supplier Contact Name', 'Thomas Chapman'],
  ['Supplier Contact Email', 'chapmansan@hotmail.com'],
  ['Supplier Contact Phone', '310-413-1169'],
  ['Buyer Name', 'NutraSync / Wells Specialty Pharmacy'],
  ['Buyer Address', '3420 Fairlane Farms Road, Suite 200, Wellington, Florida 33414'],
  ['Buyer Contact Name', 'Joe Butler'],
  ['Buyer Contact Email', 'nutrateam@nfcllc.com'],
  ['Buyer Contact Phone', '754-210-1723'],
  ['Payment Terms', 'Net 30 Days'],
  ['Delivery Terms', 'FOB Mt. Bethel, PA'],
  ['Reference Documents', 'Purchase is subject to all terms and conditions contained in the Quality Agreement and Master Supply Agreement between Buyer and Supplier.'],
  ['Shipping & Handling', ''],
  ['Special Instructions', 'Ship all products with COA and lot traceability documentation. All packaging, labeling, and lot information must conform to specifications outlined in the Quality Agreement. Any substitutions, lead-time changes, or partial shipments must be pre-approved in writing by Buyer.'],
];

const lineRows = [
  ['Line', 'Product / Bottle Title', 'Quote Number', 'Per Bottle Price (USD)', 'Exp Date', 'Order Quantity (Bottles)'],
  [1, 'Multi + Thyroid/HRT Cofactors', 'SPFQWN446', 4.30, '2028-03-01', 15000],
  [2, 'HRT Metabolism Support (DIM + Myrosinase)', 'SPFQWN447', 10.60, '2028-03-01', 15000],
  [3, 'Mito Support (CoQ10/PQQ/Astaxanthin)', 'SPFQWN448', 12.44, '2028-03-01', 10000],
  [4, 'Omega-3', 'SPFQWN449', 6.86, '2028-03-01', 10000],
  [5, 'Longevity (NAD+/Senolytic Blend)', 'SPFQWN451', 11.32, '2028-03-01', 7500],
  [6, 'Adrenal Stress', 'SPFQWN450', 5.90, '2028-03-01', 7500],
  [7, 'Iron', 'SPFQWN453', 4.20, '2028-03-01', 7500],
  [8, 'PRM Inflammation', 'SPFQWN454', 9.45, '2028-03-01', 7500],
];

const outDir = path.join(__dirname, '..', 'assets', 'templates');
fs.mkdirSync(outDir, { recursive: true });

const wb = XLSX.utils.book_new();
XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(headerRows), 'Header');
XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(lineRows), 'Lines');

const xlsxPath = path.join(outDir, 'NutraAxis_PO_Sample_NutraSeal.xlsx');
XLSX.writeFile(wb, xlsxPath);

const csvLines = [
  '[HEADER]',
  'Field,Value',
  ...headerRows.slice(1).map(([field, value]) => {
    const escaped = String(value).includes(',') ? `"${String(value).replace(/"/g, '""')}"` : value;
    return `${field},${escaped}`;
  }),
  '[LINES]',
  'Line,Product / Bottle Title,Quote Number,Per Bottle Price (USD),Exp Date,Order Quantity (Bottles)',
  ...lineRows.slice(1).map((row) => row.map((cell) => {
    const s = String(cell);
    return s.includes(',') ? `"${s.replace(/"/g, '""')}"` : s;
  }).join(',')),
];

const csvPath = path.join(outDir, 'NutraAxis_PO_Sample_NutraSeal.csv');
fs.writeFileSync(csvPath, csvLines.join('\n') + '\n');

console.log('Wrote', xlsxPath);
console.log('Wrote', csvPath);
