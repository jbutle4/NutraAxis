const processLog = require('./process-log');
const monthlySalesSummary = require('./jobs/monthly-sales-summary');
const forecastPlan = require('./jobs/forecast-plan');
const dailySalesSummary = require('./jobs/daily-sales-summary');
const jazzInventorySnapshot = require('./jobs/jazz-inventory-snapshot');
const accsSalesOrderSync = require('./jobs/accs-sales-order-sync');
const accsEmployeeCustomerCreate = require('./jobs/accs-employee-customer-create');
const qboCoaSync = require('./jobs/qbo-coa-sync');
const inventoryReceiptSync = require('./jobs/inventory-receipt-sync');
const inventorySalesSync = require('./jobs/inventory-sales-sync');

const REGISTRY = {
  'monthly-sales-summary': {
    code: 'monthly-sales-summary',
    name: 'Monthly Sales Summary',
  },
  'daily-sales-summary': {
    code: 'daily-sales-summary',
    name: 'Daily Sales Summary',
  },
  'forecast-plan': {
    code: 'forecast-plan',
    name: 'Inventory Forecast Plan',
  },
  'jazz-inventory-snapshot': {
    code: 'jazz-inventory-snapshot',
    name: 'Jazz Inventory Snapshot',
  },
  'accs-sales-order-sync': {
    code: 'accs-sales-order-sync',
    name: 'ACCS Sales Order Sync',
  },
  'accs-employee-customer-create': {
    code: 'accs-employee-customer-create',
    name: 'ACCS Stage Employee Customer Create',
  },
  'qbo-coa-sync': {
    code: 'qbo-coa-sync',
    name: 'QuickBooks Chart of Accounts Sync',
  },
  'inventory-receipt-sync': {
    code: 'inventory-receipt-sync',
    name: 'Inventory Receipt Sync (IMS + QBO)',
  },
  'inventory-sales-sync': {
    code: 'inventory-sales-sync',
    name: 'Inventory Sales Sync (IMS + QBO)',
  },
};

function buildResultMessage(code, result) {
  switch (code) {
    case 'daily-sales-summary':
      return `Summary date ${result.summary_date ?? '—'} — ${result.orders ?? 0} orders, ${result.inserted ?? 0} SKU rows inserted.`;
    case 'jazz-inventory-snapshot':
      return `Snapshot ${result.snapshot_at ?? '—'} — ${result.inserted ?? 0} rows inserted.`;
    case 'monthly-sales-summary':
      return `${result.inserted ?? 0} monthly SKU rows refreshed from daily sales.`;
    case 'forecast-plan':
      return `${result.skus ?? 0} SKUs — ${result.inserted ?? 0} forecast month rows written.`;
    case 'accs-sales-order-sync':
      if (result.skipped && result.message) {
        return result.message;
      }
      return `Fetched ${result.fetched ?? 0} orders — ${result.inserted ?? 0} inserted, ${result.updated ?? 0} updated, ${result.detail_updated ?? 0} detail rows refreshed, ${result.skipped ?? 0} skipped.`;
    case 'accs-employee-customer-create':
      return `${result.candidates ?? 0} employees — ${result.created ?? 0} created, ${result.group_updated ?? 0} groups corrected, ${result.existing ?? 0} already existed, ${result.failed ?? 0} failed.`;
    case 'qbo-coa-sync':
      return `Realm ${result.realm_id ?? '—'} — ${result.synced ?? 0} accounts synced, ${result.deactivated ?? 0} marked inactive.`;
    case 'inventory-receipt-sync':
      return `Processed ${result.processed ?? 0} receipts — ${result.posted ?? 0} posted, ${result.skipped ?? 0} skipped, ${result.failed ?? 0} failed.`;
    case 'inventory-sales-sync':
      return `Processed ${result.processed ?? 0} orders — ${result.posted ?? 0} posted, ${result.skipped ?? 0} skipped, ${result.failed ?? 0} failed.`;
    default:
      return 'Process completed.';
  }
}

async function invoke(code, params = {}) {
  switch (code) {
    case 'monthly-sales-summary':
      return monthlySalesSummary.run();
    case 'daily-sales-summary':
      return dailySalesSummary.run({ date: params.date ?? null });
    case 'forecast-plan':
      return forecastPlan.run();
    case 'jazz-inventory-snapshot':
      return jazzInventorySnapshot.run();
    case 'accs-sales-order-sync':
      return accsSalesOrderSync.run({ force: Boolean(params.force) });
    case 'accs-employee-customer-create':
      return accsEmployeeCustomerCreate.run({
        dryRun: Boolean(params.dry_run ?? params.dryRun),
        retryFailed: Boolean(params.retry_failed ?? params.retryFailed),
        includeExisting: Boolean(params.include_existing ?? params.includeExisting),
        fixGroupsOnly: Boolean(params.fix_groups_only ?? params.fixGroupsOnly),
      });
    case 'qbo-coa-sync':
      return qboCoaSync.run();
    case 'inventory-receipt-sync':
      return inventoryReceiptSync.run();
    case 'inventory-sales-sync':
      return inventorySalesSync.run();
    default:
      return {
        ok: false,
        error: `Unknown process code: ${code}`,
      };
  }
}

async function rerunFailedLog(logId, triggeredByUserId = null) {
  const log = await processLog.get(logId);
  if (!log) {
    return {
      ok: false,
      error: 'Process log entry not found.',
      log_id: null,
    };
  }

  const status = String(log.Status || '');
  if (status !== processLog.STATUS.FAILED && status !== processLog.STATUS.ABANDONED) {
    return {
      ok: false,
      error: 'Only failed or abandoned process runs can be rerun.',
      log_id: logId,
    };
  }

  const code = String(log.ProcessCode || '').trim();
  const params = processLog.decodeParams(log);

  return execute(code, params, processLog.TRIGGER.MANUAL, triggeredByUserId);
}

async function execute(code, params = {}, triggerType = processLog.TRIGGER.SCHEDULED, triggeredByUserId = null) {
  const entry = REGISTRY[code];
  if (!entry) {
    return {
      ok: false,
      error: `Unknown process code: ${code}`,
      log_id: null,
    };
  }

  const logId = await processLog.start(
    entry.code,
    entry.name,
    triggerType,
    triggeredByUserId,
    params
  );

  try {
    const result = await invoke(code, params);
    const ok = Boolean(result.ok);
    const error = String(result.error || '').trim();
    const message = ok
      ? buildResultMessage(code, result)
      : (error || 'Process failed.');

    await processLog.finish(
      logId,
      ok,
      ok ? message : null,
      ok ? null : message,
      result
    );

    return {
      ...result,
      log_id: logId,
      message,
    };
  } catch (error) {
    const message = error.message;
    await processLog.finish(logId, false, null, message);

    return {
      ok: false,
      error: message,
      log_id: logId,
    };
  }
}

module.exports = {
  execute,
  invoke,
  rerunFailedLog,
  buildResultMessage,
  REGISTRY,
};
