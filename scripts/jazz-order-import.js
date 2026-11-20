#!/usr/bin/env node
/**
 * Post raw Jazz OMS order/import payloads without ACCS.
 *
 * Usage:
 *   node scripts/jazz-order-import.js --uat --dry-run --file scripts/jazz-order-samples/pharmacy-replen-3.json
 *   node scripts/jazz-order-import.js --uat --file scripts/jazz-order-samples/pharmacy-replen-3.json
 *   node scripts/jazz-order-import.js --prod --confirm-prod --file scripts/jazz-order-samples/pharmacy-replen-3-prod.json
 *
 * Env (from repo .env): JAZZ_TENANT_CODE, JAZZ_UAT_* or JAZZ_*_PROD, optional JAZZ_ORDER_SOURCE_CODE.
 */

const fs = require('fs');
const path = require('path');

function loadEnv(filePath) {
  if (!fs.existsSync(filePath)) {
    return {};
  }
  const vars = {};
  for (const line of fs.readFileSync(filePath, 'utf8').split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) {
      continue;
    }
    const idx = trimmed.indexOf('=');
    if (idx === -1) {
      continue;
    }
    let value = trimmed.slice(idx + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"'))
      || (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }
    vars[trimmed.slice(0, idx).trim()] = value;
  }
  return vars;
}

function loadLocalSettingsValues(filePath) {
  if (!fs.existsSync(filePath)) {
    return {};
  }
  try {
    const parsed = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    const values = parsed?.Values;
    return values && typeof values === 'object' ? values : {};
  } catch {
    return {};
  }
}

function parseArgs(argv) {
  const opts = {
    uat: true,
    dryRun: false,
    confirmProd: false,
    file: '',
    runSuffix: '',
    patchShipCode: false,
    patchHeader: false,
  };

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '--uat') {
      opts.uat = true;
    } else if (arg === '--prod') {
      opts.uat = false;
    } else if (arg === '--dry-run') {
      opts.dryRun = true;
    } else if (arg === '--confirm-prod') {
      opts.confirmProd = true;
    } else if (arg === '--file' && argv[i + 1]) {
      opts.file = argv[i + 1];
      i += 1;
    } else if (arg === '--run-suffix' && argv[i + 1]) {
      opts.runSuffix = argv[i + 1];
      i += 1;
    } else if (arg === '--patch-ship-code') {
      opts.patchShipCode = true;
    } else if (arg === '--patch-header') {
      opts.patchHeader = true;
    } else if (arg === '--help' || arg === '-h') {
      opts.help = true;
    }
  }

  return opts;
}

function printHelp() {
  console.log(`Usage: node scripts/jazz-order-import.js [--uat|--prod] [options]

Options:
  --uat              Target Jazz UAT (default)
  --prod               Target Jazz production (requires --confirm-prod to POST)
  --confirm-prod       Required safety flag for production POST
  --file <path>        JSON file: single order object, or { "orders": [ ... ] }
  --dry-run            Validate payload only; do not POST
  --run-suffix <text>  Append to each order_number (e.g. -R2) for repeat UAT runs
  --patch-ship-code   PATCH ship_code on existing Jazz orders (uses order_number from --file)
  --patch-header      PATCH order header (ship_date, po_number, …) from each order in --file
`);
}

function loadOrders(filePath) {
  const abs = path.isAbsolute(filePath) ? filePath : path.join(process.cwd(), filePath);
  if (!fs.existsSync(abs)) {
    throw new Error(`File not found: ${abs}`);
  }

  let data;
  try {
    data = JSON.parse(fs.readFileSync(abs, 'utf8'));
  } catch (error) {
    throw new Error(`Invalid JSON in ${abs}: ${error.message}`);
  }

  if (Array.isArray(data)) {
    return data;
  }
  if (data && typeof data === 'object' && Array.isArray(data.orders)) {
    return data.orders;
  }
  if (data && typeof data === 'object') {
    return [data];
  }

  throw new Error('Expected a JSON order object, an array of orders, or { "orders": [...] }.');
}

function applyRunSuffix(payload, suffix) {
  if (!suffix) {
    return payload;
  }
  const next = JSON.parse(JSON.stringify(payload));
  const base = String(next.order_number ?? '').trim();
  if (base) {
    next.order_number = `${base}${suffix}`;
    if (next.po_number && String(next.po_number).trim() === base) {
      next.po_number = next.order_number;
    }
  }
  return next;
}

