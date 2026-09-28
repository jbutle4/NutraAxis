#!/usr/bin/env node
/**
 * Run a Marketing & Research job locally through the process runner (writes ProcessExecutionLog).
 * Usage: node scripts/run-marketing-job.js <code> [key=value ...]
 *   node scripts/run-marketing-job.js seo-noop
 *   node scripts/run-marketing-job.js research-harvest-due source_id=12
 */
const fs = require('fs');
const path = require('path');

function loadEnv(filePath) {
  if (!fs.existsSync(filePath)) return;
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
    if (process.env[key] === undefined) process.env[key] = value;
  }
}

loadEnv(path.join(__dirname, '..', '.env'));
if (!process.env.DB_NAME_PRODUCTION && process.env.DB_NAME) {
  process.env.DB_NAME_PRODUCTION = process.env.DB_NAME;
}

const [code, ...pairs] = process.argv.slice(2);
if (!code) {
  console.error('Usage: node scripts/run-marketing-job.js <code> [key=value ...]');
  process.exit(1);
}

const params = {};
for (const pair of pairs) {
  const idx = pair.indexOf('=');
  if (idx > 0) params[pair.slice(0, idx)] = pair.slice(idx + 1);
}

const processRunner = require('../functions/src/lib/process-runner');

processRunner.execute(code, params, 'Manual')
  .then((result) => {
    console.log(JSON.stringify(result, null, 2));
    process.exit(result.ok ? 0 : 1);
  })
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
