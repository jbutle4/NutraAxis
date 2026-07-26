const { app } = require('@azure/functions');
const { MESSAGE_TYPE_PROCESS_RETRY } = require('../lib/service-bus');
const processRetry = require('../lib/jobs/process-retry');

function parseMessageBody(message) {
  if (message === null || message === undefined) {
    return null;
  }

  if (typeof message === 'object' && !Buffer.isBuffer(message)) {
    return message;
  }

  const text = Buffer.isBuffer(message) ? message.toString('utf8') : String(message);
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

app.serviceBusQueue('process-retry', {
  connection: 'SERVICEBUS_CONNECTION_STRING',
  queueName: '%SERVICEBUS_PROCESS_RETRY_QUEUE%',
  handler: async (message, context) => {
    const body = parseMessageBody(message);
    const messageType = String(body?.type || MESSAGE_TYPE_PROCESS_RETRY);
    const logId = Number(body?.log_id || 0);

    if (messageType !== MESSAGE_TYPE_PROCESS_RETRY || logId <= 0) {
      context.log('process-retry: ignoring unsupported message %j', body);
      return;
    }

    context.log('process-retry: starting log_id=%s code=%s', logId, body?.process_code || '');

    const result = await processRetry.executeRetry(logId);
    context.log(
      'process-retry: log_id=%s ok=%s skipped=%s %s',
      result.log_id,
      result.ok,
      result.skipped,
      result.message
    );
  },
});