function envDefaultSourceCode() {
  return (
    String(process.env.JAZZ_ORDER_SOURCE_CODE || '').trim()
    || String(process.env.JAZZ_TRANSFER_ORDER_SOURCE_CODE || '').trim()
    || 'WEB_2026'
  );
}

function envDefaultAllocationFacility() {
  return (
    String(process.env.JAZZ_ALLOCATION_FACILITY || '').trim()
    || String(process.env.JAZZ_CART_FACILITY_CODE || '').trim()
    || 'FBF09'
  );
}

function envDefaultShipCode() {
  return String(process.env.JAZZ_ORDER_SHIP_CODE || '').trim() || 'ECONOMY';
}

/** Replace REPLACE_ME_* from env / documented CART defaults before POST. */
function applyEnvDefaults(payload) {
  const next = JSON.parse(JSON.stringify(payload));
  const sourceCode = envDefaultSourceCode();
  const allocationFacility = envDefaultAllocationFacility();

  if (String(next.source_code || '').includes('REPLACE_ME')) {
    next.source_code = sourceCode;
  }

  if (next.attributes && typeof next.attributes === 'object') {
    const alloc = String(next.attributes.allocation_facility || '');
    if (alloc.includes('REPLACE_ME')) {
      next.attributes.allocation_facility = allocationFacility;
    }
  }

  const shipCode = envDefaultShipCode();
  const shipTos = Array.isArray(next.shipto) ? next.shipto : [];
  shipTos.forEach((shipTo) => {
    if (shipTo && typeof shipTo === 'object' && !String(shipTo.ship_code || '').trim()) {
      shipTo.ship_code = shipCode;
    }
  });

  return next;
}

function assertNoPlaceholders(payload) {
  const json = JSON.stringify(payload);
  if (json.includes('REPLACE_ME_')) {
    throw new Error('Payload still contains REPLACE_ME_* placeholders — edit the JSON (SKU, qty, source_code, allocation_facility) before sending.');
  }
}

const jazzOms = require('../functions/src/lib/jazz-oms');
const {
  validateJazzImportPayload,
  collectJazzHolds,
} = require('../functions/src/lib/jazz-order-validation');

const root = path.join(__dirname, '..');
const mergedEnv = {
  ...loadLocalSettingsValues(path.join(root, 'functions', 'local.settings.json')),
  ...loadEnv(path.join(root, '.env')),
};
for (const [key, value] of Object.entries(mergedEnv)) {
  if (process.env[key] === undefined) {
    process.env[key] = value;
  }
}

const opts = parseArgs(process.argv.slice(2));
if (opts.help || !opts.file) {
  printHelp();
  process.exit(opts.file ? 0 : 1);
}

if (!opts.uat && !opts.dryRun && !opts.confirmProd) {
  console.error('Production POST blocked. Re-run with --prod --confirm-prod after UAT validation.');
  process.exit(1);
}

const jazzOptions = opts.uat ? { uat: true } : { uat: false };
const configError = jazzOms.configError(jazzOptions);
if (configError) {
  console.error(configError);
  process.exit(1);
}

let orders;
try {
  orders = loadOrders(opts.file);
} catch (error) {
  console.error(error.message);
  process.exit(1);
}

console.log(
  JSON.stringify(
    {
      target: opts.uat ? 'jazz_uat' : 'jazz_production',
      jazzBaseUrl: jazzOms.baseUrl(jazzOptions),
      dryRun: opts.dryRun,
      orderCount: orders.length,
    },
    null,
    2,
  ),
);

