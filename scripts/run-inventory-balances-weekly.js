#!/usr/bin/env node
/**
 * Run the Inventory Balances weekly email job once (local / on-demand).
 * Usage: node scripts/run-inventory-balances-weekly.js
 */

const path = require('path');
const fs = require('fs');

function loadEnv(filePath) {
  if (!fs.existsSync(filePath)) return {};
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

const rootEnv = loadEnv(path.join(__dirname, '..', '.env'));
const functionsEnv = loadEnv(path.join(__dirname, '..', 'functions', 'local.settings.json'));
// local.settings.json is JSON — ignore parse failures and rely on .env
void functionsEnv;

for (const [key, value] of Object.entries(rootEnv)) {
  if (process.env[key] === undefined) {
    process.env[key] = value;
  }
}

if (!process.env.DB_NAME_PRODUCTION && process.env.DB_NAME) {
  process.env.DB_NAME_PRODUCTION = process.env.DB_NAME;
}

const job = require('../functions/src/lib/jobs/inventory-balances-weekly');

job
  .run()
  .then((result) => {
    console.log(JSON.stringify(result, null, 2));
    process.exit(result.ok ? 0 : 1);
  })
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
