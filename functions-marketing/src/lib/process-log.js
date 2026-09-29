const { sql, connectPool, getProductionDatabase } = require('./db-config');

// Same dbo.ProcessExecutionLog rows the portal Process Log reads. Marketing jobs are
// idempotent and re-run on their next schedule, so failures are not auto-retried here.
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

function nowSql() {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

function decodeParams(log) {
  const raw = String(log?.ProcessParams || '').trim();
  if (raw === '') return {};
  try {
    const decoded = JSON.parse(raw);
    return decoded && typeof decoded === 'object' && !Array.isArray(decoded) ? decoded : {};
  } catch {
    return {};
  }
}

async function withPool(fn) {
  const pool = await connectPool(getProductionDatabase());
  try {
    return await fn(pool);
  } finally {
    await pool.close();
  }
}

async function get(logId) {
  if (!(logId > 0)) return null;
  return withPool(async (pool) => {
    const result = await pool.request()
      .input('log_id', sql.Int, logId)
      .query('SELECT * FROM dbo.ProcessExecutionLog WHERE ProcessExecutionLogID = @log_id');
    return result.recordset[0] || null;
  });
}

async function start(processCode, processName, triggerType = TRIGGER.SCHEDULED, triggeredByUserId = null, params = {}) {
  const startedAt = nowSql();
  return withPool(async (pool) => {
    const result = await pool.request()
      .input('process_code', sql.NVarChar(100), processCode)
      .input('process_name', sql.NVarChar(200), processName)
      .input('started_at', sql.DateTime2, startedAt)
      .input('status', sql.NVarChar(20), STATUS.RUNNING)
      .input('trigger_type', sql.NVarChar(20), triggerType)
      .input('triggered_by', sql.Int, triggeredByUserId)
      .input('process_params', sql.NVarChar(sql.MAX), params && Object.keys(params).length ? JSON.stringify(params) : null)
      .query(`
        INSERT INTO dbo.ProcessExecutionLog (
          ProcessCode, ProcessName, StartedAt, LastAttemptAt, CreatedAt,
          Status, TriggerType, TriggeredByUserID, ProcessParams, AttemptCount, MaxAttempts
        )
        OUTPUT INSERTED.ProcessExecutionLogID
        VALUES (
          @process_code, @process_name, @started_at, @started_at, @started_at,
          @status, @trigger_type, @triggered_by, @process_params, 0, 1
        )
      `);
    return result.recordset[0].ProcessExecutionLogID;
  });
}

async function finish(logId, ok, resultMessage = null, errorMessage = null, resultPayload = null) {
  if (!(logId > 0)) return;
  const finishedAt = nowSql();
  await withPool((pool) => pool.request()
    .input('finished_at', sql.DateTime2, finishedAt)
    .input('status', sql.NVarChar(20), ok ? STATUS.SUCCESS : STATUS.FAILED)
    .input('result_message', sql.NVarChar(sql.MAX), ok ? resultMessage : null)
    .input('error_message', sql.NVarChar(sql.MAX), ok ? null : (errorMessage || resultMessage || 'Process failed.'))
    .input('result_json', sql.NVarChar(sql.MAX), resultPayload !== null && resultPayload !== undefined ? JSON.stringify(resultPayload) : null)
    .input('log_id', sql.Int, logId)
    .query(`
      UPDATE dbo.ProcessExecutionLog
      SET FinishedAt = @finished_at, LastAttemptAt = @finished_at, Status = @status,
          ResultMessage = @result_message, ErrorMessage = @error_message, ResultJson = @result_json,
          AttemptCount = AttemptCount + 1, NextRetryAt = NULL
      WHERE ProcessExecutionLogID = @log_id
    `));
}

module.exports = {
  STATUS,
  TRIGGER,
  start,
  finish,
  get,
  decodeParams,
};
