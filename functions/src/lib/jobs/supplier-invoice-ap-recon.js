const { sql, connectPool, getProductionDatabase } = require('../db-config');
const qboClient = require('../qbo-client');
const qboConfig = require('../qbo-config');
const qboConnection = require('../qbo-connection');

const PAGE_SIZE = 100;
const PO_STATUS_APPROVED = 'Approved';
const PO_STATUS_ACCOUNTING = 'Submitted to Accounting for Payment';
const PO_STATUS_PAID = 'Paid';

function connectionStore() {
  return qboConfig.environment() === 'production'
    ? qboConnection.production
    : qboConnection.staging;
}

function ledgerProfile() {
  return qboConfig.environment() === 'production' ? 'production' : 'uat';
}

function billIsPaid(bill) {
  if (bill?.Balance == null) {
    return false;
  }
  const balance = Number(bill.Balance);
  const total = Number(bill.TotalAmt || 0);
  return total > 0 && balance <= 0.01;
}

function syncStatusForBill(invoice, bill) {
  const current = String(invoice?.SyncStatus || 'Posted');
  if (current === 'Voided' || current === 'Rejected') {
    return current;
  }
  if (!billIsPaid(bill)) {
    return 'Posted';
  }
  return String(invoice?.AsnStatus || '') === 'Matched' ? 'Closed' : 'Paid';
}

async function listAllBills(store) {
  const rows = [];
  let start = 1;

  while (true) {
    const result = await qboClient.query(
      `SELECT Id, DocNumber, Balance, TotalAmt, SyncToken FROM Bill STARTPOSITION ${start} MAXRESULTS ${PAGE_SIZE}`,
      PAGE_SIZE,
      store
    );
    if (!result.ok) {
      return result;
    }
    const batch = qboClient.extractQueryRows(result.data, ['Bill']);
    if (batch.length === 0) {
      break;
    }
    rows.push(...batch);
    if (batch.length < PAGE_SIZE) {
      break;
    }
    start += PAGE_SIZE;
  }

  return { ok: true, error: null, rows };
}

async function tableHasColumn(pool, tableName, columnName) {
  const result = await pool.request()
    .input('tableName', sql.NVarChar(128), tableName)
    .input('columnName', sql.NVarChar(128), columnName)
    .query(`
      SELECT 1 AS Present
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = N'dbo'
        AND TABLE_NAME = @tableName
        AND COLUMN_NAME = @columnName
    `);
  return (result.recordset || []).length > 0;
}

async function matchAsn(pool, invoice, hasAsnColumns) {
  if (!hasAsnColumns) {
    return invoice;
  }
  const poId = Number(invoice.POID || 0);
  if (!poId) {
    return invoice;
  }

  const receipts = await pool.request()
    .input('poId', sql.Int, poId)
    .input('ledger', sql.NVarChar(20), ledgerProfile())
    .query(`
      SELECT PORID, JazzASN, PORStatus
      FROM dbo.POReceipt
      WHERE POID = @poId
        AND LedgerProfile = @ledger
        AND PORStatus <> N'Cancelled'
      ORDER BY
        CASE WHEN NULLIF(LTRIM(RTRIM(JazzASN)), N'') IS NOT NULL THEN 0 ELSE 1 END,
        PORID DESC
    `);

  const chosen = receipts.recordset[0];
  if (!chosen) {
    return invoice;
  }

  const jazz = String(chosen.JazzASN || '').trim();
  const asnStatus = jazz !== '' || ['Transmitted', 'Complete'].includes(String(chosen.PORStatus || ''))
    ? 'Matched'
    : 'DraftStarted';

  await pool.request()
    .input('asnStatus', sql.NVarChar(20), asnStatus)
    .input('porId', sql.Int, Number(chosen.PORID))
    .input('jazz', sql.NVarChar(50), jazz || null)
    .input('id', sql.Int, Number(invoice.SupplierInvoiceID))
    .query(`
      UPDATE dbo.SupplierInvoice
      SET AsnStatus = @asnStatus,
          PORID = @porId,
          JazzASN = @jazz,
          ModifiedDate = SYSUTCDATETIME()
      WHERE SupplierInvoiceID = @id
    `);

  return {
    ...invoice,
    AsnStatus: asnStatus,
    PORID: Number(chosen.PORID),
    JazzASN: jazz || null,
  };
}

async function syncPoFromInvoices(pool, poId) {
  const orderResult = await pool.request()
    .input('poId', sql.Int, poId)
    .query('SELECT POID, POStatus FROM dbo.PurchaseOrder WHERE POID = @poId');
  const order = orderResult.recordset[0];
  if (!order) {
    return null;
  }

  const current = String(order.POStatus || '');
  if (![PO_STATUS_APPROVED, PO_STATUS_ACCOUNTING, PO_STATUS_PAID].includes(current)) {
    return null;
  }

  const invoices = await pool.request()
    .input('poId', sql.Int, poId)
    .query(`
      SELECT SyncStatus
      FROM dbo.SupplierInvoice
      WHERE POID = @poId
        AND SyncStatus NOT IN (N'Voided', N'Rejected')
    `);
  const rows = invoices.recordset;
  if (rows.length === 0) {
    return null;
  }

  let allSettled = true;
  let hasPostedBill = false;
  for (const row of rows) {
    const status = String(row.SyncStatus || '');
    if (['Posted', 'Paid', 'Closed'].includes(status)) {
      hasPostedBill = true;
    }
    if (!['Paid', 'Closed'].includes(status)) {
      allSettled = false;
    }
  }

  let target = null;
  if (allSettled) {
    target = PO_STATUS_PAID;
  } else if (hasPostedBill && current === PO_STATUS_APPROVED) {
    target = PO_STATUS_ACCOUNTING;
  }

  if (!target || target === current) {
    return null;
  }

  await pool.request()
    .input('status', sql.NVarChar(50), target)
    .input('poId', sql.Int, poId)
    .query(`
      UPDATE dbo.PurchaseOrder
      SET POStatus = @status,
          ModifiedDate = SYSUTCDATETIME()
      WHERE POID = @poId
    `);

  return target;
}

