#!/usr/bin/env node
/**
 * Upload production files to Azure App Service via FTPS.
 * Usage: node scripts/ftp-upload.js
 *
 * SAFETY: Full-tree sync can wipe newer hub/nav wiring if run from a stale
 * branch. By default this script only runs on an up-to-date local `main`.
 * Override (rare): ALLOW_FULL_FTP_UPLOAD=1 npm run upload
 *
 * Prefer selective deploys: node scripts/ftp-upload-files.js <paths…>
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const CONFIG_PATH = path.join(ROOT, '.vscode', 'sftp.json');

const IGNORE_DIRS = new Set([
  '.git',
  '.vscode',
  'node_modules',
  'scripts',
  'sql',
  'docs',
  'functions', // Azure Function App — deployed separately, not to PHP wwwroot
  'Archive Sites',
  'nutraaxis_test',
  '.tmp',
]);

const IGNORE_FILES = new Set([
  '.env',
  '.DS_Store',
  '.gitignore',
  'package.json',
  'package-lock.json',
]);

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

function mkdirp(client, remoteDir) {
  const parts = remoteDir.split('/').filter(Boolean);
  let current = '';

  return parts.reduce(
    (chain, part) => {
      current += '/' + part;
      const dir = current;
      return chain.then(
        () =>
          new Promise((resolve, reject) => {
            client.mkdir(dir, true, (err) => {
              if (err && err.code !== 550) reject(err);
              else resolve();
            });
          })
      );
    },
    Promise.resolve()
  );
}

function putFile(client, localPath, remotePath) {
  return new Promise((resolve, reject) => {
    client.put(localPath, remotePath, (err) => {
      if (err) reject(err);
      else resolve();
    });
  });
}

function collectFiles(dir, baseDir = dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    const relPath = path.relative(baseDir, fullPath).split(path.sep).join('/');

    if (entry.isDirectory()) {
      if (IGNORE_DIRS.has(entry.name)) continue;
      files.push(...collectFiles(fullPath, baseDir));
      continue;
    }

    if (IGNORE_FILES.has(entry.name)) continue;
    files.push(relPath);
  }

  return files.sort();
}

function git(args) {
  return execFileSync('git', args, {
    cwd: ROOT,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

/**
 * Block full-tree production sync from feature / detached / behind-main trees.
 * Those uploads previously wiped Supply Chain hub + UAT/Test cards on live.
 */
function assertSafeFullUpload() {
  if (process.env.ALLOW_FULL_FTP_UPLOAD === '1') {
    console.warn('ALLOW_FULL_FTP_UPLOAD=1 set — skipping branch safety checks.');
    return;
  }

  let branch = '';
  let head = '';
  try {
    branch = git(['rev-parse', '--abbrev-ref', 'HEAD']);
    head = git(['rev-parse', '--short', 'HEAD']);
  } catch (err) {
    throw new Error(
      'Full FTP upload requires a git checkout. Use selective upload instead:\n' +
        '  node scripts/ftp-upload-files.js <paths…>\n' +
        'Or set ALLOW_FULL_FTP_UPLOAD=1 only if you intend a full sync.'
    );
  }

  if (branch === 'HEAD' || branch !== 'main') {
    throw new Error(
      `Refusing full FTP upload from "${branch}" (${head}).\n` +
        'Full sync must run from an up-to-date local `main` so hub/nav files are not overwritten by a stale tree.\n' +
        'Deploy only changed paths:\n' +
        '  node scripts/ftp-upload-files.js <paths…>\n' +
        'Or checkout main, pull, then retry. Override (rare): ALLOW_FULL_FTP_UPLOAD=1'
    );
  }

  try {
    git(['fetch', 'origin', 'main']);
  } catch (_) {
    // Offline / no remote — continue with local origin/main if present.
  }

  let behind = '0';
  try {
    behind = git(['rev-list', '--count', 'HEAD..origin/main']);
  } catch (_) {
    behind = '0';
  }

  if (Number(behind) > 0) {
    throw new Error(
      `Refusing full FTP upload: local main is ${behind} commit(s) behind origin/main.\n` +
        'Run: git pull origin main\n' +
        'Then retry, or use: node scripts/ftp-upload-files.js <paths…>'
    );
  }

  const hubCardsPath = path.join(ROOT, 'includes', 'hub-cards.php');
  const appPath = path.join(ROOT, 'includes', 'app.php');
  if (!fs.existsSync(hubCardsPath)) {
    throw new Error('Refusing full FTP upload: includes/hub-cards.php is missing (stale tree).');
  }

  const appSource = fs.readFileSync(appPath, 'utf8');
  if (!appSource.includes("'tier'  => 'uat'") && !appSource.includes('ENVIRONMENT_TIER_UAT')) {
    throw new Error(
      'Refusing full FTP upload: includes/app.php has no UAT-tier hub cards (stale tree).'
    );
  }

  console.log(`Full FTP upload allowed from main @ ${head} (not behind origin/main).`);
}

async function main() {
  assertSafeFullUpload();

  const cfg = loadConfig();
  const files = collectFiles(ROOT);

  console.log(`Connecting to ${cfg.host}:${cfg.port}...`);
  const client = await connect(cfg);
  console.log(`Connected. Uploading ${files.length} files to ${cfg.remotePath}...`);

  try {
    for (const rel of files) {
      const localPath = path.join(ROOT, rel);
      const remotePath = `${cfg.remotePath}/${rel}`;

      await mkdirp(client, path.posix.dirname(remotePath));
      await putFile(client, localPath, remotePath);
      console.log(`  ${rel}`);
    }

    console.log('Done.');
  } finally {
    client.end();
  }
}

main().catch((err) => {
  console.error('Upload failed:', err.message);
  process.exit(1);
});
