#!/usr/bin/env node
/**
 * Upload nginx bootstrap files to Azure App Service persistent storage and
 * optionally set the startup command via Azure CLI.
 *
 * Usage:
 *   node scripts/deploy-azure-nginx.js
 *   node scripts/deploy-azure-nginx.js --apply-startup
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const CONFIG_PATH = path.join(ROOT, '.vscode', 'sftp.json');
const FILES = [
  { local: 'azure/startup.sh', remote: '/site/startup.sh' },
  { local: 'health.php', remote: '/site/wwwroot/health.php' },
  { local: 'ping.php', remote: '/site/wwwroot/ping.php' },
  { local: 'ops-dashboard.php', remote: '/site/wwwroot/ops-dashboard.php' },
  { local: 'dash.php', remote: '/site/wwwroot/dash.php' },
  { local: 'test-dash.php', remote: '/site/wwwroot/test-dash.php' },
  { local: 'legal-agreements.php', remote: '/site/wwwroot/legal-agreements.php' },
  { local: 'product-catalog.php', remote: '/site/wwwroot/product-catalog.php' },
  { local: 'operations-dashboard.php', remote: '/site/wwwroot/operations-dashboard.php' },
  { local: 'operations-dashboard/index.php', remote: '/site/wwwroot/operations-dashboard/index.php' },
  { local: 'product-catalog/index.php', remote: '/site/wwwroot/product-catalog/index.php' },
  { local: 'legal-agreements/index.php', remote: '/site/wwwroot/legal-agreements/index.php' },
  { local: 'includes/init.php', remote: '/site/wwwroot/includes/init.php' },
  { local: 'includes/catalog.php', remote: '/site/wwwroot/includes/catalog.php' },
  { local: 'includes/legal.php', remote: '/site/wwwroot/includes/legal.php' },
  { local: 'includes/labeling.php', remote: '/site/wwwroot/includes/labeling.php' },
  { local: 'includes/app.php', remote: '/site/wwwroot/includes/app.php' },
  { local: 'labeling-operations/batch-printing/index.php', remote: '/site/wwwroot/labeling-operations/batch-printing/index.php' },
  { local: 'labeling-operations/templates/index.php', remote: '/site/wwwroot/labeling-operations/templates/index.php' },
  { local: 'labeling-operations/versions/index.php', remote: '/site/wwwroot/labeling-operations/versions/index.php' },
  { local: 'labeling-operations/compliance/index.php', remote: '/site/wwwroot/labeling-operations/compliance/index.php' },
  { local: 'labeling-operations/white-label-orders/index.php', remote: '/site/wwwroot/labeling-operations/white-label-orders/index.php' },
  { local: 'includes/table-sort.php', remote: '/site/wwwroot/includes/table-sort.php' },
];

function loadConfig() {
  return JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));
}

function loadFtp() {
  try {
    return require('ftp');
  } catch (_) {
    throw new Error('FTP module not found. Run: npm install ftp');
  }
}

function connect(cfg) {
  const FTP = loadFtp();
  const client = new FTP();

  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      client.destroy();
      reject(new Error('Timeout while connecting to server'));
    }, cfg.connectTimeout || 120000);

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
      port: cfg.port || 21,
      user: cfg.username,
      password: cfg.password,
      secure: cfg.secure === true ? true : cfg.secure || 'control',
      secureOptions: cfg.secureOptions || { rejectUnauthorized: false },
      connTimeout: cfg.connectTimeout || 120000,
    });
  });
}

function putFile(client, localPath, remotePath) {
  return new Promise((resolve, reject) => {
    client.put(localPath, remotePath, (err) => {
      if (err) reject(err);
      else resolve();
    });
  });
}

async function main() {
  const cfg = loadConfig();
  const client = await connect(cfg);

  try {
    for (const file of FILES) {
      const localPath = path.join(ROOT, file.local);
      await putFile(client, localPath, file.remote);
      console.log(`Uploaded ${file.local} -> ${file.remote}`);
    }
  } finally {
    client.end();
  }

  if (process.argv.includes('--apply-startup')) {
    execSync(
      'az webapp config set -g NutraSync -n nutraaxisweb --startup-file "/home/site/startup.sh"',
      { stdio: 'inherit' }
    );
    console.log('Startup command set. App Service will restart.');
  } else {
    console.log('Run with --apply-startup to set Azure startup command.');
  }
}

main().catch((err) => {
  console.error('Deploy failed:', err.message);
  process.exit(1);
});
