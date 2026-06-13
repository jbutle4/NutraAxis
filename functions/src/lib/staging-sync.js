const crypto = require('crypto');
const { sql, getSyncSettings, connectPool, WATERMARK_CANDIDATES } = require('./db-config');

function quoteIdent(name) {
  return `[${String(name).replace(/]/g, ']]')}]`;
}

function quoteTable(schemaName, tableName) {
  return `${quoteIdent(schemaName)}.${quoteIdent(tableName)}`;
}

function logMessage(log, message, ...args) {
  if (typeof log === 'function') {
    log(message, ...args);
  }
}

function rowFingerprint(row, columns) {
  const parts = columns.map((column) => {
    const value = row[column.column_name];
    if (value === null || value === undefined) {
      return '';
    }
    if (value instanceof Date) {
      return value.toISOString();
    }
    if (Buffer.isBuffer(value)) {
      return value.toString('base64');
    }
    return String(value);
  });
  return crypto.createHash('sha256').update(parts.join('\u001f')).digest('hex');
}

async function loadTables(pool, excludedTables) {
  const result = await pool.request().query(`
    SELECT s.name AS schema_name, t.name AS table_name
    FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE t.type = 'U' AND t.is_ms_shipped = 0
    ORDER BY s.name, t.name
  `);

  return result.recordset.filter((row) => !excludedTables.has(row.table_name.toLowerCase()));
}

async function loadForeignKeys(pool) {
  const result = await pool.request().query(`
    SELECT
      OBJECT_SCHEMA_NAME(fk.parent_object_id) AS child_schema,
      OBJECT_NAME(fk.parent_object_id) AS child_table,
      OBJECT_SCHEMA_NAME(fk.referenced_object_id) AS parent_schema,
      OBJECT_NAME(fk.referenced_object_id) AS parent_table
    FROM sys.foreign_keys fk
  `);
  return result.recordset;
}

function sortTablesByDependency(tables, foreignKeys) {
  const tableNames = tables.map((table) => table.table_name);
  const tableSet = new Set(tableNames);
  const inDegree = new Map(tableNames.map((name) => [name, 0]));
  const adjacency = new Map(tableNames.map((name) => [name, []]));

  for (const fk of foreignKeys) {
    if (!tableSet.has(fk.child_table) || !tableSet.has(fk.parent_table)) {
      continue;
    }
    if (fk.child_table === fk.parent_table) {
      continue;
    }
    adjacency.get(fk.parent_table).push(fk.child_table);
    inDegree.set(fk.child_table, (inDegree.get(fk.child_table) || 0) + 1);
  }

  const queue = tableNames.filter((name) => (inDegree.get(name) || 0) === 0).sort();
  const ordered = [];

  while (queue.length > 0) {
    const current = queue.shift();
    ordered.push(current);
    for (const child of adjacency.get(current) || []) {
      const next = (inDegree.get(child) || 0) - 1;
      inDegree.set(child, next);
      if (next === 0) {
        queue.push(child);
        queue.sort();
      }
    }
  }

  if (ordered.length !== tableNames.length) {
    return [...tables].sort((a, b) => a.table_name.localeCompare(b.table_name));
  }

  const byName = new Map(tables.map((table) => [table.table_name, table]));
  return ordered.map((name) => byName.get(name));
}

async function loadTableMetadata(pool, schemaName, tableName, watermarkCandidates) {
  const columnsResult = await pool.request()
    .input('schemaName', sql.NVarChar, schemaName)
    .input('tableName', sql.NVarChar, tableName)
    .query(`
      SELECT
        c.name AS column_name,
        c.is_identity,
        c.is_computed,
        ty.name AS type_name
      FROM sys.columns c
      INNER JOIN sys.tables t ON t.object_id = c.object_id
      INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
      INNER JOIN sys.types ty ON ty.user_type_id = c.user_type_id
      WHERE s.name = @schemaName
        AND t.name = @tableName
      ORDER BY c.column_id
    `);

  const pkResult = await pool.request()
    .input('schemaName', sql.NVarChar, schemaName)
    .input('tableName', sql.NVarChar, tableName)
    .query(`
      SELECT c.name AS column_name
      FROM sys.indexes i
      INNER JOIN sys.index_columns ic
        ON ic.object_id = i.object_id AND ic.index_id = i.index_id
      INNER JOIN sys.columns c
        ON c.object_id = ic.object_id AND c.column_id = ic.column_id
      INNER JOIN sys.tables t ON t.object_id = i.object_id
      INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
      WHERE i.is_primary_key = 1
        AND s.name = @schemaName
        AND t.name = @tableName
      ORDER BY ic.key_ordinal
    `);

  const columns = columnsResult.recordset.filter((column) => !column.is_computed);
  const pkColumns = pkResult.recordset.map((row) => row.column_name);
  if (pkColumns.length === 0) {
    throw new Error(`Table ${schemaName}.${tableName} has no primary key.`);
  }

  const columnNames = new Set(columns.map((column) => column.column_name));
  const watermarkColumn = watermarkCandidates.find((name) => columnNames.has(name)) || null;
  const hasIdentity = columns.some((column) => column.is_identity);
  const singleNumericPk = pkColumns.length === 1
    && ['int', 'bigint', 'smallint', 'tinyint'].includes(
      columns.find((column) => column.column_name === pkColumns[0])?.type_name
    );

  return {
    schemaName,
    tableName,
    qualifiedName: quoteTable(schemaName, tableName),
    columns,
    pkColumns,
    watermarkColumn,
    hasIdentity,
    singleNumericPk,
    pkColumn: pkColumns[0],
  };
}

