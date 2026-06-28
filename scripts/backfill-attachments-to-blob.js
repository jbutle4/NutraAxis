#!/usr/bin/env node
/**
 * Backfill legacy SQL attachment bytes to Azure Blob Storage.
 *
 * Usage:
 *   node scripts/backfill-attachments-to-blob.js [--dry-run] [--clear-sql] [--table=POAttachment] [--batch=25]
 */

const fs = require('fs');
const path = require('path');
const sql = require('mssql');
const { BlobServiceClient } = require('@azure/storage-blob');

const ATTACHMENT_TABLES = {
  POAttachment: {
    domain: 'po',
    idColumn: 'AttachmentID',
    entityColumn: 'POID',
  },
  ContractAttachment: {
    domain: 'legal',
    idColumn: 'AttachmentID',
    entityColumn: 'ContractID',
  },
  SKUMasterAttachment: {
    domain: 'catalog',
    idColumn: 'AttachmentID',
    entityColumn: 'SKUID',
  },
  PORAttachment: {
    domain: 'po-receiving',
    idColumn: 'AttachmentID',
    entityColumn: 'PORID',
  },
  POPaymentAttachment: {
    domain: 'po-payment',
    idColumn: 'POPaymentAttachmentID',
    entityColumn: 'PaymentID',
  },
  SupplierInvoiceAttachment: {
    domain: 'supplier-invoice',
    idColumn: 'AttachmentID',
    entityColumn: 'SupplierInvoiceID',
  },
  EnhLogAttachment: {
    domain: 'enh-log',
    idColumn: 'AttachmentID',
    entityColumn: 'EnhancementLogID',
  },
  TEAttachment: {
    domain: 'te',
    idColumn: 'AttachmentID',
    entityColumn: 'ReportID',
  },
};

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

function parseArgs(argv) {
  const options = {
    dryRun: false,
    clearSql: false,
    table: null,
    batchSize: 25,
  };

  for (const arg of argv) {
    if (arg === '--dry-run') options.dryRun = true;
    else if (arg === '--clear-sql') options.clearSql = true;
    else if (arg.startsWith('--table=')) options.table = arg.slice('--table='.length);
    else if (arg.startsWith('--batch=')) {
      const parsed = Number(arg.slice('--batch='.length));
      if (Number.isFinite(parsed) && parsed > 0) options.batchSize = parsed;
    }
  }

  return options;
}

function sanitizeFilename(fileName) {
  const base = path.basename(String(fileName).replace(/\\/g, '/'));
  const safe = base.replace(/[^\w.\- ()]+/gu, '_').replace(/^[._]+|[._]+$/g, '');
  return safe || 'attachment';
}

function buildBlobPath(domain, entityId, attachmentId, fileName) {
  const safeDomain = String(domain)
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9-]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'files';
  const safeName = sanitizeFilename(fileName);
  return `${safeDomain}/${entityId}/${attachmentId}-${safeName}`;
}

  const connectionString = envFirst(env, ['AZURE_STORAGE_CONNECTION_STRING']);
  const container = env.AZURE_STORAGE_CONTAINER || 'portal-attachments';
  if (!connectionString || !container) return null;

  return {
    client: BlobServiceClient.fromConnectionString(connectionString),
    container,
  };
}

async function uploadBlob(blobConfig, blobPath, content, contentType) {
  const normalizedPath = String(blobPath).replace(/\\/g, '/').replace(/^\/+/, '');
  const containerClient = blobConfig.client.getContainerClient(blobConfig.container);
  const blockBlobClient = containerClient.getBlockBlobClient(normalizedPath);

  try {
    const upload = await blockBlobClient.upload(content, content.length, {
      blobHTTPHeaders: {
        blobContentType: contentType || 'application/octet-stream',
      },
    });

    return {
      ok: !upload.errorCode,
      status: upload.errorCode ? 400 : 201,
      body: upload.errorCode || '',
    };
  } catch (error) {
    return {
      ok: false,
      status: 0,
      body: error.message,
    };
  }
}

function toBuffer(fileData) {
  if (fileData == null) return null;
  if (Buffer.isBuffer(fileData)) return fileData.length ? fileData : null;
  if (fileData instanceof Uint8Array) return fileData.length ? Buffer.from(fileData) : null;
  if (typeof fileData === 'string') {
    const buf = Buffer.from(fileData, 'binary');
    return buf.length ? buf : null;
  }
  return null;
}

