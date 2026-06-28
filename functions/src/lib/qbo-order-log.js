const { sql, connectPool, getSyncSettings } = require('./db-config');

function stagingDatabase() {
  return getSyncSettings().stagingDb;
}

async function loadEntry(incrementId, sourceEnvironment) {
  const pool = await connectPool(stagingDatabase());

  try {
    const result = await pool.request()
      .input('IncrementId', sql.NVarChar(50), incrementId)
      .input('SourceEnvironment', sql.NVarChar(20), sourceEnvironment)
      .query(`
        SELECT TOP (1)
          QBOOrderLogID,
          IncrementId,
          SourceEnvironment,
          QBODocNumber,
          QBOTransactionId,
          Status
        FROM dbo.QBOOrderLog
        WHERE IncrementId = @IncrementId
          AND SourceEnvironment = @SourceEnvironment
      `);

    return result.recordset[0] ?? null;
  } finally {
    await pool.close();
  }
}

async function markPosted(entry) {
  const pool = await connectPool(stagingDatabase());

  try {
    await pool.request()
      .input('AccsEntityId', sql.Int, entry.accs_entity_id)
      .input('IncrementId', sql.NVarChar(50), entry.increment_id)
      .input('SourceEnvironment', sql.NVarChar(20), entry.source_environment)
      .input('QBODocNumber', sql.NVarChar(50), entry.doc_number)
      .input('QBOTransactionId', sql.NVarChar(32), entry.transaction_id)
      .input('QBORealmId', sql.NVarChar(32), entry.realm_id)
      .input('Status', sql.NVarChar(30), entry.status || 'posted')
      .query(`
        MERGE dbo.QBOOrderLog AS target
        USING (
          SELECT
            @AccsEntityId AS AccsEntityId,
            @IncrementId AS IncrementId,
            @SourceEnvironment AS SourceEnvironment
        ) AS source
          ON target.IncrementId = source.IncrementId
         AND target.SourceEnvironment = source.SourceEnvironment
        WHEN MATCHED THEN
          UPDATE SET
            QBODocNumber = @QBODocNumber,
            QBOTransactionId = @QBOTransactionId,
            QBORealmId = @QBORealmId,
            Status = @Status,
            PostedAt = SYSUTCDATETIME(),
            LastError = NULL
        WHEN NOT MATCHED THEN
          INSERT (
            AccsEntityId, IncrementId, SourceEnvironment,
            QBODocNumber, QBOTransactionId, QBORealmId, Status, PostedAt
          )
          VALUES (
            @AccsEntityId, @IncrementId, @SourceEnvironment,
            @QBODocNumber, @QBOTransactionId, @QBORealmId, @Status, SYSUTCDATETIME()
          );
      `);
  } finally {
    await pool.close();
  }
}

async function markFailed(incrementId, sourceEnvironment, errorMessage) {
  const pool = await connectPool(stagingDatabase());

  try {
    await pool.request()
      .input('IncrementId', sql.NVarChar(50), incrementId)
      .input('SourceEnvironment', sql.NVarChar(20), sourceEnvironment)
      .input('LastError', sql.NVarChar(1000), String(errorMessage || '').slice(0, 1000))
      .query(`
        MERGE dbo.QBOOrderLog AS target
        USING (
          SELECT @IncrementId AS IncrementId, @SourceEnvironment AS SourceEnvironment
        ) AS source
          ON target.IncrementId = source.IncrementId
         AND target.SourceEnvironment = source.SourceEnvironment
        WHEN MATCHED THEN
          UPDATE SET Status = 'failed', LastError = @LastError
        WHEN NOT MATCHED THEN
          INSERT (IncrementId, SourceEnvironment, Status, LastError, CreatedAt)
          VALUES (@IncrementId, @SourceEnvironment, 'failed', @LastError, SYSUTCDATETIME());
      `);
  } finally {
    await pool.close();
  }
}

module.exports = {
  loadEntry,
  markPosted,
  markFailed,
};
