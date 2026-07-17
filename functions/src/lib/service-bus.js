const { ServiceBusClient } = require('@azure/service-bus');

const MESSAGE_TYPE_PROCESS_RETRY = 'process-retry';
const MESSAGE_TYPE_ACCS_CANONICAL_ORDER = 'accs-canonical-order';

let client = null;

function connectionString() {
  const value = String(process.env.SERVICEBUS_CONNECTION_STRING || '').trim();
  if (value === '') {
    throw new Error('SERVICEBUS_CONNECTION_STRING is not configured.');
  }

  return value;
}

function isConfigured() {
  return String(process.env.SERVICEBUS_CONNECTION_STRING || '').trim() !== '';
}

function queueName(envKey, defaultName) {
  const value = String(process.env[envKey] || defaultName).trim();
  return value !== '' ? value : defaultName;
}

function processRetryQueueName() {
  return queueName('SERVICEBUS_PROCESS_RETRY_QUEUE', 'process-retry');
}

function accsOrderTopicName() {
  return queueName('ACCS_ORDER_TOPIC', 'accs-order-canonical-test');
}

function getClient() {
  if (!client) {
    client = new ServiceBusClient(connectionString());
  }

  return client;
}

async function closeClient() {
  if (client) {
    await client.close();
    client = null;
  }
}

function parseScheduledTime(value) {
  if (value instanceof Date) {
    return value;
  }

  const text = String(value || '').trim();
  if (text === '') {
    return new Date();
  }

  const parsed = new Date(text.includes('T') ? text : `${text}Z`);
  if (Number.isNaN(parsed.getTime())) {
    throw new Error(`Invalid scheduled enqueue time: ${value}`);
  }

  return parsed;
}

async function sendToQueue(queue, message, scheduledEnqueueTimeUtc = null) {
  const sender = getClient().createSender(queue);

  try {
    if (scheduledEnqueueTimeUtc) {
      const sequenceNumbers = await sender.scheduleMessages(
        [message],
        parseScheduledTime(scheduledEnqueueTimeUtc)
      );

      return {
        ok: true,
        sequence_number: sequenceNumbers[0] ?? null,
      };
    }

    await sender.sendMessages(message);

    return { ok: true, sequence_number: null };
  } finally {
    await sender.close();
  }
}

async function sendToTopic(topic, message) {
  const sender = getClient().createSender(topic);

  try {
    await sender.sendMessages(message);
    return { ok: true, message_id: message.messageId ?? null };
  } finally {
    await sender.close();
  }
}

async function publishCanonicalOrder(canonical, applicationProperties = {}) {
  const entityId = Number(canonical?.orderId || canonical?.order?.entity_id || 0);
  const incrementId = String(canonical?.incrementId ?? '').trim();

  if (!entityId) {
    throw new Error('Canonical order is missing orderId.');
  }

  const message = {
    body: canonical,
    messageId: `accs-order-${entityId}-${incrementId || 'unknown'}`,
    contentType: 'application/json',
    subject: MESSAGE_TYPE_ACCS_CANONICAL_ORDER,
    applicationProperties: {
      order_id: entityId,
      increment_id: incrementId,
      source_environment: String(canonical?.sourceEnvironment || 'stage'),
      schema_version: Number(canonical?.schemaVersion || 1),
      ...applicationProperties,
    },
  };

  return sendToTopic(accsOrderTopicName(), message);
}

async function scheduleProcessRetry(logId, processCode, attemptCount, scheduledEnqueueTimeUtc) {
  const message = {
    body: {
      type: MESSAGE_TYPE_PROCESS_RETRY,
      log_id: logId,
      process_code: processCode,
      attempt_count: attemptCount,
    },
    messageId: `process-retry-${logId}-${attemptCount}`,
    contentType: 'application/json',
  };

  return sendToQueue(processRetryQueueName(), message, scheduledEnqueueTimeUtc);
}

module.exports = {
  MESSAGE_TYPE_PROCESS_RETRY,
  MESSAGE_TYPE_ACCS_CANONICAL_ORDER,
  isConfigured,
  accsOrderTopicName,
  processRetryQueueName,
  publishCanonicalOrder,
  scheduleProcessRetry,
  sendToQueue,
  sendToTopic,
  closeClient,
};
