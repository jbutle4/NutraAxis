#!/usr/bin/env node
/**
 * Remove orphaned root-level PHP shims on production FTPS (and optionally locally).
 * Uses the same config as scripts/ftp-upload.js (.vscode/sftp.json).
 *
 * Usage:
 *   node scripts/delete-orphaned-root-files.js              # dry-run: list remote targets
 *   node scripts/delete-orphaned-root-files.js --confirm    # delete listed remote files
 *   node scripts/delete-orphaned-root-files.js --local      # remove local root shims if present
 *   node scripts/delete-orphaned-root-files.js --delete-cron-retired [--confirm]
 *   node scripts/delete-orphaned-root-files.js --delete-functions [--confirm]
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const CONFIG_PATH = path.join(ROOT, '.vscode', 'sftp.json');

/** Root wwwroot PHP files that duplicate folder routes (never delete protected names). */
const ORPHAN_ROOT_FILES = [
  'product-catalog.php',
  'legal-agreements.php',
  'operations-dashboard.php',
  'dash.php',
  'ops-dashboard.php',
  'planner.php',
  'test-dash.php',
  'health.php',
  'ping.php',
];

const PROTECTED_ROOT_FILES = new Set([
  'index.php',
  'Planner.php',
  'favicon.ico',
  '.user.ini',
  'web.config',
]);

/** Retired App Service cron jobs (migrated to Azure Functions). Keep test-mail.php. */
const RETIRED_CRON_FILES = [
  'daily-sales-summary.php',
  'jazz-inventory-snapshot.php',
  'monthly-sales-summary.php',
  'process-watcher.php',
  'weekly-chain.php',
  'weekly-demand.php',
];

function parseArgs(argv) {
  return {
    confirm: argv.includes('--confirm'),
    local: argv.includes('--local'),
    deleteCronRetired: argv.includes('--delete-cron-retired'),
    deleteFunctions: argv.includes('--delete-functions'),
  };
}

function loadConfig() {
  const cfg = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));
  return {
    host: cfg.host,
    port: cfg.port || 21,
    user: cfg.username,
    password: cfg.password,
    secure: cfg.secure === true ? true : cfg.secure || 'control',
    secureOptions: cfg.secureOptions || { rejectUnauthorized: false },
    remotePath: (cfg.remotePath || '/site/wwwroot').replace(/\/$/, ''),
    connTimeout: cfg.connectTimeout || 120000,
  };
}

function loadFtp() {
  const candidates = [
    'ftp',
    path.join(process.env.HOME || '', '.cursor/extensions/natizyskunk.sftp-1.16.3-universal/node_modules/ftp/lib/connection'),
  ];

  for (const mod of candidates) {
    try {
      return require(mod);
    } catch (_) {
      /* try next */
    }
  }

  throw new Error('FTP module not found. Run: npm install ftp');
}

function connect(cfg) {
  const FTP = loadFtp();
  const client = new FTP();

  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      client.destroy();
      reject(new Error('Timeout while connecting to server'));
    }, cfg.connTimeout);

    client.on('ready', () => {
      clearTimeout(timer);
      resolve(client);
    });

    client.on('error', (err) => {
      clearTimeout(timer);
      reject(err);
    });

    client.connect({
      host: cfg.host,
      port: cfg.port,
      user: cfg.user,
      password: cfg.password,
      secure: cfg.secure,
      secureOptions: cfg.secureOptions,
      connTimeout: cfg.connTimeout,
    });
  });
}

function listDir(client, remoteDir) {
  return new Promise((resolve, reject) => {
    client.list(remoteDir, (err, list) => {
      if (err) reject(err);
      else resolve(list || []);
    });
  });
}

function remoteExists(client, remotePath) {
  return new Promise((resolve) => {
    client.size(remotePath, (err) => {
      resolve(!err);
    });
  });
}

function deleteRemote(client, remotePath) {
  return new Promise((resolve, reject) => {
    client.delete(remotePath, (err) => {
      if (err) reject(err);
      else resolve();
    });
  });
}

function rmdirRemote(client, remotePath) {
  return new Promise((resolve, reject) => {
    client.rmdir(remotePath, (err) => {
      if (err) reject(err);
      else resolve();
    });
  });
}

async function removeDirRecursive(client, remoteDir) {
  const entries = await listDir(client, remoteDir);
  for (const entry of entries) {
    const full = `${remoteDir}/${entry.name}`;
    if (entry.type === 'd') {
      await removeDirRecursive(client, full);
      await rmdirRemote(client, full);
    } else {
      await deleteRemote(client, full);
    }
  }
}

