const { sql, connectPool, getProductionDatabase } = require('./db-config');

const STATUS = {
  RUNNING: 'Running',
  SUCCESS: 'Success',
  FAILED: 'Failed',
  ABANDONED: 'Abandoned',
};

const TRIGGER = {
  SCHEDULED: 'Scheduled',
  MANUAL: 'Manual',
  RETRY: 'Retry',
};

const DEFAULT_MAX_ATTEMPTS = 3;

function nowSql() {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

function retryDelayMinutes(attemptCount) {
  return 2 ** Math.max(1, attemptCount);
}

function encodeParams(params) {
  if (!params || Object.keys(params).length === 0) {
    return null;
  }

  return JSON.stringify(params);
}

function decodeParams(log) {
  if (!log) {
    return {};
  }

  const raw = String(log.ProcessParams || '').trim();
  if (raw !== '') {
    try {
      const decoded = JSON.parse(raw);
      return decoded && typeof decoded === 'object' && !Array.isArray(decoded) ? decoded : {};
    } catch {
      // Fall through to ResultJson.
    }
  }

  const resultJson = String(log.ResultJson || '').trim();
  if (resultJson === '') {
    return {};
  }

  try {
    const decoded = JSON.parse(resultJson);
    if (!decoded || typeof decoded !== 'object' || Array.isArray(decoded)) {
      return {};
    }

    const params = {};
    if (decoded.summary_date) {
      params.date = String(decoded.summary_date);
    }

    return params;
  } catch {
    return {};
  }
}

async function get(logId, pool = null) {
  if (logId <= 0) {
    return null;
  }

  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());
  try {
    const result = await db.request()
      .input('log_id', sql.Int, logId)
      .query(`
        SELECT *
        FROM dbo.ProcessExecutionLog
        WHERE ProcessExecutionLogID = @log_id
      `);

    return result.recordset[0] || null;
  } finally {
    if (owned) {
      await db.close();
    }
  }
}

async function start(processCode, processName, triggerType = TRIGGER.SCHEDULED, triggeredByUserId = null, params = {}, maxAttempts = DEFAULT_MAX_ATTEMPTS, pool = null) {
  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());
  const startedAt = nowSql();

  try {
    const result = await db.request()
      .input('process_code', sql.NVarChar(100), processCode)
      .input('process_name', sql.NVarChar(200), processName)
      .input('started_at', sql.DateTime2, startedAt)
      .input('last_attempt_at', sql.DateTime2, startedAt)
      .input('created_at', sql.DateTime2, startedAt)
      .input('status', sql.NVarChar(20), STATUS.RUNNING)
      .input('trigger_type', sql.NVarChar(20), triggerType)
      .input('triggered_by', sql.Int, triggeredByUserId)
      .input('process_params', sql.NVarChar(sql.MAX), encodeParams(params))
      .input('max_attempts', sql.Int, Math.max(1, maxAttempts))
      .query(`
        INSERT INTO dbo.ProcessExecutionLog (
          ProcessCode, ProcessName, StartedAt, LastAttemptAt, CreatedAt,
          Status, TriggerType, TriggeredByUserID,
          ProcessParams, AttemptCount, MaxAttempts
        )
        OUTPUT INSERTED.ProcessExecutionLogID
        VALUES (
          @process_code, @process_name, @started_at, @last_attempt_at, @created_at,
          @status, @trigger_type, @triggered_by,
          @process_params, 0, @max_attempts
        )
      `);

    return result.recordset[0].ProcessExecutionLogID;
  } finally {
    if (owned) {
      await db.close();
    }
  }
}

async function finishSuccess(logId, resultMessage = null, resultPayload = null, pool = null) {
  if (logId <= 0) {
    return;
  }

  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());
  const finishedAt = nowSql();
  const resultJson = resultPayload !== null ? JSON.stringify(resultPayload) : null;

  try {
    await db.request()
      .input('finished_at', sql.DateTime2, finishedAt)
      .input('last_attempt_at', sql.DateTime2, finishedAt)
      .input('status', sql.NVarChar(20), STATUS.SUCCESS)
      .input('result_message', sql.NVarChar(sql.MAX), resultMessage)
      .input('result_json', sql.NVarChar(sql.MAX), resultJson)
      .input('log_id', sql.Int, logId)
      .query(`
        UPDATE dbo.ProcessExecutionLog
        SET
          FinishedAt = @finished_at,
          LastAttemptAt = @last_attempt_at,
          Status = @status,
          ResultMessage = @result_message,
          ErrorMessage = NULL,
          ResultJson = @result_json,
          NextRetryAt = NULL
        WHERE ProcessExecutionLogID = @log_id
      `);
  } finally {
    if (owned) {
      await db.close();
    }
  }
}

