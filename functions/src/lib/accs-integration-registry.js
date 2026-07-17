const INTEGRATIONS = {
  'accs-order-webhook': {
    processName: 'ACCS Order Webhook',
    integrationLabel: 'ACCS Webhook',
    maxDeliveryCount: 1,
    hardFailAction: 'Review ACCS webhook payload and Service Bus configuration, then replay the order event.',
  },
  'accs-order-fulfillment-jazz': {
    processName: 'ACCS Jazz Order Fulfillment',
    integrationLabel: 'Jazz / Cart',
    maxDeliveryCount: 10,
    hardFailAction: 'Correct the ACCS order data (especially ship-to address) and resubmit to Jazz.',
  },
  'accs-cppc-test-new-order': {
    processName: 'ACCS CPPC Order Notification',
    integrationLabel: 'CPPC / NutraLogics',
    maxDeliveryCount: 10,
    hardFailAction: 'Verify CPPC email routing configuration and resend the supplier notification.',
  },
  'accs-mtl-test-new-order': {
    processName: 'ACCS MTL Order Notification',
    integrationLabel: 'MTL',
    maxDeliveryCount: 10,
    hardFailAction: 'Verify MTL email routing configuration and resend the supplier notification.',
  },
  'accs-qbo-sandbox-new-order-insert': {
    processName: 'ACCS QBO Sandbox Order Insert',
    integrationLabel: 'QuickBooks Sandbox',
    maxDeliveryCount: 10,
    hardFailAction: 'Review QBO sandbox connection, SKU mapping, and replay the sales receipt insert.',
  },
};

function getIntegration(processCode) {
  const config = INTEGRATIONS[processCode];
  if (!config) {
    throw new Error(`Unknown ACCS integration process code: ${processCode}`);
  }

  return {
    processCode,
    ...config,
  };
}

function listIntegrations() {
  return Object.entries(INTEGRATIONS).map(([processCode, config]) => ({
    processCode,
    ...config,
  }));
}

module.exports = {
  INTEGRATIONS,
  getIntegration,
  listIntegrations,
};
