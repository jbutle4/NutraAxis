#!/usr/bin/env node
/**
 * Execute a .sql file against Azure SQL (splits on GO batches).
 * Usage: node scripts/run-sql-file.js sql/001_create_iam_tables.sql
 */

const fs = require('fs');
const path = require('path');
const sql = require('mssql');

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

function splitBatches(content) {
  return content
    .split(/^\s*GO\s*$/gim)
    .map((batch) => batch.trim())
    .filter((batch) => batch.length > 0);
}

function envFirst(env, keys) {
  for (const key of keys) {
    const value = env[key];
    if (value !== undefined && value !== '') return value;
  }
  return undefined;
}

async function main() {
  const file = process.argv[2];
  if (!file) {
    console.error('Usage: node scripts/run-sql-file.js <path-to.sql>');
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

  const sqlPath = path.resolve(file);
  const batches = splitBatches(fs.readFileSync(sqlPath, 'utf8'));

  console.log(`Executing ${batches.length} batch(es) from ${sqlPath}...`);

  const pool = await sql.connect(config);
  try {
    for (let i = 0; i < batches.length; i++) {
      await pool.request().query(batches[i]);
      console.log(`  Batch ${i + 1}/${batches.length} OK`);
    }
    console.log('Done.');
  } finally {
    await pool.close();
  }
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
