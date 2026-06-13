#!/usr/bin/env node
/**
 * Generate NutraAxis PO import Excel template (.xlsx)
 * Usage: node scripts/generate-po-template.js
 */

const fs = require('fs');
const path = require('path');

let XLSX;
try {
  XLSX = require('xlsx');
} catch (_) {
  console.error('Run: npm install xlsx');
  process.exit(1);
}

const headerRows = [
  ['Field', 'Value'],
  ['PO Number', ''],
  ['PO Date', ''],
  ['Supplier Name', ''],
  ['Supplier Address', ''],
  ['Supplier Contact Name', ''],
  ['Supplier Contact Email', ''],
  ['Supplier Contact Phone', ''],
  ['Buyer Name', 'NutraSync / Wells Specialty Pharmacy'],
  ['Buyer Address', '3420 Fairlane Farms Road, Suite 200, Wellington, Florida 33414'],
  ['Buyer Contact Name', 'Joe Butler'],
  ['Buyer Contact Email', 'nutrateam@nfcllc.com'],
  ['Buyer Contact Phone', '754-210-1723'],
  ['Payment Terms', 'Net 30 Days'],
  ['Delivery Terms', 'FOB Mt. Bethel, PA'],
  ['Reference Documents', 'Purchase is subject to all terms and conditions contained in the Quality Agreement and Master Supply Agreement between Buyer and Supplier.'],
  ['Shipping & Handling', ''],
  ['Special Instructions', 'Ship all products with COA and lot traceability documentation.'],
];

const lineRows = [
  ['Line', 'Product / Bottle Title', 'Quote Number', 'Per Bottle Price (USD)', 'Exp Date', 'Order Quantity (Bottles)'],
  [1, '', '', '', '', ''],
  [2, '', '', '', '', ''],
  [3, '', '', '', '', ''],
  [4, '', '', '', '', ''],
  [5, '', '', '', '', ''],
];

const wb = XLSX.utils.book_new();
XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(headerRows), 'Header');
XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(lineRows), 'Lines');

const out = path.join(__dirname, '..', 'assets', 'templates', 'NutraAxis_PO_Import_Template.xlsx');
fs.mkdirSync(path.dirname(out), { recursive: true });
XLSX.writeFile(wb, out);
console.log('Wrote', out);
