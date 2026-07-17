const { app } = require('@azure/functions');
const { runAccsOrderIntegration } = require('../lib/accs-integration-runner');
const { insertCanonicalOrderToQbo } = require('../lib/jobs/accs-order-qbo-insert');

app.serviceBusTopic('accs-qbo-sandbox-new-order-insert', {
  connection: 'SERVICEBUS_CONNECTION_STRING',
  topicName: '%ACCS_ORDER_TOPIC%',
  subscriptionName: '%ACCS_ORDER_SUB_QBO%',
  handler: async (message, context) => runAccsOrderIntegration(
    'accs-qbo-sandbox-new-order-insert',
    message,
    context,
    (body, ctx) => insertCanonicalOrderToQbo(body, ctx)
  ),
});
