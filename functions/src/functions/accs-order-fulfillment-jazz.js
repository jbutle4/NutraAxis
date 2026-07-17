const { app } = require('@azure/functions');
const { runAccsOrderIntegration } = require('../lib/accs-integration-runner');
const { submitCartOrderToJazz } = require('../lib/jazz-order-submit');

app.serviceBusTopic('accs-order-fulfillment-jazz', {
  connection: 'SERVICEBUS_CONNECTION_STRING',
  topicName: '%ACCS_ORDER_TOPIC%',
  subscriptionName: '%ACCS_ORDER_SUB_CART%',
  handler: async (message, context) => runAccsOrderIntegration(
    'accs-order-fulfillment-jazz',
    message,
    context,
    (body, ctx) => submitCartOrderToJazz(body, ctx)
  ),
});
