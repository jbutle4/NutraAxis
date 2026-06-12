#!/usr/bin/env node
/**
 * Test Azure SQL connection using .env credentials.
 * Usage: node scripts/test-db-connection.js
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

async function main() {
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
      requestTimeout: 15000,
    },
  };

  if (!config.server || !config.database || !config.user || !config.password) {
    console.error('Missing required DB_* values in .env');
    process.exit(1);
  }

  console.log(`Host: ${config.server}`);
  console.log(`Database: ${config.database}`);
  console.log(`User: ${config.user}`);
  console.log('');

  try {
    const pool = await sql.connect(config);
    const result = await pool.request().query('SELECT DB_NAME() AS db_name, @@VERSION AS version');
    const row = result.recordset[0];
    console.log('SUCCESS: Connected to Azure SQL');
    console.log(`Connected database: ${row.db_name}`);
    console.log(`Server version: ${row.version.split('\n')[0]}`);
    await pool.close();
  } catch (err) {
    console.error('FAILED:', err.message);
    if (err.code) console.error('Code:', err.code);
    process.exit(1);
  }
}

main();
