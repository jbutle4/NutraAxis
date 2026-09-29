const nodemailer = require('nodemailer');

/** Same Office 365 SMTP settings as the portal (SMTP_HOST / SMTP_PORT / SMTP_USER / SMTP_PASS / SMTP_ENCRYPTION). */
function smtpIsConfigured() {
  return Boolean(String(process.env.SMTP_HOST || '').trim() && String(process.env.SMTP_USER || '').trim() && process.env.SMTP_PASS);
}

function fromAddress() {
  const user = String(process.env.SMTP_USER || '').trim();
  const from = String(process.env.MAIL_FROM || '').trim();
  if (user.includes('@')) return user;
  if (from.includes('@')) return from;
  return 'noreply@nutraaxis.com';
}

/** One message to several recipients. */
async function sendMail(recipients, subject, text) {
  const to = [...new Set(recipients.map((r) => String(r || '').trim()).filter((r) => r.includes('@')))];
  if (to.length === 0) return { ok: false, error: 'No alert recipients.' };
  if (!smtpIsConfigured()) return { ok: false, error: 'SMTP is not configured on the marketing Function App.' };
  const encryption = String(process.env.SMTP_ENCRYPTION || 'tls').trim().toLowerCase();
  try {
    const transport = nodemailer.createTransport({
      host: String(process.env.SMTP_HOST).trim(),
      port: Number(process.env.SMTP_PORT || 587),
      secure: encryption === 'ssl',
      auth: { user: String(process.env.SMTP_USER).trim(), pass: process.env.SMTP_PASS },
      requireTLS: encryption === 'tls',
    });
    await transport.sendMail({
      from: `"${String(process.env.MAIL_FROM_NAME || '').trim() || 'NutraAxis Operations'}" <${fromAddress()}>`,
      to: to.join(', '),
      replyTo: String(process.env.MAIL_REPLY_TO || '').trim() || undefined,
      subject,
      text,
    });
    return { ok: true, sent: to.length };
  } catch (error) {
    return { ok: false, error: error.message || 'SMTP send failed.' };
  }
}

module.exports = { smtpIsConfigured, sendMail };
