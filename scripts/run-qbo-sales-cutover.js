#!/usr/bin/env node
/**
 * Run QBO sales cutover locally (dry-run or live).
 * Usage:
 *   node scripts/run-qbo-sales-cutover.js --dry-run
 *   node scripts/run-qbo-sales-cutover.js
 */
const fs = require('fs');
const path = require('path');

function loadEnv(filePath) {
  if (!fs.existsSync(filePath)) {
    return;
  }
  for (const line of fs.readFileSync(filePath, 'utf8').split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const idx = trimmed.indexOf('=');
    if (idx === -1) continue;
    let value = trimmed.slice(idx + 1).trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    const key = trimmed.slice(0, idx).trim();
    if (process.env[key] === undefined) {
      process.env[key] = value;
    }
  }
}

loadEnv(path.join(__dirname, '..', '.env'));

const cutover = require('../functions/src/lib/jobs/qbo-sales-cutover');

const dryRun = process.argv.includes('--dry-run');

cutover.run({ dry_run: dryRun })
  .then((result) => {
    console.log(JSON.stringify(result, null, 2));
    process.exit(result.ok ? 0 : 1);
  })
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
