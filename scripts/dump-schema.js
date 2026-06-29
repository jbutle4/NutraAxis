#!/usr/bin/env node
/**
 * Dump dbo schema to SQL CREATE statements.
 * Usage: node scripts/dump-schema.js [output-path]
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

function envFirst(env, keys) {
  for (const key of keys) {
    const value = env[key];
    if (value !== undefined && value !== '') return value;
  }
  return undefined;
}

function sqlLiteral(value) {
  if (value === null || value === undefined) return 'NULL';
  return "N'" + String(value).replace(/'/g, "''") + "'";
}

function formatColumnType(row) {
  let type = row.DATA_TYPE;
  if (['varchar', 'char', 'nvarchar', 'nchar', 'varbinary', 'binary'].includes(type)) {
    const len = row.CHARACTER_MAXIMUM_LENGTH;
    type += len === -1 ? '(MAX)' : `(${len})`;
  } else if (['decimal', 'numeric'].includes(type)) {
    type += `(${row.NUMERIC_PRECISION},${row.NUMERIC_SCALE})`;
  } else if (type === 'datetime2' || type === 'datetimeoffset' || type === 'time') {
    type += row.DATETIME_PRECISION != null ? `(${row.DATETIME_PRECISION})` : '';
  }
  return type;
}

async function fetchTables(pool) {
  const result = await pool.request().query(`
    SELECT TABLE_NAME
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = 'dbo'
      AND TABLE_TYPE = 'BASE TABLE'
    ORDER BY TABLE_NAME
  `);
  return result.recordset.map((row) => row.TABLE_NAME);
}

async function fetchColumns(pool, tableName) {
  const result = await pool.request()
    .input('table', sql.NVarChar, tableName)
    .query(`
      SELECT
        c.COLUMN_NAME,
        c.DATA_TYPE,
        c.CHARACTER_MAXIMUM_LENGTH,
        c.NUMERIC_PRECISION,
        c.NUMERIC_SCALE,
        c.DATETIME_PRECISION,
        c.IS_NULLABLE,
        c.COLUMN_DEFAULT,
        c.ORDINAL_POSITION,
        COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.TABLE_SCHEMA) + '.' + QUOTENAME(c.TABLE_NAME)), c.COLUMN_NAME, 'IsIdentity') AS IS_IDENTITY
      FROM INFORMATION_SCHEMA.COLUMNS c
      WHERE c.TABLE_SCHEMA = 'dbo'
        AND c.TABLE_NAME = @table
      ORDER BY c.ORDINAL_POSITION
    `);
  return result.recordset;
}

async function fetchPrimaryKeys(pool, tableName) {
  const result = await pool.request()
    .input('table', sql.NVarChar, tableName)
    .query(`
      SELECT
        kcu.CONSTRAINT_NAME,
        kcu.COLUMN_NAME,
        kcu.ORDINAL_POSITION
      FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
      INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
        ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
       AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
       AND tc.TABLE_NAME = kcu.TABLE_NAME
      WHERE tc.TABLE_SCHEMA = 'dbo'
        AND tc.TABLE_NAME = @table
        AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
      ORDER BY kcu.ORDINAL_POSITION
    `);
  return result.recordset;
}

async function fetchForeignKeys(pool, tableName) {
  const result = await pool.request()
    .input('table', sql.NVarChar, tableName)
    .query(`
      SELECT
        fk.name AS CONSTRAINT_NAME,
        pc.name AS COLUMN_NAME,
        rt.name AS REFERENCED_TABLE,
        rc.name AS REFERENCED_COLUMN,
        fk.delete_referential_action_desc AS DELETE_ACTION,
        fk.update_referential_action_desc AS UPDATE_ACTION
      FROM sys.foreign_keys fk
      INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
      INNER JOIN sys.tables pt ON pt.object_id = fkc.parent_object_id
      INNER JOIN sys.columns pc ON pc.object_id = fkc.parent_object_id AND pc.column_id = fkc.parent_column_id
      INNER JOIN sys.tables rt ON rt.object_id = fkc.referenced_object_id
      INNER JOIN sys.columns rc ON rc.object_id = fkc.referenced_object_id AND rc.column_id = fkc.referenced_column_id
      WHERE pt.name = @table
        AND SCHEMA_NAME(pt.schema_id) = 'dbo'
      ORDER BY fk.name, fkc.constraint_column_id
    `);
  return result.recordset;
}

async function fetchCheckConstraints(pool, tableName) {
  const result = await pool.request()
    .input('table', sql.NVarChar, tableName)
    .query(`
      SELECT
        cc.name AS CONSTRAINT_NAME,
        cc.definition AS CHECK_DEFINITION
      FROM sys.check_constraints cc
      INNER JOIN sys.tables t ON t.object_id = cc.parent_object_id
      WHERE t.name = @table
        AND SCHEMA_NAME(t.schema_id) = 'dbo'
      ORDER BY cc.name
    `);
  return result.recordset;
}

async function fetchDefaultConstraints(pool, tableName) {
  const result = await pool.request()
    .input('table', sql.NVarChar, tableName)
    .query(`
      SELECT
        dc.name AS CONSTRAINT_NAME,
        c.name AS COLUMN_NAME,
        dc.definition AS DEFAULT_DEFINITION
      FROM sys.default_constraints dc
      INNER JOIN sys.columns c ON c.default_object_id = dc.object_id
      INNER JOIN sys.tables t ON t.object_id = c.object_id
      WHERE t.name = @table
        AND SCHEMA_NAME(t.schema_id) = 'dbo'
      ORDER BY c.column_id
    `);
  return result.recordset;
}

async function fetchIndexes(pool, tableName) {
  const result = await pool.request()
    .input('table', sql.NVarChar, tableName)
    .query(`
      SELECT
        i.name AS INDEX_NAME,
        i.is_unique,
        i.type_desc,
        i.has_filter,
        i.filter_definition,
        STRING_AGG(
          QUOTENAME(c.name) + CASE WHEN ic.is_descending_key = 1 THEN ' DESC' ELSE ' ASC' END,
          ', '
        ) WITHIN GROUP (ORDER BY ic.key_ordinal) AS COLUMN_LIST
      FROM sys.indexes i
      INNER JOIN sys.index_columns ic
        ON ic.object_id = i.object_id
       AND ic.index_id = i.index_id
      INNER JOIN sys.columns c
        ON c.object_id = ic.object_id
       AND c.column_id = ic.column_id
      INNER JOIN sys.tables t ON t.object_id = i.object_id
      WHERE t.name = @table
        AND SCHEMA_NAME(t.schema_id) = 'dbo'
        AND i.is_primary_key = 0
        AND i.type_desc IN ('CLUSTERED', 'NONCLUSTERED')
      GROUP BY i.name, i.is_unique, i.type_desc, i.has_filter, i.filter_definition
      ORDER BY i.name
    `);
  return result.recordset;
}

function buildCreateTable(tableName, columns, primaryKeys, defaultConstraints, checkConstraints) {
  const lines = [];
  const defaultByColumn = new Map(defaultConstraints.map((row) => [row.COLUMN_NAME, row]));
  const pkName = primaryKeys[0]?.CONSTRAINT_NAME ?? `PK_${tableName}`;
  const pkColumns = primaryKeys.map((row) => row.COLUMN_NAME);

  lines.push(`CREATE TABLE dbo.${tableName} (`);

  const columnLines = columns.map((col) => {
    let line = `    ${col.COLUMN_NAME} ${formatColumnType(col)}`;
    if (col.IS_IDENTITY) {
      line += ' IDENTITY(1,1)';
    }
    const defaultConstraint = defaultByColumn.get(col.COLUMN_NAME);
    if (defaultConstraint) {
      line += ` CONSTRAINT ${defaultConstraint.CONSTRAINT_NAME} DEFAULT ${defaultConstraint.DEFAULT_DEFINITION}`;
    } else if (col.COLUMN_DEFAULT != null && String(col.COLUMN_DEFAULT).trim() !== '') {
      line += ` DEFAULT ${col.COLUMN_DEFAULT}`;
    }
    line += col.IS_NULLABLE === 'NO' ? ' NOT NULL' : ' NULL';
    return line;
  });

  if (pkColumns.length > 0) {
    columnLines.push(`    CONSTRAINT ${pkName} PRIMARY KEY ${pkColumns.length === 1 ? 'CLUSTERED' : 'CLUSTERED'} (${pkColumns.join(', ')})`);
  }

  for (const check of checkConstraints) {
    columnLines.push(`    CONSTRAINT ${check.CONSTRAINT_NAME} CHECK ${check.CHECK_DEFINITION}`);
  }

  lines.push(columnLines.join(',\n'));
  lines.push(');');
  return lines.join('\n');
}

function buildForeignKeys(tableName, foreignKeys) {
  const grouped = new Map();
  for (const row of foreignKeys) {
    if (!grouped.has(row.CONSTRAINT_NAME)) {
      grouped.set(row.CONSTRAINT_NAME, {
        name: row.CONSTRAINT_NAME,
        columns: [],
        referencedTable: row.REFERENCED_TABLE,
        referencedColumns: [],
        deleteAction: row.DELETE_ACTION,
        updateAction: row.UPDATE_ACTION,
      });
    }
    const entry = grouped.get(row.CONSTRAINT_NAME);
    entry.columns.push(row.COLUMN_NAME);
    entry.referencedColumns.push(row.REFERENCED_COLUMN);
  }

  return [...grouped.values()].map((fk) => {
    let stmt = `ALTER TABLE dbo.${tableName} ADD CONSTRAINT ${fk.name} FOREIGN KEY (${fk.columns.join(', ')})`;
    stmt += ` REFERENCES dbo.${fk.referencedTable} (${fk.referencedColumns.join(', ')})`;
    if (fk.deleteAction && fk.deleteAction !== 'NO_ACTION') {
      stmt += ` ON DELETE ${fk.deleteAction.replace('_', ' ')}`;
    }
    if (fk.updateAction && fk.updateAction !== 'NO_ACTION') {
      stmt += ` ON UPDATE ${fk.updateAction.replace('_', ' ')}`;
    }
    return stmt + ';';
  });
}

function buildIndexes(tableName, indexes) {
  return indexes.map((idx) => {
    const unique = idx.is_unique ? 'UNIQUE ' : '';
    const filter = idx.has_filter && idx.filter_definition ? ` WHERE ${idx.filter_definition}` : '';
    return `CREATE ${unique}NONCLUSTERED INDEX ${idx.INDEX_NAME} ON dbo.${tableName} (${idx.COLUMN_LIST})${filter};`;
  });
}

async function main() {
  const outputArg = process.argv[2];
  const outputPath = outputArg
    ? path.resolve(outputArg)
    : path.join(__dirname, '..', 'docs', 'exports', 'nutraaxis-schema.sql');

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
      requestTimeout: 120000,
    },
  };

  if (!config.server || !config.database || !config.user || !config.password) {
    console.error('Missing required DB credentials in .env');
    process.exit(1);
  }

  const pool = await sql.connect(config);
  const chunks = [];
  const generatedAt = new Date().toISOString();

  chunks.push('-- NutraAxis database schema dump');
  chunks.push(`-- Database: ${config.database}`);
  chunks.push(`-- Server: ${config.server}`);
  chunks.push(`-- Generated: ${generatedAt}`);
  chunks.push('');

  try {
    const tables = await fetchTables(pool);
    chunks.push(`-- Tables: ${tables.length}`);
    chunks.push('');

    for (const tableName of tables) {
      const [columns, primaryKeys, foreignKeys, checkConstraints, defaultConstraints, indexes] = await Promise.all([
        fetchColumns(pool, tableName),
        fetchPrimaryKeys(pool, tableName),
        fetchForeignKeys(pool, tableName),
        fetchCheckConstraints(pool, tableName),
        fetchDefaultConstraints(pool, tableName),
        fetchIndexes(pool, tableName),
      ]);

      chunks.push(`-- -----------------------------------------------------------------------------`);
      chunks.push(`-- Table: dbo.${tableName}`);
      chunks.push(`-- -----------------------------------------------------------------------------`);
      chunks.push(buildCreateTable(tableName, columns, primaryKeys, defaultConstraints, checkConstraints));
      chunks.push('GO');
      chunks.push('');
    }

    const fkStatements = [];
    for (const tableName of tables) {
      const foreignKeys = await fetchForeignKeys(pool, tableName);
      fkStatements.push(...buildForeignKeys(tableName, foreignKeys));
    }

    if (fkStatements.length > 0) {
      chunks.push('-- -----------------------------------------------------------------------------');
      chunks.push('-- Foreign keys');
      chunks.push('-- -----------------------------------------------------------------------------');
      chunks.push(...fkStatements);
      chunks.push('GO');
      chunks.push('');
    }

    const indexStatements = [];
    for (const tableName of tables) {
      const indexes = await fetchIndexes(pool, tableName);
      indexStatements.push(...buildIndexes(tableName, indexes));
    }

    if (indexStatements.length > 0) {
      chunks.push('-- -----------------------------------------------------------------------------');
      chunks.push('-- Indexes');
      chunks.push('-- -----------------------------------------------------------------------------');
      chunks.push(...indexStatements);
      chunks.push('GO');
      chunks.push('');
    }
  } finally {
    await pool.close();
  }

  fs.mkdirSync(path.dirname(outputPath), { recursive: true });
  fs.writeFileSync(outputPath, chunks.join('\n') + '\n', 'utf8');
  console.log(`Schema dump written to ${outputPath}`);
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
