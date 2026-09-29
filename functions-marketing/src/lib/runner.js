const processLog = require('./process-log');
const registry = require('./mkt/registry');

async function execute(code, params = {}, triggerType = processLog.TRIGGER.SCHEDULED, triggeredByUserId = null) {
  const entry = registry.REGISTRY[code];
  if (!entry) {
    return { ok: false, error: `Unknown marketing process code: ${code}`, log_id: null };
  }

  const logId = await processLog.start(entry.code, entry.name, triggerType, triggeredByUserId, params);
  try {
    const result = await registry.invoke(code, {
      ...params,
      trigger_type: triggerType,
      triggered_by_user_id: triggeredByUserId,
      log_id: logId,
    });
    const ok = Boolean(result.ok);
    const message = ok ? registry.buildResultMessage(code, result) : (String(result.error || '').trim() || 'Process failed.');
    await processLog.finish(logId, ok, ok ? message : null, ok ? null : message, result);
    return { ...result, log_id: logId, message };
  } catch (error) {
    await processLog.finish(logId, false, null, error.message);
    return { ok: false, error: error.message, log_id: logId };
  }
}

async function rerunFailedLog(logId, triggeredByUserId = null) {
  const log = await processLog.get(logId);
  if (!log) {
    return { ok: false, error: 'Process log entry not found.', log_id: null };
  }
  const status = String(log.Status || '');
  if (status !== processLog.STATUS.FAILED && status !== processLog.STATUS.ABANDONED) {
    return { ok: false, error: 'Only failed or abandoned process runs can be rerun.', log_id: logId };
  }
  return execute(String(log.ProcessCode || '').trim(), processLog.decodeParams(log), processLog.TRIGGER.MANUAL, triggeredByUserId);
}

module.exports = { execute, rerunFailedLog };
