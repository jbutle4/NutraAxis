const processLog = require('../process-log');
const processRunner = require('../process-runner');

async function executeRetry(logId) {
  const log = await processLog.get(logId);
  if (!log) {
    return {
      ok: false,
      skipped: true,
      log_id: logId,
      message: 'Process log row not found.',
    };
  }

  const code = String(log.ProcessCode || '').trim();
  if (code === '') {
    return {
      ok: false,
      skipped: true,
      log_id: logId,
      message: 'Process code missing on log row.',
    };
  }

  if (!(await processLog.markRetryRunning(logId))) {
    return {
      ok: true,
      skipped: true,
      log_id: logId,
      code,
      message: 'Retry skipped — log is not eligible (already running, succeeded, or not yet due).',
    };
  }

  const params = processLog.decodeParams(log);

  try {
    const result = await processRunner.invoke(code, params);
    const ok = Boolean(result.ok);
    const error = String(result.error || '').trim();
    const message = ok
      ? processRunner.buildResultMessage(code, result)
      : (error || 'Process failed.');

    await processLog.finish(
      logId,
      ok,
      ok ? message : null,
      ok ? null : message,
      result
    );

    return {
      ok,
      skipped: false,
      log_id: logId,
      code,
      message,
    };
  } catch (error) {
    const message = error.message;
    console.error(`process_retry executeRetry log ${logId}: ${message}`);

    await processLog.finish(logId, false, null, message);

    return {
      ok: false,
      skipped: false,
      log_id: logId,
      code,
      message,
    };
  }
}

module.exports = {
  executeRetry,
};
