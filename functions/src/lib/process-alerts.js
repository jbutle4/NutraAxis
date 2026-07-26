const mail = require('./mail');

const ZENDESK_ALERT_EMAIL = 'alerts@nutraaxislabs.zendesk.com';

function alertEmail() {
  const configured = String(process.env.PROCESS_ALERT_EMAIL || ZENDESK_ALERT_EMAIL).trim();
  return configured !== '' ? configured : ZENDESK_ALERT_EMAIL;
}

function siteUrl() {
  const url = String(process.env.SITE_URL || 'https://nutraaxisweb.azurewebsites.net').trim();
  return url.replace(/\/+$/, '');
}

async function onAbandoned(processCode, processName, errorMessage, context = {}) {
  const processLogUrl = `${siteUrl()}/process-log/`;
  const subject = `NutraAxis process abandoned: ${processName}`;

  const lines = [
    'A NutraAxis Operations background process exhausted automatic retries and was marked abandoned.',
    '',
    `Process: ${processName} (${processCode})`,
    `Error: ${errorMessage}`,
    `Time (UTC): ${new Date().toISOString().slice(0, 19).replace('T', ' ')}`,
    `Process log: ${processLogUrl}`,
    'Action: Review Process Log and rerun manually if needed.',
  ];

  for (const [key, value] of Object.entries(context)) {
    if (value === null || typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
      const label = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
      lines.push(`${label}: ${value}`);
    }
  }

  const body = lines.join('\n');
  const result = await mail.sendMessage(alertEmail(), subject, body);

  if (!result.ok) {
    console.error(
      `process_alert_on_abandoned email failed for ${processCode}: ${errorMessage} (${result.skipped_reason || result.error || 'unknown'})`
    );
  }

  return result;
}

module.exports = {
  alertEmail,
  onAbandoned,
};