async function getLastSyncUtc(stagingPool, tableName) {
  const result = await stagingPool.request()
    .input('tableName', sql.NVarChar, tableName)
    .query(`
      SELECT LastSyncUtc
      FROM dbo.StagingSyncState
      WHERE TableName = @tableName
    `);

  if (result.recordset.length === 0) {
    return new Date('1970-01-01T00:00:00.000Z');
  }

  return new Date(result.recordset[0].LastSyncUtc);
}

async function getMaxPk(stagingPool, metadata) {
  if (!metadata.singleNumericPk) {
    return 0;
  }

  const result = await stagingPool.request().query(`
    SELECT MAX(${quoteIdent(metadata.pkColumn)}) AS max_pk
    FROM ${metadata.qualifiedName}
  `);

  return Number(result.recordset[0].max_pk || 0);
}

async function fetchCandidateRows(prodPool, stagingPool, metadata, sinceUtc, maxPk, batchSize) {
  const columnList = metadata.columns.map((column) => quoteIdent(column.column_name)).join(', ');
  const rowsByKey = new Map();

  function addRows(recordset) {
    for (const row of recordset) {
      const key = metadata.pkColumns.map((pk) => String(row[pk])).join('\u001f');
      rowsByKey.set(key, row);
    }
  }

  if (metadata.watermarkColumn) {
    const watermarkResult = await prodPool.request()
      .input('sinceUtc', sql.DateTime2, sinceUtc)
      .query(`
        SELECT ${columnList}
        FROM ${metadata.qualifiedName}
        WHERE ${quoteIdent(metadata.watermarkColumn)} >= @sinceUtc
        ORDER BY ${quoteIdent(metadata.pkColumn)}
      `);
    addRows(watermarkResult.recordset);
  }

  if (metadata.singleNumericPk) {
    for (let offset = 0; ; offset += batchSize) {
      const appendResult = await prodPool.request()
        .input('maxPk', sql.BigInt, maxPk)
        .query(`
          SELECT ${columnList}
          FROM ${metadata.qualifiedName}
          WHERE ${quoteIdent(metadata.pkColumn)} > @maxPk
          ORDER BY ${quoteIdent(metadata.pkColumn)}
          OFFSET ${offset} ROWS FETCH NEXT ${batchSize} ROWS ONLY
        `);

      if (appendResult.recordset.length === 0) {
        break;
      }
      addRows(appendResult.recordset);
      if (appendResult.recordset.length < batchSize) {
        break;
      }
    }

    if (!metadata.watermarkColumn && maxPk > 0) {
      for (let offset = 0; ; offset += batchSize) {
        const compareResult = await prodPool.request()
          .input('maxPk', sql.BigInt, maxPk)
          .query(`
            SELECT ${columnList}
            FROM ${metadata.qualifiedName}
            WHERE ${quoteIdent(metadata.pkColumn)} <= @maxPk
            ORDER BY ${quoteIdent(metadata.pkColumn)}
            OFFSET ${offset} ROWS FETCH NEXT ${batchSize} ROWS ONLY
          `);

        if (compareResult.recordset.length === 0) {
          break;
        }
        addRows(compareResult.recordset);
        if (compareResult.recordset.length < batchSize) {
          break;
        }
      }
    }
  }

  return [...rowsByKey.values()];
}

