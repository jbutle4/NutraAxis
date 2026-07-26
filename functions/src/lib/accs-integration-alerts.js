const mail = require('./mail');
const { getIntegration } = require('./accs-integration-registry');

const DEFAULT_ACCS_ALERTS_EMAIL = 'accs_alerts@nutraaxislabs.zendesk.com';

function accsAlertsEmail() {
  const configured = String(process.env.ACCS_ALERTS_ZENDESK_EMAIL || DEFAULT_ACCS_ALERTS_EMAIL).trim();
  return configured !== '' ? configured : DEFAULT_ACCS_ALERTS_EMAIL;
}

function siteUrl() {
  return String(process.env.SITE_URL || 'https://nutraaxisweb.azurewebsites.net').replace(/\/+$/, '');
}

function accsAdminUrl(environment = 'stage') {
  if (String(environment).toLowerCase() === 'production') {
    return 'https://na1.admin.commerce.adobe.com/VLuKe3eeTwf1D5oxmLBfcr/sales/order';
  }

  return 'https://na1-sandbox.admin.commerce.adobe.com/UAEyTrirS4qBMAWYZa4uic/sales/order';
}

function formatIssueList(items) {
  return items.map((item) => `- ${item}`).join('\n');
}

function buildAccsIntegrationAlertBody({
  processCode,
  integration,
  incrementId,
  orderEntityId = null,
  externalRef = null,
  sourceEnvironment = 'stage',
  issueType,
  errors = [],
  holds = [],
  action,
  processLogId = null,
  delivered = false,
  deliveryCount = null,
}) {
  const adminOrderId = orderEntityId || incrementId;
  const orderUrl = `${accsAdminUrl(sourceEnvironment)}/view/order_id/${adminOrderId}/`;
  const processLogUrl = `${siteUrl()}/process-log/?process_code=${encodeURIComponent(processCode)}`;

  const lines = [
    `${integration} integration ${delivered ? 'needs attention' : 'hard failure — payload not delivered'}.`,
    '',
    `ACCS order: ${incrementId}`,
    externalRef ? `Destination reference: ${externalRef}` : 'Destination reference: not created',
    `Environment: ${sourceEnvironment}`,
    `Issue: ${issueType}`,
    delivered ? 'Delivery status: payload reached destination with errors/holds.' : 'Delivery status: payload was not delivered.',
    '',
    action,
    '',
    `Update the order in ACCS: ${orderUrl}`,
    `Process log: ${processLogUrl}`,
    `Operations portal: ${siteUrl()}/operations-dashboard/`,
  ];

  if (processLogId) {
    lines.push(`Process log entry: ${processLogId}`);
  }

  if (deliveryCount !== null) {
    lines.push(`Service Bus delivery attempt: ${deliveryCount}`);
  }

  if (errors.length > 0) {
    lines.push('', 'Problems:', formatIssueList(errors));
  }

  if (holds.length > 0) {
    lines.push('', 'Destination holds:');
    for (const hold of holds) {
      if (typeof hold === 'string') {
        lines.push(`- ${hold}`);
        continue;
      }

      const detail = hold.explanation ? ` — ${hold.explanation}` : '';
      lines.push(`- ${hold.reason}${detail}`);
    }
  }

  return lines.join('\n');
}

async function sendAccsIntegrationAlert(options) {
  const subjectPrefix = options.delivered ? 'needs attention' : 'hard failure';
  const subject = options.subject
    || (options.externalRef
      ? `[ACCS / ${options.integration}] Order #${options.incrementId} ${subjectPrefix} (${options.externalRef})`
      : `[ACCS / ${options.integration}] Order #${options.incrementId} ${subjectPrefix}`);

  const body = options.body || buildAccsIntegrationAlertBody(options);
  const result = await mail.sendMessage(accsAlertsEmail(), subject, body);

  if (!result.ok) {
    return result;
  }

  return {
    ok: true,
    messageId: result.messageId ?? null,
    ticketEmail: accsAlertsEmail(),
  };
}

module.exports = {
  accsAlertsEmail,
  buildAccsIntegrationAlertBody,
  sendAccsIntegrationAlert,
};
