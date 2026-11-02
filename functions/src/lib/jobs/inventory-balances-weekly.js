const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { buildXlsx } = require('../xlsx-simple');
const mail = require('../mail');

const ALERT_NAME = 'inventory-balances-weekly';
const LEDGER_PROFILE = 'production';
const TIMEZONE = 'America/Chicago';

function chicagoStamp(date = new Date()) {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: TIMEZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date);
}

function chicagoLabel(date = new Date()) {
  return new Intl.DateTimeFormat('en-US', {
    timeZone: TIMEZONE,
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZoneName: 'short',
  }).format(date);
}

function num(value) {
  if (value === null || value === undefined || value === '') {
    return null;
  }
  const n = Number(value);
  if (!Number.isFinite(n)) {
    return null;
  }
  if (Math.abs(n - Math.round(n)) < 0.0000001) {
    return Math.round(n);
  }
  return Math.round(n * 10000) / 10000;
}

async function loadRecipients(pool) {
  const result = await pool.request()
    .input('alertName', sql.NVarChar(100), ALERT_NAME)
    .query(`
      SELECT
        ISNULL(sub.AddressType, N'TO') AS AddressType,
        u.UserLogin,
        u.UserName
      FROM dbo.AlertMessage am
      INNER JOIN dbo.AlertSubscription sub ON sub.alertID = am.alertID
      INNER JOIN dbo.[User] u ON u.UserID = sub.UserID
      WHERE am.AlertName = @alertName
        AND am.AlertStatus = 1
    `);

  const to = [];
  const cc = [];
  for (const row of result.recordset) {
    const email = String(row.UserLogin || '').trim().toLowerCase();
    if (!email.includes('@')) {
      continue;
    }
    const name = String(row.UserName || '').trim() || email;
    const bucket = String(row.AddressType || 'TO').toUpperCase() === 'CC' ? cc : to;
    if (!bucket.some((entry) => entry.email === email)) {
      bucket.push({ email, name });
    }
  }

  return { to, cc };
}

async function loadBalanceRows(pool) {
  const result = await pool.request()
    .input('ledgerProfile', sql.NVarChar(32), LEDGER_PROFILE)
    .query(`
      SELECT
          b.SKUCode,
          ISNULL(s.ProductName, N'') AS ProductName,
          b.FacilityCode,
          f.FacilityName,
          b.QtyOK,
          b.QtyQuarantine,
          b.QtyOnHold,
          b.QtyDestroy,
          b.QtyReserved,
          b.QtyOnHand,
          b.QtyAvailable,
          q.QtyForQbo,
          s.COGS,
          CASE
              WHEN s.COGS IS NULL THEN NULL
              ELSE ROUND(s.COGS * b.QtyOnHand, 2)
          END AS ExtendedCOGS
      FROM dbo.InvCurrentBalance b
      INNER JOIN dbo.Facility f ON f.FacilityCode = b.FacilityCode
      LEFT JOIN dbo.SKUMaster s ON s.SKUCode = b.SKUCode
      OUTER APPLY (
          SELECT SUM(b2.QtyOK + b2.QtyQuarantine + b2.QtyOnHold) AS QtyForQbo
          FROM dbo.InvCurrentBalance b2
          WHERE b2.SKUCode = b.SKUCode
            AND b2.LedgerProfile = b.LedgerProfile
      ) q
      WHERE b.LedgerProfile = @ledgerProfile
      ORDER BY b.SKUCode ASC, b.FacilityCode ASC
    `);

  return result.recordset;
}

function buildWorkbook(rows) {
  const headers = [
    'SKU',
    'Product Name',
    'Facility',
    'Facility Name',
    'OK',
    'Quarantine',
    'On Hold',
    'Destroy',
    'Reserved',
    'On Hand',
    'Available',
    'QBO Qty',
    'COGS',
    'Extended COGS (On Hand)',
  ];

  const data = rows.map((row) => [
    String(row.SKUCode || ''),
    String(row.ProductName || ''),
    String(row.FacilityCode || ''),
    String(row.FacilityName || ''),
    num(row.QtyOK),
    num(row.QtyQuarantine),
    num(row.QtyOnHold),
    num(row.QtyDestroy),
    num(row.QtyReserved),
    num(row.QtyOnHand),
    num(row.QtyAvailable),
    num(row.QtyForQbo),
    num(row.COGS),
    num(row.ExtendedCOGS),
  ]);

  return buildXlsx(headers, data, 'Inventory Balances');
}

async function run() {
  const database = getProductionDatabase();
  const pool = await connectPool(database);

  try {
    const recipients = await loadRecipients(pool);
    const toList = recipients.to.map((r) => r.email);
    const ccList = recipients.cc.map((r) => r.email);

    if (toList.length === 0 && ccList.length === 0) {
      return {
        ok: false,
        error: `No subscribers for ${ALERT_NAME}.`,
        skipped_reason: 'no_subscribers',
        row_count: 0,
      };
    }

    if (!mail.smtpIsConfigured()) {
      return {
        ok: false,
        error: 'SMTP is not configured.',
        skipped_reason: 'smtp_not_configured',
        row_count: 0,
      };
    }

    const rows = await loadBalanceRows(pool);
    const filename = `inventory-balances-${chicagoStamp()}.xlsx`;
    const xlsx = buildWorkbook(rows);
    const subject = `Inventory Balances — ${chicagoStamp()}`;
    const body =
      `Attached is the NutraAxis Inventory Balances workbook (${rows.length} row` +
      `${rows.length === 1 ? '' : 's'}) for ledger profile ${LEDGER_PROFILE}.\n\n` +
      `Generated ${chicagoLabel()}.\n` +
      'COGS values come from SKU Master.\n';

    const send = await mail.sendMessageWithAttachments({
      to: toList,
      cc: ccList,
      subject,
      text: body,
      attachments: [
        {
          filename,
          content: xlsx,
          contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        },
      ],
    });

    if (!send.ok) {
      return {
        ok: false,
        error: send.error || 'Send failed.',
        skipped_reason: send.skipped_reason || null,
        row_count: rows.length,
        filename,
        recipients: [...toList, ...ccList],
      };
    }

    return {
      ok: true,
      row_count: rows.length,
      filename,
      recipients: [...toList, ...ccList],
      message_id: send.messageId || null,
    };
  } finally {
    await pool.close();
  }
}

module.exports = {
  run,
  ALERT_NAME,
};