async function fetchExistingRows(stagingPool, metadata, rows) {
  if (rows.length === 0) {
    return new Map();
  }

  const existing = new Map();
  const columnList = metadata.columns.map((column) => quoteIdent(column.column_name)).join(', ');

  for (let index = 0; index < rows.length; index += 100) {
    const batch = rows.slice(index, index + 100);
    const request = stagingPool.request();
    const whereParts = batch.map((row, batchIndex) => {
      const parts = metadata.pkColumns.map((pk, pkIndex) => {
        const paramName = `pk_${batchIndex}_${pkIndex}`;
        request.input(paramName, row[pk]);
        return `${quoteIdent(pk)} = @${paramName}`;
      });
      return `(${parts.join(' AND ')})`;
    });

    const result = await request.query(`
      SELECT ${columnList}
      FROM ${metadata.qualifiedName}
      WHERE ${whereParts.join(' OR ')}
    `);

    for (const row of result.recordset) {
      const key = metadata.pkColumns.map((pk) => String(row[pk])).join('\u001f');
      existing.set(key, row);
    }
  }

  return existing;
}

function buildInsertStatement(metadata, row) {
  const insertColumns = metadata.columns.map((column) => quoteIdent(column.column_name)).join(', ');
  const values = metadata.columns.map((column, index) => `@p${index}`).join(', ');
  return {
    sql: `
      INSERT INTO ${metadata.qualifiedName} (${insertColumns})
      VALUES (${values})
    `,
    inputs: metadata.columns.map((column, index) => ({
      name: `p${index}`,
      value: row[column.column_name],
    })),
  };
}

function buildUpdateStatement(metadata, row) {
  const setClause = metadata.columns
    .filter((column) => !metadata.pkColumns.includes(column.column_name))
    .map((column, index) => `${quoteIdent(column.column_name)} = @v${index}`)
    .join(', ');
  const whereClause = metadata.pkColumns
    .map((pk, index) => `${quoteIdent(pk)} = @k${index}`)
    .join(' AND ');

  const dataColumns = metadata.columns.filter((column) => !metadata.pkColumns.includes(column.column_name));

  return {
    sql: `
      UPDATE ${metadata.qualifiedName}
      SET ${setClause}
      WHERE ${whereClause}
    `,
    inputs: [
      ...dataColumns.map((column, index) => ({
        name: `v${index}`,
        value: row[column.column_name],
      })),
      ...metadata.pkColumns.map((pk, index) => ({
        name: `k${index}`,
        value: row[pk],
      })),
    ],
  };
}

async function upsertRows(stagingPool, metadata, rows, existingRows) {
  let inserted = 0;
  let updated = 0;
  let skipped = 0;

  for (const row of rows) {
    const key = metadata.pkColumns.map((pk) => String(row[pk])).join('\u001f');
    const existing = existingRows.get(key);

    if (!existing) {
      const insert = buildInsertStatement(metadata, row);
      const request = stagingPool.request();
      insert.inputs.forEach((input) => request.input(input.name, input.value));

      if (metadata.hasIdentity) {
        await request.batch(`
          SET IDENTITY_INSERT ${metadata.qualifiedName} ON;
          ${insert.sql}
          SET IDENTITY_INSERT ${metadata.qualifiedName} OFF;
        `);
      } else {
        await request.query(insert.sql);
      }
      inserted += 1;
      continue;
    }

    if (rowFingerprint(row, metadata.columns) === rowFingerprint(existing, metadata.columns)) {
      skipped += 1;
      continue;
    }

    const update = buildUpdateStatement(metadata, row);
    const request = stagingPool.request();
    update.inputs.forEach((input) => request.input(input.name, input.value));
    await request.query(update.sql);
    updated += 1;
  }

  return { inserted, updated, skipped };
}

async function saveTableSyncState(stagingPool, tableName, stats, errorMessage, runStartedUtc) {
  await stagingPool.request()
    .input('tableName', sql.NVarChar, tableName)
    .input('lastSyncUtc', sql.DateTime2, runStartedUtc)
    .input('lastRunUtc', sql.DateTime2, new Date())
    .input('rowsInserted', sql.Int, stats.inserted)
    .input('rowsUpdated', sql.Int, stats.updated)
    .input('rowsSkipped', sql.Int, stats.skipped)
    .input('lastError', sql.NVarChar, errorMessage)
    .query(`
      MERGE dbo.StagingSyncState AS target
      USING (SELECT @tableName AS TableName) AS source
      ON target.TableName = source.TableName
      WHEN MATCHED THEN
        UPDATE SET
          LastSyncUtc = @lastSyncUtc,
          LastRunUtc = @lastRunUtc,
          RowsInserted = @rowsInserted,
          RowsUpdated = @rowsUpdated,
          RowsSkipped = @rowsSkipped,
          LastError = @lastError
      WHEN NOT MATCHED THEN
        INSERT (TableName, LastSyncUtc, LastRunUtc, RowsInserted, RowsUpdated, RowsSkipped, LastError)
        VALUES (@tableName, @lastSyncUtc, @lastRunUtc, @rowsInserted, @rowsUpdated, @rowsSkipped, @lastError);
    `);
}

