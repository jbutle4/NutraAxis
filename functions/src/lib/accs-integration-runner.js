const { parseCanonicalBody } = require('./canonical-order');
const { getIntegration } = require('./accs-integration-registry');
const { logIntegrationSuccess, logIntegrationFailure } = require('./accs-integration-log');
const { sendAccsIntegrationAlert } = require('./accs-integration-alerts');

const PERMANENT_ERROR_PATTERNS = [
  /not configured/i,
  /not connected/i,
  /missing/i,
  /invalid/i,
  /blocked/i,
  /no email route/i,
  /unauthorized/i,
  /forbidden/i,
];

function extractMeta(body, overrides = {}) {
  const parsed = parseCanonicalBody(body);
  const canonical = parsed.ok ? parsed.canonical : body;

  return {
    incrementId: String(overrides.incrementId ?? canonical?.incrementId ?? '(unknown)').trim(),
    orderEntityId: overrides.orderEntityId ?? canonical?.orderId ?? null,
    sourceEnvironment: String(overrides.sourceEnvironment ?? canonical?.sourceEnvironment ?? 'stage').trim(),
    externalRef: overrides.externalRef ?? null,
  };
}

function getDeliveryCount(message, context) {
  const fromMeta = Number(context?.triggerMetadata?.deliveryCount || 0);
  if (fromMeta > 0) {
    return fromMeta;
  }

  if (message && typeof message === 'object') {
    const fromMessage = Number(message.deliveryCount || message.DeliveryCount || 0);
    if (fromMessage > 0) {
      return fromMessage;
    }
  }

  return 1;
}

function isPermanentError(error) {
  const message = String(error?.message || error || '').trim();
  if (!message) {
    return false;
  }

  return PERMANENT_ERROR_PATTERNS.some((pattern) => pattern.test(message));
}

function isHardFailResult(result) {
  return Boolean(result?.hard_fail || result?.blocked);
}

function needsAttentionResult(result) {
  return Boolean(result?.needs_attention || (Array.isArray(result?.holds) && result.holds.length > 0));
}

async function reportIntegrationFailure(processCode, {
  meta,
  issueType,
  errors = [],
  holds = [],
  action = null,
  delivered = false,
  deliveryCount = null,
  resultPayload = {},
  context = null,
}) {
  const config = getIntegration(processCode);

  const logResult = await logIntegrationFailure(processCode, meta, issueType, {
    errors,
    holds,
    resultPayload,
  });

  const alert = await sendAccsIntegrationAlert({
    processCode,
    integration: config.integrationLabel,
    incrementId: meta.incrementId,
    orderEntityId: meta.orderEntityId,
    externalRef: meta.externalRef,
    sourceEnvironment: meta.sourceEnvironment,
    issueType,
    errors,
    holds,
    action: action || config.hardFailAction,
    processLogId: logResult.log_id ?? null,
    delivered,
    deliveryCount,
  });

  if (context) {
    if (!alert.ok) {
      context.warn(
        '%s alert failed for order %s: %s',
        processCode,
        meta.incrementId,
        alert.error || alert.skipped_reason || 'unknown error'
      );
    } else {
      context.log(
        '%s alert sent for order %s to %s (process_log_id=%s delivered=%s)',
        processCode,
        meta.incrementId,
        alert.ticketEmail,
        logResult.log_id ?? null,
        delivered
      );
    }
  }

  return {
    log: logResult,
    alert,
    process_log_id: logResult.log_id ?? null,
  };
}

async function runAccsOrderIntegration(processCode, message, context, executeFn) {
  const config = getIntegration(processCode);
  const deliveryCount = getDeliveryCount(message, context);

  let body = message;
  if (message && typeof message === 'object' && !Buffer.isBuffer(message)) {
    body = message;
  }

  const meta = extractMeta(body);

  try {
    const result = await executeFn(body, context);
    const resultMeta = extractMeta(body, {
      externalRef: result?.external_ref
        ?? result?.jazz_order_number
        ?? result?.doc_number
        ?? result?.qbo_transaction_id
        ?? null,
    });

    if (result?.skipped) {
      const logResult = await logIntegrationSuccess(
        processCode,
        resultMeta,
        `Skipped: ${result.reason || 'no work required'}`,
        result
      );

      context.log(
        '%s skipped increment_id=%s reason=%s process_log_id=%s',
        processCode,
        resultMeta.incrementId,
        result.reason || 'unknown',
        logResult.log_id ?? null
      );

      return {
        ...result,
        process_log_id: logResult.log_id ?? null,
      };
    }

    if (isHardFailResult(result)) {
      const report = await reportIntegrationFailure(processCode, {
        meta: resultMeta,
        issueType: result.issue_type || 'Payload blocked before delivery',
        errors: result.errors || result.validation_errors || [],
        holds: result.holds || [],
        delivered: false,
        deliveryCount,
        resultPayload: result,
        context,
      });

      return {
        ...result,
        process_log_id: report.process_log_id,
      };
    }

    if (needsAttentionResult(result)) {
      const report = await reportIntegrationFailure(processCode, {
        meta: resultMeta,
        issueType: result.issue_type || 'Delivered with errors or holds',
        errors: result.errors || [],
        holds: result.holds || [],
        delivered: true,
        deliveryCount,
        resultPayload: result,
        context,
      });

      return {
        ...result,
        process_log_id: report.process_log_id,
        alerted: true,
      };
    }

    const logResult = await logIntegrationSuccess(
      processCode,
      resultMeta,
      result.result_message || `Order ${resultMeta.incrementId} processed successfully.`,
      result
    );

    context.log(
      '%s success increment_id=%s process_log_id=%s',
      processCode,
      resultMeta.incrementId,
      logResult.log_id ?? null
    );

    return {
      ...result,
      process_log_id: logResult.log_id ?? null,
    };
  } catch (error) {
    const isLastAttempt = deliveryCount >= config.maxDeliveryCount;
    const permanent = isPermanentError(error);

    context.error(
      '%s failed increment_id=%s delivery_count=%s error=%s',
      processCode,
      meta.incrementId,
      deliveryCount,
      error.message
    );

    if (permanent || isLastAttempt) {
      const report = await reportIntegrationFailure(processCode, {
        meta,
        issueType: permanent
          ? 'Permanent integration failure'
          : 'Integration failed after retry exhaustion',
        errors: [error.message],
        delivered: false,
        deliveryCount,
        resultPayload: {
          delivery_count: deliveryCount,
          permanent,
        },
        context,
      });

      return {
        ok: false,
        hard_fail: true,
        increment_id: meta.incrementId,
        error: error.message,
        process_log_id: report.process_log_id,
        retried: !permanent,
      };
    }

    await logIntegrationFailure(processCode, meta, 'Transient integration failure — will retry', {
      errors: [error.message],
      extra: `Delivery attempt ${deliveryCount} of ${config.maxDeliveryCount}`,
      resultPayload: {
        delivery_count: deliveryCount,
        will_retry: true,
      },
    });

    throw error;
  }
}

module.exports = {
  extractMeta,
  getDeliveryCount,
  isPermanentError,
  reportIntegrationFailure,
  runAccsOrderIntegration,
};
