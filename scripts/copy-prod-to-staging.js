#!/usr/bin/env node
/**
 * Copy nutraaxis schema (via sql/*.sql) and data into nutraaxis_test.
 * Azure SQL does not support cross-database SELECT, so data is copied table-by-table.
 *
 * Usage: node scripts/copy-prod-to-staging.js
 */

const fs = require('fs');
const path = require('path');
const sql = require('mssql');

const SOURCE_DB = 'nutraaxis';
const TARGET_DB = 'nutraaxis_test';
const BATCH_SIZE = 200;

function loadEnv(filePath) {
  const vars = {};
  for (const line of fs.readFileSync(filePath, 'utf8').split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const idx = trimmed.indexOf('=');
    if (idx === -1) continue;
    let value = trimmed.slice(idx + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"'))
      || (value.startsWith("'") && value.endsWith("'"))
    ) {
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

function dbConfig(env, database) {
  return {
    server: env.DB_HOST,
    database,
    user: env.DB_USER,
    password: env.DB_PASS,
    port: Number(env.DB_PORT || 1433),
    options: {
      encrypt: true,
      trustServerCertificate: false,
      connectTimeout: 15000,
      requestTimeout: 600000,
    },
    pool: { max: 5, min: 0, idleTimeoutMillis: 30000 },
  };
}

function quoteIdent(name) {
  return `[${String(name).replace(/]/g, ']]')}]`;
}

function quoteTable(schemaName, tableName) {
  return `${quoteIdent(schemaName)}.${quoteIdent(tableName)}`;
}

async function runMigrations(targetPool) {
  const sqlDir = path.join(__dirname, '..', 'sql');
  const files = fs.readdirSync(sqlDir)
    .filter((file) => file.endsWith('.sql'))
    .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));

  console.log(`Applying ${files.length} migration file(s) to ${TARGET_DB}...`);

  for (const file of files) {
    const fullPath = path.join(sqlDir, file);
    const batches = splitBatches(fs.readFileSync(fullPath, 'utf8'));
    process.stdout.write(`  ${file} (${batches.length} batch(es))... `);
    for (const batch of batches) {
      await targetPool.request().query(batch);
    }
    console.log('OK');
  }
}

async function listTables(pool) {
  const result = await pool.request().query(`
    SELECT s.name AS schema_name, t.name AS table_name
    FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE t.type = 'U' AND t.is_ms_shipped = 0
    ORDER BY s.name, t.name
  `);
  return result.recordset;
}

async function listColumns(pool, schemaName, tableName) {
  const result = await pool.request()
    .input('schemaName', sql.NVarChar, schemaName)
    .input('tableName', sql.NVarChar, tableName)
    .query(`
      SELECT c.name AS column_name, c.is_identity, c.is_computed
      FROM sys.columns c
      INNER JOIN sys.tables t ON t.object_id = c.object_id
      INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
      WHERE s.name = @schemaName AND t.name = @tableName
        AND c.is_computed = 0
      ORDER BY c.column_id
    `);
  return result.recordset;
}

async function setForeignKeys(pool, enabled) {
  const verb = enabled ? 'CHECK' : 'NOCHECK';
  const result = await pool.request().query(`
    SELECT
      s.name AS schema_name,
      t.name AS table_name,
      fk.name AS fk_name
    FROM sys.foreign_keys fk
    INNER JOIN sys.tables t ON t.object_id = fk.parent_object_id
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
  `);

  for (const row of result.recordset) {
    const statement = `ALTER TABLE ${quoteTable(row.schema_name, row.table_name)} ${verb} CONSTRAINT ${quoteIdent(row.fk_name)}`;
    await pool.request().query(statement);
  }
}

async function clearTable(pool, schemaName, tableName) {
  await pool.request().query(`DELETE FROM ${quoteTable(schemaName, tableName)}`);
}

function sqlLiteral(value) {
  if (value === null || value === undefined) {
    return 'NULL';
  }
  if (typeof value === 'boolean') {
    return value ? '1' : '0';
  }
  if (typeof value === 'number') {
    return Number.isFinite(value) ? String(value) : 'NULL';
  }
  if (value instanceof Date) {
    return `N'${value.toISOString().replace(/'/g, "''")}'`;
  }
  if (Buffer.isBuffer(value)) {
    return `0x${value.toString('hex')}`;
  }
  return `N'${String(value).replace(/'/g, "''")}'`;
}

async function copyTable(sourcePool, targetPool, schemaName, tableName) {
  const qualified = quoteTable(schemaName, tableName);
  const columns = await listColumns(sourcePool, schemaName, tableName);
  if (columns.length === 0) {
    return 0;
  }

  const columnList = columns.map((column) => quoteIdent(column.column_name)).join(', ');
  const hasIdentity = columns.some((column) => column.is_identity);

  const countResult = await sourcePool.request().query(`SELECT COUNT(*) AS row_count FROM ${qualified}`);
  const totalRows = countResult.recordset[0].row_count;
  if (totalRows === 0) {
    return 0;
  }

  await clearTable(targetPool, schemaName, tableName);

  let copied = 0;
  for (let offset = 0; offset < totalRows; offset += BATCH_SIZE) {
    const rowsResult = await sourcePool.request().query(`
      SELECT ${columnList}
      FROM ${qualified}
      ORDER BY (SELECT NULL)
      OFFSET ${offset} ROWS FETCH NEXT ${BATCH_SIZE} ROWS ONLY
    `);

    if (rowsResult.recordset.length === 0) {
      continue;
    }

    const insertStatements = rowsResult.recordset.map((row) => {
      const values = columns.map((column) => sqlLiteral(row[column.column_name])).join(', ');
      return `INSERT INTO ${qualified} (${columnList}) VALUES (${values});`;
    });

    const batchSql = hasIdentity
      ? [`SET IDENTITY_INSERT ${qualified} ON;`, ...insertStatements, `SET IDENTITY_INSERT ${qualified} OFF;`].join('\n')
      : insertStatements.join('\n');

    await targetPool.request().query(batchSql);
    copied += rowsResult.recordset.length;
  }

  return copied;
}

async function main() {
  const env = loadEnv(path.join(__dirname, '..', '.env'));
  if (!env.DB_HOST || !env.DB_USER || !env.DB_PASS) {
    console.error('Missing DB_HOST, DB_USER, or DB_PASS in .env');
    process.exit(1);
  }

  const sourcePool = await new sql.ConnectionPool(dbConfig(env, SOURCE_DB)).connect();
  const targetPool = await new sql.ConnectionPool(dbConfig(env, TARGET_DB)).connect();

  try {
    const targetTables = await listTables(targetPool);
    if (targetTables.length === 0) {
      await runMigrations(targetPool);
    } else {
      console.log(`${TARGET_DB} already has ${targetTables.length} table(s); skipping migrations.`);
    }

    const sourceTables = await listTables(sourcePool);
    const finalTargetTables = await listTables(targetPool);

    if (sourceTables.length === 0) {
      throw new Error(`No tables found in ${SOURCE_DB}.`);
    }

    if (finalTargetTables.length === 0) {
      throw new Error(`No tables found in ${TARGET_DB} after migrations.`);
    }

    console.log(`Copying data from ${SOURCE_DB} to ${TARGET_DB} (${sourceTables.length} tables)...`);
    console.log('Disabling foreign keys on staging...');
    await setForeignKeys(targetPool, false);

    let totalCopied = 0;
    for (const table of sourceTables) {
      const { schema_name: schemaName, table_name: tableName } = table;
      process.stdout.write(`  ${schemaName}.${tableName}... `);
      const copied = await copyTable(sourcePool, targetPool, schemaName, tableName);
      totalCopied += copied;
      console.log(`${copied} row(s)`);
    }

    console.log('Re-enabling foreign keys on staging...');
    await setForeignKeys(targetPool, true);

    const verify = await targetPool.request().query(`
      SELECT COUNT(*) AS table_count
      FROM sys.tables
      WHERE type = 'U' AND is_ms_shipped = 0
    `);

    console.log(`Done. Copied ${totalCopied} row(s) into ${verify.recordset[0].table_count} staging table(s).`);
  } finally {
    await sourcePool.close();
    await targetPool.close();
  }
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