async function collectExisting(client, cfg, relativePaths) {
  const found = [];
  const missing = [];

  for (const rel of relativePaths) {
    const base = path.posix.basename(rel);
    if (PROTECTED_ROOT_FILES.has(base)) continue;

    const remotePath = `${cfg.remotePath}/${rel.replace(/^\//, '')}`;
    // eslint-disable-next-line no-await-in-loop
    const exists = await remoteExists(client, remotePath);
    if (exists) found.push({ rel, remotePath });
    else missing.push(rel);
  }

  return { found, missing };
}

function deleteLocalOrphans() {
  const removed = [];
  const absent = [];
  const rootEntries = new Set(fs.readdirSync(ROOT));

  for (const name of ORPHAN_ROOT_FILES) {
    if (PROTECTED_ROOT_FILES.has(name)) continue;
    if (!rootEntries.has(name)) {
      absent.push(name);
      continue;
    }
    fs.unlinkSync(path.join(ROOT, name));
    removed.push(name);
  }

  return { removed, absent };
}

function printSummary(title, lines) {
  console.log(`\n=== ${title} ===`);
  for (const line of lines) console.log(line);
}

async function runRemote(cfg, args) {
  const remoteTargets = [];

  for (const name of ORPHAN_ROOT_FILES) {
    if (!PROTECTED_ROOT_FILES.has(name)) remoteTargets.push(name);
  }

  if (args.deleteCronRetired) {
    for (const name of RETIRED_CRON_FILES) {
      remoteTargets.push(`cron/${name}`);
    }
  }

  console.log(`Connecting to ${cfg.host}:${cfg.port}...`);
  const client = await connect(cfg);
  console.log(`Connected. Remote root: ${cfg.remotePath}`);

  try {
    const { found, missing } = await collectExisting(client, cfg, remoteTargets);

    printSummary('Remote orphan files (exist on server)', found.length ? found.map((f) => `  ${f.remotePath}`) : ['  (none)']);

    if (missing.length && !args.deleteFunctions) {
      printSummary('Known targets not on server (skipped)', missing.map((m) => `  ${m}`));
    }

    let functionsEntries = [];
    if (args.deleteFunctions) {
      const functionsDir = `${cfg.remotePath}/functions`;
      try {
        functionsEntries = await listDir(client, functionsDir);
        printSummary(
          'Remote /functions/ contents',
          functionsEntries.length
            ? functionsEntries.map((e) => `  ${functionsDir}/${e.name}${e.type === 'd' ? '/' : ''}`)
            : ['  (directory missing or empty)']
        );
      } catch (err) {
        printSummary('Remote /functions/', [`  Not found or not listable: ${err.message}`]);
      }
    }

    if (!args.confirm) {
      console.log('\nDry run only. Re-run with --confirm to delete the items listed above.');
      if (args.deleteFunctions && functionsEntries.length) {
        console.log('With --delete-functions, --confirm will remove the entire /functions/ tree.');
      }
      return { deleted: [], errors: [] };
    }

    const deleted = [];
    const errors = [];

    for (const item of found) {
      try {
        await deleteRemote(client, item.remotePath);
        deleted.push(item.remotePath);
        console.log(`Deleted ${item.remotePath}`);
      } catch (err) {
        errors.push({ path: item.remotePath, message: err.message });
        console.error(`Failed ${item.remotePath}: ${err.message}`);
      }
    }

    if (args.deleteFunctions && functionsEntries.length) {
      const functionsDir = `${cfg.remotePath}/functions`;
      try {
        await removeDirRecursive(client, functionsDir);
        deleted.push(`${functionsDir}/ (tree)`);
        console.log(`Deleted ${functionsDir}/ (recursive)`);
      } catch (err) {
        errors.push({ path: functionsDir, message: err.message });
        console.error(`Failed ${functionsDir}: ${err.message}`);
      }
    }

    return { deleted, errors };
  } finally {
    client.end();
  }
}

async function main() {
  const args = parseArgs(process.argv.slice(2));

  if (args.local) {
    const { removed, absent } = deleteLocalOrphans();
    printSummary(
      'Local cleanup',
      removed.length
        ? removed.map((f) => `  Removed ${f}`)
        : ['  No orphan root PHP files present locally.']
    );
    if (absent.length) {
      console.log(`  Already absent: ${absent.join(', ')}`);
    }
  }

  const cfg = loadConfig();
  const { deleted, errors } = await runRemote(cfg, args);

  printSummary('Summary', [
    `  Mode: ${args.confirm ? 'DELETE' : 'dry-run'}`,
    `  Remote deleted: ${deleted.length}`,
    `  Remote errors: ${errors.length}`,
    args.deleteCronRetired ? '  Cron retired cleanup: enabled' : '',
    args.deleteFunctions ? '  Functions tree cleanup: enabled' : '',
  ].filter(Boolean));

  if (errors.length) process.exit(1);
}

main().catch((err) => {
  console.error('Failed:', err.message);
  process.exit(1);
});
