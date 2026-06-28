/**
 * ACCS Order Webhook — Azure Function (test stack)
 *
 * Validates ACCS webhook, enriches line items with fulfillment tags,
 * builds a canonical order message, and publishes to Service Bus topic
 * accs-order-canonical-test. Supplier email is handled by topic subscribers.
 *
 * Handles:
 *   GET  ?challenge=xxx  — ACCS webhook registration challenge
 *   POST                 — order event delivery
 */

const { app } = require('@azure/functions');
const { createHmac } = require('crypto');
const { enrichItemsWithFulfillment } = require('../lib/accs-client');
const { buildCanonicalOrder } = require('../lib/canonical-order');
const { suppliersToApplicationProperties } = require('../lib/order-suppliers');
const serviceBus = require('../lib/service-bus');
const orderFulfillmentLog = require('../lib/order-fulfillment-log');
const processLog = require('../lib/process-log');
const { sendAccsIntegrationAlert } = require('../lib/accs-integration-alerts');
const { getIntegration } = require('../lib/accs-integration-registry');
const { shouldLogSuccess, logIntegrationFailure } = require('../lib/accs-integration-log');
const adobeCommerce = require('../lib/adobe-commerce');

const WEBHOOK_SECRET = process.env.ACCS_WEBHOOK_SECRET || '';
const PROCESS_CODE = 'accs-order-webhook';

app.http('accs-order-webhook', {
  methods: ['GET', 'POST'],
  authLevel: 'anonymous',
  handler: async (request, context) => {
    if (request.method === 'GET') {
      const challenge = request.query.get('challenge');
      if (challenge) {
        context.log('Challenge received — echoing back');
        return respond(200, { challenge });
      }
      return respond(200, { ok: true, service: 'accs-order-webhook' });
    }

    context.log('accs-order-webhook — event received');

    let rawBody;
    try {
      rawBody = await request.text();
    } catch {
      return respond(400, { ok: false, error: 'Could not read body' });
    }

    if (WEBHOOK_SECRET) {
      const incoming = request.headers.get('x-nutraaxis-webhook-secret') || '';
      const adobeSig = request.headers.get('x-adobe-signature') || '';
      const hmac = createHmac('sha256', WEBHOOK_SECRET).update(rawBody).digest('base64');

      const secretOk = incoming === WEBHOOK_SECRET || adobeSig === hmac;
      if (!secretOk) {
        context.warn('Secret verification failed');
        return respond(401, { ok: false, error: 'Unauthorized' });
      }
    }

    let body;
    try {
      body = JSON.parse(rawBody);
    } catch {
      return respond(400, { ok: false, error: 'Invalid JSON' });
    }

    const order =
      body?.data?.value?.order ??
      body?.data?.order ??
      body?.eventData?.order ??
      body?.order ??
      body;

    const orderId = order?.increment_id || order?.entity_id || '(unknown)';
    context.log(`Order: #${orderId}`);

    if (typeof order !== 'object' || Array.isArray(order)) {
      context.log('No order object — acknowledging without action');
      return respond(200, { ok: true, message: 'Acknowledged' });
    }

    const entityId = Number(order.entity_id || 0);
    const incrementId = String(order.increment_id ?? '').trim();
    const sourceEnvironment = adobeCommerce.environment() === 'production' ? 'production' : 'stage';

    const rawItems = order.items || [];
    context.log(`Enriching ${rawItems.length} line item(s)...`);
    const enrichedItems = await enrichItemsWithFulfillment(rawItems, context);

    for (const item of enrichedItems) {
      context.log(`  ${item.sku} → fulfillment: ${item.fulfillment ?? '(none)'}`);
    }

    const canonicalResult = buildCanonicalOrder(order, enrichedItems, {
      sourceEnvironment,
      receivedAt: new Date().toISOString(),
      eventType: body?.type ?? body?.event ?? null,
    });

    if (!canonicalResult.ok) {
      return respond(400, { ok: false, error: canonicalResult.error });
    }

    if (!serviceBus.isConfigured()) {
      return respond(500, { ok: false, error: 'SERVICEBUS_CONNECTION_STRING is not configured.' });
    }

    const claim = await orderFulfillmentLog.claimForPublish(entityId, incrementId, sourceEnvironment);
    if (!claim.ok) {
      return respond(500, { ok: false, orderId: incrementId, error: claim.error });
    }

    if (claim.duplicate) {
      context.log('Duplicate webhook for entity_id=%s — already published', entityId);
      return respond(200, {
        ok: true,
        duplicate: true,
        orderId: incrementId,
        message_id: claim.message_id ?? null,
      });
    }

    let logId = 0;
    if (shouldLogSuccess()) {
      try {
        logId = await processLog.start(
          PROCESS_CODE,
          'ACCS Order Webhook',
          processLog.TRIGGER.MANUAL,
          null,
          {
            entity_id: entityId,
            increment_id: incrementId,
            source_environment: sourceEnvironment,
          }
        );
      } catch (error) {
        context.warn('Process log start failed: %s', error.message);
      }
    }

    const topicName = serviceBus.accsOrderTopicName();
    const appProperties = suppliersToApplicationProperties(canonicalResult.canonical.suppliers);

    try {
      const publish = await serviceBus.publishCanonicalOrder(
        canonicalResult.canonical,
        appProperties
      );

      await orderFulfillmentLog.markPublished(
        claim.log_id,
        publish.message_id,
        topicName
      );

      if (logId > 0) {
        await processLog.finish(
          logId,
          true,
          `Published order ${incrementId} to ${topicName}`,
          null,
          {
            entity_id: entityId,
            increment_id: incrementId,
            topic: topicName,
            suppliers: canonicalResult.canonical.suppliers,
            message_id: publish.message_id,
          }
        );
      }

      context.log(
        'Published canonical order %s to topic %s (message_id=%s)',
        incrementId,
        topicName,
        publish.message_id
      );

      return respond(200, {
        ok: true,
        orderId: incrementId,
        entity_id: entityId,
        topic: topicName,
        message_id: publish.message_id,
        suppliers: canonicalResult.canonical.suppliers,
      });
    } catch (error) {
      context.error('Publish failed: %s', error.message);

      if (claim.log_id > 0) {
        await orderFulfillmentLog.markFailed(claim.log_id, error.message);
      }

      let processLogId = logId > 0 ? logId : null;

      if (logId > 0) {
        await processLog.finish(logId, false, null, error.message, {
          entity_id: entityId,
          increment_id: incrementId,
        });
      } else {
        const logResult = await logIntegrationFailure(
          PROCESS_CODE,
          {
            incrementId,
            orderEntityId: entityId,
            sourceEnvironment,
          },
          'Canonical order publish failed — payload not delivered to Service Bus',
          {
            errors: [error.message],
            resultPayload: {
              entity_id: entityId,
              increment_id: incrementId,
            },
          }
        );
        processLogId = logResult.log_id ?? null;
      }

      await sendAccsIntegrationAlert({
        processCode: 'accs-order-webhook',
        integration: getIntegration('accs-order-webhook').integrationLabel,
        incrementId,
        orderEntityId: entityId,
        sourceEnvironment,
        issueType: 'Canonical order publish failed — payload not delivered to Service Bus',
        errors: [error.message],
        action: getIntegration('accs-order-webhook').hardFailAction,
        processLogId,
        delivered: false,
      });

      return respond(500, { ok: false, orderId: incrementId, error: error.message });
    }
  },
});

function respond(status, data) {
  return {
    status,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  };
}
