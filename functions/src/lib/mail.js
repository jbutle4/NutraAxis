const nodemailer = require('nodemailer');

function smtpIsConfigured() {
  const host = String(process.env.SMTP_HOST || '').trim();
  const user = String(process.env.SMTP_USER || '').trim();
  const pass = String(process.env.SMTP_PASS || '');

  return host !== '' && user !== '' && pass !== '';
}

function fromAddress() {
  const smtpUser = String(process.env.SMTP_USER || '').trim();
  if (smtpIsConfigured() && smtpUser !== '' && smtpUser.includes('@')) {
    return smtpUser;
  }

  const from = String(process.env.MAIL_FROM || '').trim();
  if (from !== '' && from.includes('@')) {
    return from;
  }

  if (smtpUser !== '' && smtpUser.includes('@')) {
    return smtpUser;
  }

  return 'noreply@nutraaxis.com';
}

function fromName() {
  const name = String(process.env.MAIL_FROM_NAME || '').trim();
  return name !== '' ? name : 'NutraAxis Operations';
}

function replyToAddress() {
  const replyTo = String(process.env.MAIL_REPLY_TO || '').trim();
  if (replyTo !== '' && replyTo.includes('@')) {
    return replyTo;
  }

  return 'nutrateam@nfcllc.com';
}

function createTransport() {
  const host = String(process.env.SMTP_HOST || '').trim();
  const port = Number(process.env.SMTP_PORT || 587);
  const user = String(process.env.SMTP_USER || '').trim();
  const pass = String(process.env.SMTP_PASS || '');
  const encryption = String(process.env.SMTP_ENCRYPTION || 'tls').trim().toLowerCase();

  return nodemailer.createTransport({
    host,
    port,
    secure: encryption === 'ssl',
    auth: { user, pass },
    requireTLS: encryption === 'tls',
  });
}

async function sendMessage(to, subject, body) {
  const recipient = String(to || '').trim();
  if (recipient === '' || !recipient.includes('@')) {
    return { ok: false, error: 'Invalid recipient email address.' };
  }

  if (!smtpIsConfigured()) {
    return { ok: false, error: 'SMTP is not configured.', skipped_reason: 'smtp_not_configured' };
  }

  try {
    const transport = createTransport();
    const info = await transport.sendMail({
      from: `"${fromName()}" <${fromAddress()}>`,
      to: recipient,
      replyTo: replyToAddress(),
      subject,
      text: body,
    });

    return {
      ok: true,
      messageId: info.messageId || null,
    };
  } catch (error) {
    return {
      ok: false,
      error: error.message || 'SMTP send failed.',
    };
  }
}

function normalizeAddressList(value) {
  if (Array.isArray(value)) {
    return value
      .map((item) => String(item || '').trim().toLowerCase())
      .filter((email) => email.includes('@'));
  }
  const single = String(value || '').trim().toLowerCase();
  return single.includes('@') ? [single] : [];
}

async function sendMessageWithAttachments({ to, cc = [], subject, text, html = null, attachments = [] }) {
  const toList = normalizeAddressList(to);
  const ccList = normalizeAddressList(cc).filter((email) => !toList.includes(email));

  if (toList.length === 0 && ccList.length === 0) {
    return { ok: false, error: 'No recipients specified.' };
  }

  if (!smtpIsConfigured()) {
    return { ok: false, error: 'SMTP is not configured.', skipped_reason: 'smtp_not_configured' };
  }

  try {
    const transport = createTransport();
    const info = await transport.sendMail({
      from: `"${fromName()}" <${fromAddress()}>`,
      to: toList.join(', '),
      cc: ccList.length > 0 ? ccList.join(', ') : undefined,
      replyTo: replyToAddress(),
      subject,
      text,
      html: html || undefined,
      attachments: (attachments || []).map((attachment) => ({
        filename: attachment.filename,
        content: attachment.content,
        contentType: attachment.contentType || attachment.content_type || 'application/octet-stream',
      })),
    });

    return {
      ok: true,
      messageId: info.messageId || null,
    };
  } catch (error) {
    return {
      ok: false,
      error: error.message || 'SMTP send failed.',
    };
  }
}

module.exports = {
  smtpIsConfigured,
  sendMessage,
  sendMessageWithAttachments,
};