async function run() {
  const store = connectionStore();
  const profile = ledgerProfile();
  const connection = await store.getConnection();
  if (!connection) {
    return {
      ok: false,
      error: `QuickBooks ${qboConfig.environment()} is not connected.`,
      refreshed: 0,
      paid: 0,
      closed: 0,
      matched: 0,
      po_advanced: 0,
    };
  }

  const billsResult = await listAllBills(store);
  if (!billsResult.ok) {
    return {
      ok: false,
      error: billsResult.error,
      refreshed: 0,
      paid: 0,
      closed: 0,
      matched: 0,
      po_advanced: 0,
    };
  }

  const billsById = new Map();
  const billsByDoc = new Map();
  for (const bill of billsResult.rows || []) {
    const id = String(bill?.Id || '').trim();
    if (id) {
      billsById.set(id, bill);
    }
    const doc = String(bill?.DocNumber || '').trim().toLowerCase();
    if (doc) {
      billsByDoc.set(doc, bill);
    }
  }

  const pool = await connectPool(getProductionDatabase() || 'nutraaxis');
  try {
    const hasLedger = await tableHasColumn(pool, 'SupplierInvoice', 'LedgerProfile');
    const hasAsnColumns = await tableHasColumn(pool, 'SupplierInvoice', 'AsnStatus');

    let invoiceSql = `
      SELECT SupplierInvoiceID, DocNumber, QBO_BillId, SyncStatus, POID, Balance
      ${hasAsnColumns ? ', AsnStatus, PORID, JazzASN' : ''}
      FROM dbo.SupplierInvoice
    `;
    const request = pool.request();
    if (hasLedger) {
      invoiceSql += ' WHERE LedgerProfile = @ledger';
      request.input('ledger', sql.NVarChar(20), profile);
    }
    invoiceSql += ' ORDER BY SupplierInvoiceID';
    const invoices = (await request.query(invoiceSql)).recordset;

    let refreshed = 0;
    let paid = 0;
    let closed = 0;
    let matched = 0;
    let poAdvanced = 0;
    const poIds = new Set();

    for (const raw of invoices) {
      let invoice = raw;
      const billId = String(invoice.QBO_BillId || '').trim();
      const doc = String(invoice.DocNumber || '').trim().toLowerCase();
      const bill = (billId && billsById.get(billId)) || (doc && billsByDoc.get(doc)) || null;

      if (hasAsnColumns) {
        invoice = await matchAsn(pool, invoice, true);
        if (String(invoice.AsnStatus || '') === 'Matched') {
          matched += 1;
        }
      }

      if (bill) {
        const status = syncStatusForBill(invoice, bill);
        await pool.request()
          .input('billId', sql.NVarChar(32), String(bill.Id || ''))
          .input('syncToken', sql.NVarChar(32), bill.SyncToken != null ? String(bill.SyncToken) : null)
          .input('realmId', sql.NVarChar(32), String(connection.RealmID || ''))
          .input('balance', sql.Decimal(18, 2), bill.Balance != null ? Number(bill.Balance) : null)
          .input('syncStatus', sql.NVarChar(30), status)
          .input('id', sql.Int, Number(invoice.SupplierInvoiceID))
          .query(`
            UPDATE dbo.SupplierInvoice
            SET QBO_BillId = @billId,
                QBO_SyncToken = @syncToken,
                QBO_RealmId = @realmId,
                Balance = @balance,
                SyncStatus = @syncStatus,
                LastSyncError = NULL,
                LastSyncAt = SYSUTCDATETIME(),
                ModifiedDate = SYSUTCDATETIME()
            WHERE SupplierInvoiceID = @id
          `);
        invoice.SyncStatus = status;
        refreshed += 1;
        if (status === 'Paid') {
          paid += 1;
        }
        if (status === 'Closed') {
          closed += 1;
        }
      }

      if (invoice.POID) {
        poIds.add(Number(invoice.POID));
      }
    }

    for (const poId of poIds) {
      const advanced = await syncPoFromInvoices(pool, poId);
      if (advanced) {
        poAdvanced += 1;
      }
    }

    return {
      ok: true,
      error: null,
      environment: qboConfig.environment(),
      ledger_profile: profile,
      invoices: invoices.length,
      refreshed,
      paid,
      closed,
      matched,
      po_advanced: poAdvanced,
    };
  } finally {
    await pool.close();
  }
}

module.exports = { run };
