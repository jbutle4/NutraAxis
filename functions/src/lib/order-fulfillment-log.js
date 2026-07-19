const { sql, connectPool, getSyncSettings } = require('./db-config');

function stagingDatabase() {
  return getSyncSettings().stagingDb;
}

async function loadEntry(pool, entityId, sourceEnvironment) {
  const result = await pool.request()
    .input('AccsEntityId', sql.Int, entityId)
    .input('SourceEnvironment', sql.NVarChar(20), sourceEnvironment)
    .query(`
      SELECT TOP (1)
        OrderFulfillmentLogID,
        AccsEntityId,
        IncrementId,
        SourceEnvironment,
        Status,
        PublishedToTopicAt,
        ServiceBusMessageId
      FROM dbo.OrderFulfillmentLog
      WHERE AccsEntityId = @AccsEntityId
        AND SourceEnvironment = @SourceEnvironment
    `);

  return result.recordset[0] ?? null;
}

async function claimForPublish(entityId, incrementId, sourceEnvironment) {
  const pool = await connectPool(stagingDatabase());

  try {
    const existing = await loadEntry(pool, entityId, sourceEnvironment);
    if (existing && String(existing.Status).toLowerCase() === 'published') {
      return {
        ok: true,
        duplicate: true,
        log_id: Number(existing.OrderFulfillmentLogID),
        message_id: existing.ServiceBusMessageId ?? null,
      };
    }

    if (existing) {
      await pool.request()
        .input('OrderFulfillmentLogID', sql.Int, existing.OrderFulfillmentLogID)
        .input('IncrementId', sql.NVarChar(50), incrementId)
        .query(`
          UPDATE dbo.OrderFulfillmentLog
          SET IncrementId = @IncrementId,
              Status = 'pending',
              LastError = NULL
          WHERE OrderFulfillmentLogID = @OrderFulfillmentLogID
        `);

      return {
        ok: true,
        duplicate: false,
        log_id: Number(existing.OrderFulfillmentLogID),
      };
    }

    const insert = await pool.request()
      .input('AccsEntityId', sql.Int, entityId)
      .input('IncrementId', sql.NVarChar(50), incrementId)
      .input('SourceEnvironment', sql.NVarChar(20), sourceEnvironment)
      .query(`
        INSERT INTO dbo.OrderFulfillmentLog (
          AccsEntityId,
          IncrementId,
          SourceEnvironment,
          Status
        )
        OUTPUT INSERTED.OrderFulfillmentLogID AS log_id
        VALUES (
          @AccsEntityId,
          @IncrementId,
          @SourceEnvironment,
          'pending'
        )
      `);

    return {
      ok: true,
      duplicate: false,
      log_id: Number(insert.recordset[0]?.log_id ?? 0),
    };
  } catch (error) {
    if (String(error.message || '').includes('UQ_OrderFulfillmentLog_Entity')) {
      const existing = await loadEntry(pool, entityId, sourceEnvironment);
      if (existing && String(existing.Status).toLowerCase() === 'published') {
        return {
          ok: true,
          duplicate: true,
          log_id: Number(existing.OrderFulfillmentLogID),
          message_id: existing.ServiceBusMessageId ?? null,
        };
      }
    }

    return { ok: false, error: error.message };
  } finally {
    await pool.close();
  }
}

async function markPublished(logId, messageId, topicName) {
  const pool = await connectPool(stagingDatabase());

  try {
    await pool.request()
      .input('OrderFulfillmentLogID', sql.Int, logId)
      .input('ServiceBusMessageId', sql.NVarChar(128), messageId)
      .input('TopicName', sql.NVarChar(200), topicName)
      .query(`
        UPDATE dbo.OrderFulfillmentLog
        SET Status = 'published',
            PublishedToTopicAt = SYSUTCDATETIME(),
            ServiceBusMessageId = @ServiceBusMessageId,
            TopicName = @TopicName,
            LastError = NULL
        WHERE OrderFulfillmentLogID = @OrderFulfillmentLogID
      `);
  } finally {
    await pool.close();
  }
}

async function markFailed(logId, errorMessage) {
  const pool = await connectPool(stagingDatabase());

  try {
    await pool.request()
      .input('OrderFulfillmentLogID', sql.Int, logId)
      .input('LastError', sql.NVarChar(1000), String(errorMessage || '').slice(0, 1000))
      .query(`
        UPDATE dbo.OrderFulfillmentLog
        SET Status = 'failed',
            LastError = @LastError
        WHERE OrderFulfillmentLogID = @OrderFulfillmentLogID
      `);
  } finally {
    await pool.close();
  }
}

module.exports = {
  claimForPublish,
  markPublished,
  markFailed,
};