async function startSyncRun(stagingPool) {
  const result = await stagingPool.request()
    .input('startedAt', sql.DateTime2, new Date())
    .query(`
      INSERT INTO dbo.StagingSyncRun (StartedAt, Status)
      OUTPUT INSERTED.StagingSyncRunID AS run_id
      VALUES (@startedAt, N'Running')
    `);
  return result.recordset[0].run_id;
}

async function finishSyncRun(stagingPool, runId, status, summary, errorMessage) {
  await stagingPool.request()
    .input('runId', sql.Int, runId)
    .input('finishedAt', sql.DateTime2, new Date())
    .input('status', sql.NVarChar, status)
    .input('summaryJson', sql.NVarChar, summary ? JSON.stringify(summary) : null)
    .input('errorMessage', sql.NVarChar, errorMessage)
    .query(`
      UPDATE dbo.StagingSyncRun
      SET FinishedAt = @finishedAt,
          Status = @status,
          SummaryJson = @summaryJson,
          ErrorMessage = @errorMessage
      WHERE StagingSyncRunID = @runId
    `);
}

async function syncTable(prodPool, stagingPool, metadata, settings, runStartedUtc, log) {
  const lastSyncUtc = await getLastSyncUtc(stagingPool, metadata.tableName);
  const sinceUtc = new Date(lastSyncUtc.getTime() - settings.overlapMinutes * 60 * 1000);
  const maxPk = await getMaxPk(stagingPool, metadata);

  const rows = await fetchCandidateRows(
    prodPool,
    stagingPool,
    metadata,
    sinceUtc,
    maxPk,
    settings.batchSize
  );

  logMessage(
    log,
    '%s: %s candidate row(s) (watermark=%s, since=%s, maxPk=%s)',
    metadata.tableName,
    rows.length,
    metadata.watermarkColumn || 'none',
    sinceUtc.toISOString(),
    maxPk
  );

  if (rows.length === 0) {
    await saveTableSyncState(stagingPool, metadata.tableName, { inserted: 0, updated: 0, skipped: 0 }, null, runStartedUtc);
    return { inserted: 0, updated: 0, skipped: 0, candidates: 0 };
  }

  const existingRows = await fetchExistingRows(stagingPool, metadata, rows);
  const stats = await upsertRows(stagingPool, metadata, rows, existingRows);
  await saveTableSyncState(stagingPool, metadata.tableName, stats, null, runStartedUtc);

  return { ...stats, candidates: rows.length };
}

async function runStagingDatabaseSync(log = console.log) {
  const settings = getSyncSettings();
  const runStartedUtc = new Date();
  let prodPool;
  let stagingPool;
  let runId = null;

  logMessage(log, 'Starting staging database sync (%s -> %s)', settings.productionDb, settings.stagingDb);

  try {
    prodPool = await connectPool(settings.productionDb);
    stagingPool = await connectPool(settings.stagingDb);
    runId = await startSyncRun(stagingPool);

    const tables = await loadTables(prodPool, settings.excludedTables);
    const foreignKeys = await loadForeignKeys(prodPool);
    const orderedTables = sortTablesByDependency(tables, foreignKeys);
    const summary = {
      productionDb: settings.productionDb,
      stagingDb: settings.stagingDb,
      startedAt: runStartedUtc.toISOString(),
      tables: {},
    };

    for (const table of orderedTables) {
      const metadata = await loadTableMetadata(
        prodPool,
        table.schema_name,
        table.table_name,
        settings.watermarkCandidates
      );

      try {
        summary.tables[metadata.tableName] = await syncTable(
          prodPool,
          stagingPool,
          metadata,
          settings,
          runStartedUtc,
          log
        );
      } catch (err) {
        const message = err instanceof Error ? err.message : String(err);
        await saveTableSyncState(
          stagingPool,
          metadata.tableName,
          { inserted: 0, updated: 0, skipped: 0 },
          message,
          runStartedUtc
        );
        throw new Error(`Sync failed for ${metadata.tableName}: ${message}`);
      }
    }

    summary.finishedAt = new Date().toISOString();
    await finishSyncRun(stagingPool, runId, 'Success', summary, null);
    logMessage(log, 'Staging database sync completed successfully.');
    return summary;
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    if (stagingPool && runId) {
      await finishSyncRun(stagingPool, runId, 'Failed', null, message);
    }
    logMessage(log, 'Staging database sync failed: %s', message);
    throw err;
  } finally {
    if (prodPool) {
      await prodPool.close();
    }
    if (stagingPool) {
      await stagingPool.close();
    }
  }
}

module.exports = {
  runStagingDatabaseSync,
  WATERMARK_CANDIDATES,
};
