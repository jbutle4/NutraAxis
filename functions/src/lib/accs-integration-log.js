const processLog = require('./process-log');
const { getIntegration } = require('./accs-integration-registry');

// ACCS_INTEGRATION_LOG_MODE: "all" (default) logs successes/skips and failures;
// "failures_only" skips Process Log DB writes for success/skipped paths only.
const LOG_MODE_ALL = 'all';
const LOG_MODE_FAILURES_ONLY = 'failures_only';

function getIntegrationLogMode() {
  const mode = String(process.env.ACCS_INTEGRATION_LOG_MODE || LOG_MODE_ALL).trim().toLowerCase();
  return mode === LOG_MODE_FAILURES_ONLY ? LOG_MODE_FAILURES_ONLY : LOG_MODE_ALL;
}

function isFailuresOnlyMode() {
  return getIntegrationLogMode() === LOG_MODE_FAILURES_ONLY;
}

function shouldLogSuccess() {
  return !isFailuresOnlyMode();
}

function buildErrorMessage(issueType, errors = [], holds = [], extra = '') {
  const parts = [issueType];

  if (errors.length > 0) {
    parts.push(errors.join('; '));
  }

  if (holds.length > 0) {
    const holdText = holds.map((hold) => {
      if (typeof hold === 'string') {
        return hold;
      }

      return hold.explanation ? `${hold.reason}: ${hold.explanation}` : hold.reason;
    }).join('; ');
    parts.push(`Holds: ${holdText}`);
  }

  if (extra) {
    parts.push(extra);
  }

  return parts.join(' — ').slice(0, 3900);
}

function baseParams(meta, extras = {}) {
  return {
    increment_id: meta.incrementId,
    entity_id: meta.orderEntityId,
    source_environment: meta.sourceEnvironment,
    external_ref: meta.externalRef ?? null,
    ...extras,
  };
}

async function logIntegrationSuccess(processCode, meta, resultMessage, resultPayload = {}) {
  if (!shouldLogSuccess()) {
    return {
      ok: true,
      log_id: null,
      skipped_process_log: true,
    };
  }

  const config = getIntegration(processCode);
  let logId = 0;

  try {
    logId = await processLog.start(
      processCode,
      config.processName,
      processLog.TRIGGER.MANUAL,
      null,
      baseParams(meta, resultPayload),
      1
    );

    await processLog.finish(
      logId,
      true,
      resultMessage,
      null,
      {
        ok: true,
        ...baseParams(meta, resultPayload),
      }
    );
  } catch (error) {
    return {
      ok: false,
      log_id: logId,
      error: error.message || 'Unable to write process log entry.',
    };
  }

  return {
    ok: true,
    log_id: logId,
  };
}

async function logIntegrationFailure(processCode, meta, issueType, {
  errors = [],
  holds = [],
  extra = '',
  resultPayload = {},
} = {}) {
  const config = getIntegration(processCode);
  let logId = 0;

  const payload = {
    ok: false,
    issue_type: issueType,
    validation_errors: errors,
    holds,
    ...baseParams(meta, resultPayload),
  };

  try {
    logId = await processLog.start(
      processCode,
      config.processName,
      processLog.TRIGGER.MANUAL,
      null,
      payload,
      1
    );

    await processLog.finish(
      logId,
      false,
      null,
      buildErrorMessage(issueType, errors, holds, extra),
      payload
    );
  } catch (error) {
    return {
      ok: false,
      log_id: logId,
      error: error.message || 'Unable to write process log entry.',
    };
  }

  return {
    ok: true,
    log_id: logId,
  };
}

module.exports = {
  getIntegrationLogMode,
  isFailuresOnlyMode,
  shouldLogSuccess,
  logIntegrationSuccess,
  logIntegrationFailure,
};
