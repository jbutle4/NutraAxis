const { app } = require('@azure/functions');
const processLog = require('../lib/process-log');
const runner = require('../lib/runner');

async function parseBody(request) {
  try {
    return await request.json();
  } catch {
    return null;
  }
}

function normalizeTriggerType(value) {
  const trigger = String(value || processLog.TRIGGER.MANUAL).trim();
  return Object.values(processLog.TRIGGER).includes(trigger) ? trigger : processLog.TRIGGER.MANUAL;
}

// Same contract as Nutra-forecast-tool's process-execute so includes/process-functions-client.php can call either app.
app.http('process-execute', {
  methods: ['POST'],
  authLevel: 'function',
  handler: async (request, context) => {
    const body = await parseBody(request);
    if (!body || typeof body !== 'object') {
      return { status: 400, jsonBody: { ok: false, error: 'Request body must be JSON.' } };
    }

    const triggeredByUserId = body.triggered_by_user_id ?? null;

    if (body.log_id !== undefined && body.log_id !== null) {
      const logId = Number(body.log_id);
      context.log('process-execute rerun log_id=%s user_id=%s', logId, triggeredByUserId);
      const result = await runner.rerunFailedLog(logId, triggeredByUserId);
      return { status: result.ok ? 200 : 400, jsonBody: result };
    }

    const code = String(body.code || '').trim();
    if (code === '') {
      return { status: 400, jsonBody: { ok: false, error: 'code or log_id is required.' } };
    }

    const params = body.params && typeof body.params === 'object' ? body.params : {};
    const triggerType = normalizeTriggerType(body.trigger_type);
    context.log('process-execute code=%s trigger=%s user_id=%s', code, triggerType, triggeredByUserId);

    const result = await runner.execute(code, params, triggerType, triggeredByUserId);
    return { status: result.ok ? 200 : 500, jsonBody: result };
  },
});
