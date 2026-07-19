const { app } = require('@azure/functions');
const { runAccsOrderIntegration } = require('../lib/accs-integration-runner');
const { parseServiceBusBody, sendSupplierFulfillmentEmail } = require('../lib/order-fulfillment-subscriber');

const SUPPLIER_CODE = 'CPPC';

app.serviceBusTopic('accs-cppc-test-new-order', {
  connection: 'SERVICEBUS_CONNECTION_STRING',
  topicName: '%ACCS_ORDER_TOPIC%',
  subscriptionName: '%ACCS_ORDER_SUB_CPPC%',
  handler: async (message, context) => runAccsOrderIntegration(
    'accs-cppc-test-new-order',
    message,
    context,
    (body, ctx) => sendSupplierFulfillmentEmail(body, SUPPLIER_CODE, ctx)
  ),
});