async function finishFailure(logId, errorMessage, resultPayload = null, pool = null) {
  if (logId <= 0) {
    return;
  }

  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());

  try {
    const log = await get(logId, db);
    if (!log) {
      return;
    }

    const finishedAt = nowSql();
    const attemptCount = (log.AttemptCount || 0) + 1;
    const maxAttempts = Math.max(1, log.MaxAttempts || DEFAULT_MAX_ATTEMPTS);
    const resultJson = resultPayload !== null
      ? JSON.stringify(resultPayload)
      : log.ResultJson;

    let status = STATUS.FAILED;
    let nextRetryAt = null;
    let retryAt = null;

    if (attemptCount <= maxAttempts) {
      retryAt = new Date(Date.now() + retryDelayMinutes(attemptCount) * 60 * 1000);
      nextRetryAt = retryAt.toISOString().slice(0, 19).replace('T', ' ');
    } else {
      status = STATUS.ABANDONED;
    }

    await db.request()
      .input('finished_at', sql.DateTime2, finishedAt)
      .input('last_attempt_at', sql.DateTime2, finishedAt)
      .input('status', sql.NVarChar(20), status)
      .input('error_message', sql.NVarChar(sql.MAX), errorMessage)
      .input('result_json', sql.NVarChar(sql.MAX), resultJson)
      .input('attempt_count', sql.Int, attemptCount)
      .input('next_retry_at', sql.DateTime2, nextRetryAt)
      .input('log_id', sql.Int, logId)
      .query(`
        UPDATE dbo.ProcessExecutionLog
        SET
          FinishedAt = @finished_at,
          LastAttemptAt = @last_attempt_at,
          Status = @status,
          ResultMessage = NULL,
          ErrorMessage = @error_message,
          ResultJson = @result_json,
          AttemptCount = @attempt_count,
          NextRetryAt = @next_retry_at
        WHERE ProcessExecutionLogID = @log_id
      `);

    if (status === STATUS.ABANDONED) {
      const processAlerts = require('./process-alerts');
      await processAlerts.onAbandoned(
        String(log.ProcessCode || ''),
        String(log.ProcessName || ''),
        errorMessage,
        {
          log_id: logId,
          attempt_count: attemptCount,
          max_attempts: maxAttempts,
        }
      );
    } else if (retryAt) {
      try {
        const serviceBus = require('./service-bus');
        if (serviceBus.isConfigured()) {
          await serviceBus.scheduleProcessRetry(
            logId,
            String(log.ProcessCode || ''),
            attemptCount,
            retryAt
          );
        } else {
          console.error(
            `process_log finishFailure: Service Bus not configured; retry for log ${logId} will not run automatically.`
          );
        }
      } catch (error) {
        console.error(
          `process_log finishFailure: failed to schedule retry for log ${logId}: ${error.message}`
        );
      }
    }
  } finally {
    if (owned) {
      await db.close();
    }
  }
}

async function markRetryRunning(logId, pool = null) {
  if (logId <= 0) {
    return false;
  }

  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());
  const now = nowSql();

  try {
    const result = await db.request()
      .input('running', sql.NVarChar(20), STATUS.RUNNING)
      .input('last_attempt_at', sql.DateTime2, now)
      .input('log_id', sql.Int, logId)
      .input('failed', sql.NVarChar(20), STATUS.FAILED)
      .input('now', sql.DateTime2, now)
      .query(`
        UPDATE dbo.ProcessExecutionLog
        SET
          Status = @running,
          LastAttemptAt = @last_attempt_at,
          FinishedAt = NULL,
          ResultMessage = NULL
        WHERE ProcessExecutionLogID = @log_id
          AND Status = @failed
          AND NextRetryAt IS NOT NULL
          AND NextRetryAt <= @now
          AND AttemptCount <= MaxAttempts
      `);

    return (result.rowsAffected[0] || 0) > 0;
  } finally {
    if (owned) {
      await db.close();
    }
  }
}

async function listRetryCandidates(pool = null) {
  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());
  const now = nowSql();

  try {
    const result = await db.request()
      .input('failed', sql.NVarChar(20), STATUS.FAILED)
      .input('now', sql.DateTime2, now)
      .query(`
        SELECT
          ProcessExecutionLogID,
          ProcessCode,
          ProcessName,
          ProcessParams,
          AttemptCount,
          MaxAttempts,
          NextRetryAt
        FROM dbo.ProcessExecutionLog
        WHERE Status = @failed
          AND NextRetryAt IS NOT NULL
          AND NextRetryAt <= @now
          AND AttemptCount <= MaxAttempts
        ORDER BY NextRetryAt ASC, ProcessExecutionLogID ASC
      `);

    return result.recordset || [];
  } finally {
    if (owned) {
      await db.close();
    }
  }
}

async function finish(logId, ok, resultMessage = null, errorMessage = null, resultPayload = null, pool = null) {
  if (ok) {
    await finishSuccess(logId, resultMessage, resultPayload, pool);
  } else {
    await finishFailure(logId, errorMessage || resultMessage || 'Process failed.', resultPayload, pool);
  }
}

module.exports = {
  STATUS,
  TRIGGER,
  start,
  finish,
  get,
  decodeParams,
  markRetryRunning,
  listRetryCandidates,
};