async function run() {
  const results = [];

  const defaultsNote = {
    source_code: envDefaultSourceCode(),
    allocation_facility: envDefaultAllocationFacility(),
    ship_code: envDefaultShipCode(),
  };
  console.error(`Env defaults: ${JSON.stringify(defaultsNote)}`);

  if (opts.patchHeader) {
    for (let index = 0; index < orders.length; index += 1) {
      const prepared = applyEnvDefaults(orders[index]);
      const orderNumber = String(prepared.order_number ?? '').trim();
      if (!orderNumber) {
        results.push({ index: index + 1, ok: false, error: 'Missing order_number.' });
        continue;
      }
      const headerFields = {};
      if (prepared.ship_date) {
        headerFields.ship_date = String(prepared.ship_date).trim().slice(0, 10);
      }
      if (prepared.cancel_date) {
        headerFields.cancel_date = String(prepared.cancel_date).trim().slice(0, 10);
      }
      if (prepared.po_number) {
        headerFields.po_number = String(prepared.po_number).trim();
      }
      if (prepared.source_code) {
        headerFields.source_code = String(prepared.source_code).trim();
      }
      if (Object.keys(headerFields).length === 0) {
        results.push({ index: index + 1, order_number: orderNumber, ok: false, error: 'No header fields to patch (need ship_date, etc.).' });
        continue;
      }
      if (opts.dryRun) {
        results.push({ index: index + 1, order_number: orderNumber, ok: true, dryRun: true, action: 'patch_order_header', headerFields });
        continue;
      }
      const patchResult = await jazzOms.patchOrderHeader(orderNumber, headerFields, jazzOptions);
      results.push({
        index: index + 1,
        order_number: orderNumber,
        headerFields,
        ok: patchResult.ok,
        status: patchResult.status,
        error: patchResult.error,
        jazz: patchResult.data,
      });
    }
    console.log(JSON.stringify({ results }, null, 2));
    process.exit(results.some((row) => !row.ok) ? 1 : 0);
    return;
  }

  if (opts.patchShipCode) {
    for (let index = 0; index < orders.length; index += 1) {
      const prepared = applyEnvDefaults(orders[index]);
      const orderNumber = String(prepared.order_number ?? '').trim();
      const shipCode = String(prepared.shipto?.[0]?.ship_code || envDefaultShipCode()).trim();
      if (!orderNumber) {
        results.push({ index: index + 1, ok: false, error: 'Missing order_number.' });
        continue;
      }
      if (opts.dryRun) {
        results.push({ index: index + 1, order_number: orderNumber, ship_code: shipCode, ok: true, dryRun: true, action: 'patch_shipto_address' });
        continue;
      }
      const shipTo = prepared.shipto?.[0] || {};
      const addr = shipTo.address || {};
      const shiptoPatch = {
        first_name: shipTo.first_name || prepared.customer?.first_name,
        last_name: shipTo.last_name || prepared.customer?.last_name,
        company: shipTo.company || prepared.customer?.company,
        address1: addr.address1 || prepared.customer?.address1,
        address2: addr.address2 || prepared.customer?.address2,
        city: addr.city || prepared.customer?.city,
        state: addr.state || prepared.customer?.state,
        zipcode: addr.zipcode || prepared.customer?.zipcode,
        country: addr.country || prepared.customer?.country || 'US',
        email: shipTo.email || prepared.customer?.email,
        phone_number: shipTo.phone || prepared.customer?.phone,
      };
      Object.keys(shiptoPatch).forEach((key) => {
        if (shiptoPatch[key] === undefined || shiptoPatch[key] === null || shiptoPatch[key] === '') {
          delete shiptoPatch[key];
        }
      });
      const patchResult = await jazzOms.patchOrderShipCode(orderNumber, shipCode, {
        ...jazzOptions,
        shiptoPatch,
      });
      results.push({
        index: index + 1,
        order_number: orderNumber,
        ship_code: shipCode,
        ok: patchResult.ok,
        status: patchResult.status,
        error: patchResult.error,
        jazz: patchResult.data,
      });
    }
    console.log(JSON.stringify({ results }, null, 2));
    process.exit(results.some((row) => !row.ok) ? 1 : 0);
    return;
  }

  for (let index = 0; index < orders.length; index += 1) {
    const prepared = applyEnvDefaults(applyRunSuffix(orders[index], opts.runSuffix));
    try {
      assertNoPlaceholders(prepared);
    } catch (error) {
      results.push({ index: index + 1, ok: false, error: error.message });
      continue;
    }

    const validation = validateJazzImportPayload(prepared);
    if (!validation.ok) {
      results.push({
        index: index + 1,
        order_number: prepared.order_number ?? null,
        ok: false,
        error: 'Validation failed',
        validation_errors: validation.errors,
      });
      continue;
    }

    if (opts.dryRun) {
      results.push({
        index: index + 1,
        order_number: prepared.order_number,
        ok: true,
        dryRun: true,
        lineCount: (prepared.shipto || []).flatMap((s) => s.detail_set || []).length,
      });
      continue;
    }

    const jazzResult = await jazzOms.importOrder(prepared, jazzOptions);
    const holds = jazzResult.ok ? collectJazzHolds(jazzResult.data) : [];

    results.push({
      index: index + 1,
      order_number: prepared.order_number,
      ok: jazzResult.ok,
      status: jazzResult.status,
      error: jazzResult.error,
      holds,
      jazz: jazzResult.data,
    });
  }

  console.log(JSON.stringify({ results }, null, 2));

  const failed = results.some((row) => !row.ok);
  process.exit(failed ? 1 : 0);
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});