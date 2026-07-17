/**
 * Shared SMTP mailer for NutraAxis Azure Functions.
 * Uses the same Office 365 SMTP config as the PHP portal.
 */

const nodemailer = require('nodemailer');

/**
 * Returns a configured nodemailer transporter from environment variables.
 */
function createTransporter() {
    const host       = process.env.SMTP_HOST;
    const port       = parseInt(process.env.SMTP_PORT || '587', 10);
    const user       = process.env.SMTP_USER;
    const pass       = process.env.SMTP_PASS;
    const encryption = (process.env.SMTP_ENCRYPTION || 'tls').toLowerCase();

    if (!host || !user || !pass) {
        throw new Error('SMTP is not configured. Set SMTP_HOST, SMTP_USER, and SMTP_PASS.');
    }

    return nodemailer.createTransport({
        host,
        port,
        secure: encryption === 'ssl',
        auth: { user, pass },
        tls: { ciphers: 'SSLv3' },
    });
}

/**
 * Send a single email.
 *
 * @param {object} options
 * @param {string|string[]} options.to
 * @param {string|string[]} [options.cc]
 * @param {string} options.subject
 * @param {string} options.html
 * @param {string} [options.text]
 */
async function sendMail({ to, cc, subject, html, text }) {
    const transporter = createTransporter();
    const from        = `"${process.env.MAIL_FROM_NAME || 'NutraAxis Operations'}" <${process.env.SMTP_USER}>`;

    const toStr = Array.isArray(to) ? to.join(', ') : to;
    const ccStr = cc && cc.length ? (Array.isArray(cc) ? cc.join(', ') : cc) : undefined;

    const info = await transporter.sendMail({
        from,
        to:      toStr,
        ...(ccStr ? { cc: ccStr } : {}),
        subject,
        html,
        text: text || html.replace(/<[^>]+>/g, ''),
    });

    return info;
}

module.exports = { sendMail };
