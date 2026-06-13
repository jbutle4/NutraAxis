const sql = require('mssql');

const WATERMARK_CANDIDATES = [
  'ModifiedDate',
  'LastUpdatedDate',
  'ChangeDate',
  'GeneratedAt',
  'SummaryCaptureDate',
  'StartedAt',
  'CreatedAt',
  'CreateDate',
  'ImportedDate',
  'SnapshotDateTime',
  'LastAttemptAt',
];

const DEFAULT_EXCLUDED_TABLES = [
  'PasswordResetToken',
  'StagingSyncState',
  'StagingSyncRun',
];

function envValue(...keys) {
  for (const key of keys) {
    const value = process.env[key];
    if (value !== undefined && value !== '') {
      return value;
    }
  }
  return null;
}

function getSqlConfig(database) {
  const server = envValue('DB_SERVER', 'DB_HOST');
  const user = envValue('DB_USER');
  const password = envValue('DB_PASSWORD', 'DB_PASS');
  const port = Number(envValue('DB_PORT') || 1433);

  if (!server || !user || !password || !database) {
    throw new Error('Database settings are incomplete. Set DB_SERVER, DB_USER, DB_PASSWORD, and DB_NAME_* values.');
  }

  return {
    server,
    database,
    user,
    password,
    port,
    options: {
      encrypt: true,
      trustServerCertificate: false,
      connectTimeout: 15000,
      requestTimeout: 600000,
    },
    pool: {
      max: 5,
      min: 0,
      idleTimeoutMillis: 30000,
    },
  };
}

function getSyncSettings() {
  const productionDb = envValue('DB_NAME_PRODUCTION', 'DB_NAME_PROD') || 'nutraaxis';
  const stagingDb = envValue('DB_NAME_STAGING', 'DB_NAME_STAGE') || 'nutraaxis_staging';
  const excluded = (envValue('STAGING_SYNC_EXCLUDED_TABLES') || DEFAULT_EXCLUDED_TABLES.join(','))
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
  const overlapMinutes = Number(envValue('STAGING_SYNC_OVERLAP_MINUTES') || 5);
  const batchSize = Number(envValue('STAGING_SYNC_BATCH_SIZE') || 100);

  return {
    productionDb,
    stagingDb,
    excludedTables: new Set(excluded.map((name) => name.toLowerCase())),
    overlapMinutes,
    batchSize,
    watermarkCandidates: WATERMARK_CANDIDATES,
  };
}

async function connectPool(database) {
  return new sql.ConnectionPool(getSqlConfig(database)).connect();
}

module.exports = {
  sql,
  getSyncSettings,
  connectPool,
  WATERMARK_CANDIDATES,
};