async function backfillTable(pool, tableName, tableConfig, blobConfig, options) {
  const { domain, idColumn, entityColumn } = tableConfig;
  const summary = {
    table: tableName,
    scanned: 0,
    uploaded: 0,
    skipped: 0,
    failed: 0,
    cleared: 0,
    errors: [],
  };

  while (true) {
    const result = await pool.request().query(`
      SELECT TOP (${options.batchSize})
        ${idColumn} AS AttachmentId,
        ${entityColumn} AS EntityId,
        FileName,
        ContentType,
        FileSizeBytes,
        FileData
      FROM dbo.[${tableName}]
      WHERE BlobPath IS NULL
        AND FileData IS NOT NULL
        AND DATALENGTH(FileData) > 0
      ORDER BY ${idColumn}
    `);

    const rows = result.recordset || [];
    if (!rows.length) break;

    for (const row of rows) {
      summary.scanned += 1;
      const attachmentId = Number(row.AttachmentId || 0);
      const entityId = Number(row.EntityId || 0);
      const fileName = String(row.FileName || 'attachment');
      const contentType = String(row.ContentType || 'application/octet-stream');
      const content = toBuffer(row.FileData);

      if (attachmentId <= 0 || entityId <= 0 || !content) {
        summary.skipped += 1;
        continue;
      }

      const blobPath = buildBlobPath(domain, entityId, attachmentId, fileName);

      if (options.dryRun) {
        console.log(`[dry-run] ${tableName} id=${attachmentId} -> ${blobPath} (${content.length} bytes)`);
        summary.uploaded += 1;
        continue;
      }

      try {
        const upload = await uploadBlob(blobConfig, blobPath, content, contentType);
        if (!upload.ok) {
          summary.failed += 1;
          summary.errors.push({
            table: tableName,
            attachmentId,
            blobPath,
            status: upload.status,
            message: upload.body.slice(0, 300),
          });
          continue;
        }

        const updateRequest = pool.request()
          .input('path', sql.NVarChar(512), blobPath)
          .input('id', sql.Int, attachmentId);

        if (options.clearSql) {
          await updateRequest.query(`
            UPDATE dbo.[${tableName}]
            SET BlobPath = @path, FileData = NULL
            WHERE ${idColumn} = @id
          `);
          summary.cleared += 1;
        } else {
          await updateRequest.query(`
            UPDATE dbo.[${tableName}]
            SET BlobPath = @path
            WHERE ${idColumn} = @id
          `);
        }

        summary.uploaded += 1;
        console.log(`Uploaded ${tableName} id=${attachmentId} -> ${blobPath}`);
      } catch (error) {
        summary.failed += 1;
        summary.errors.push({
          table: tableName,
          attachmentId,
          blobPath,
          message: error.message,
        });
      }
    }

    if (rows.length < options.batchSize) break;
  }

  return summary;
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  const env = {
    ...loadEnv(path.join(__dirname, '..', '.env')),
    ...Object.fromEntries(
      [
        'DB_HOST',
        'DB_SERVER',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'DB_PASSWORD',
        'DB_PORT',
        'AZURE_STORAGE_CONNECTION_STRING',
        'AZURE_STORAGE_ACCOUNT',
        'AZURE_STORAGE_CONTAINER',
      ]
        .filter((key) => process.env[key])
        .map((key) => [key, process.env[key]])
    ),
  };

  const blobConfig = loadBlobConfig(env);
  if (!blobConfig) {
    console.error('Azure Blob Storage is not configured. Set AZURE_STORAGE_CONNECTION_STRING in .env.');
    process.exit(1);
  }

  const dbConfig = {
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

  if (!dbConfig.server || !dbConfig.database || !dbConfig.user || !dbConfig.password) {
    console.error('Missing required DB credentials in .env');
    process.exit(1);
  }

  const tableNames = options.table
    ? [options.table]
    : Object.keys(ATTACHMENT_TABLES);

  for (const tableName of tableNames) {
    if (!ATTACHMENT_TABLES[tableName]) {
      console.error(`Unknown attachment table: ${tableName}`);
      process.exit(1);
    }
  }

  console.log(
    `Starting attachment backfill (${options.dryRun ? 'dry-run' : 'live'})`
      + `${options.clearSql ? ' with SQL clear' : ''}, batch=${options.batchSize}`
  );

  const pool = await sql.connect(dbConfig);
  const results = [];

  try {
    for (const tableName of tableNames) {
      results.push(await backfillTable(pool, tableName, ATTACHMENT_TABLES[tableName], blobConfig, options));
    }
  } finally {
    await pool.close();
  }

  console.log(JSON.stringify({ options, results }, null, 2));

  const failed = results.reduce((sum, row) => sum + row.failed, 0);
  if (failed > 0) process.exit(1);
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
