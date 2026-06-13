#!/usr/bin/env node
/**
 * Upload a small marker file to production and staging via FTPS.
 * Usage: node scripts/ftp-test-environments.js
 *
 * Production credentials: .vscode/sftp.json
 * Staging password: .vscode/sftp-staging.json (optional) or Azure CLI publish profile
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const CONFIG_PATH = path.join(ROOT, '.vscode', 'sftp.json');
const STAGING_CONFIG_PATH = path.join(ROOT, '.vscode', 'sftp-staging.json');

function loadFtp() {
  try {
    return require('ftp');
  } catch (_) {
    throw new Error('FTP module not found. Run: npm install');
  }
}

function loadJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function loadStagingPassword() {
  if (fs.existsSync(STAGING_CONFIG_PATH)) {
    const cfg = loadJson(STAGING_CONFIG_PATH);
    if (cfg.password) {
      return cfg.password;
    }
  }

  try {
    const profiles = JSON.parse(
      execSync(
        'az webapp deployment list-publishing-profiles --resource-group NutraSync --name nutraaxisweb --slot staging -o json',
        { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }
      )
    );
    const ftpProfile = profiles.find((profile) => profile.publishMethod === 'FTP');
    if (ftpProfile?.userPWD) {
      return ftpProfile.userPWD;
    }
  } catch (_) {
    /* fall through */
  }

  throw new Error(
    'Staging FTP password not found. Add .vscode/sftp-staging.json or run az login and retry.'
  );
}

function loadBaseConfig() {
  const cfg = loadJson(CONFIG_PATH);
  return {
    host: cfg.host,
    port: cfg.port || 21,
    password: cfg.password,
    productionUser: cfg.username,
    secure: cfg.secure === true ? true : cfg.secure || 'control',
    secureOptions: cfg.secureOptions || { rejectUnauthorized: false },
    remotePath: (cfg.remotePath || '/site/wwwroot').replace(/\/$/, ''),
    connTimeout: cfg.connectTimeout || 120000,
  };
}

function connect(cfg) {
  const FTP = loadFtp();
  const client = new FTP();

  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      client.destroy();
      reject(new Error(`Timeout connecting to ${cfg.label}`));
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

function putFile(client, localPath, remotePath) {
  return new Promise((resolve, reject) => {
    client.put(localPath, remotePath, (err) => {
      if (err) reject(err);
      else resolve();
    });
  });
}

async function uploadTarget(baseCfg, target) {
  const cfg = {
    ...baseCfg,
    user: target.user,
    password: target.password,
    label: target.label,
  };
  const localPath = path.join(ROOT, target.localFile);
  const remotePath = `${cfg.remotePath}/${target.remoteFile}`;
  const content = [
    `environment=${target.label}`,
    `uploaded_at=${new Date().toISOString()}`,
    `remote_path=${remotePath}`,
  ].join('\n');

  fs.writeFileSync(localPath, content, 'utf8');

  console.log(`Uploading to ${target.label} (${cfg.user})...`);
  const client = await connect(cfg);
  try {
    await putFile(client, localPath, remotePath);
    console.log(`  OK -> ${remotePath}`);
    return target.url;
  } finally {
    client.end();
    fs.unlinkSync(localPath);
  }
}

async function main() {
  const baseCfg = loadBaseConfig();
  const stagingPassword = loadStagingPassword();
  const stamp = new Date().toISOString().replace(/[:.]/g, '-');

  const targets = [
    {
      label: 'production',
      user: baseCfg.productionUser,
      password: baseCfg.password,
      localFile: '.ftp-test-production.txt',
      remoteFile: `ftp-test-production-${stamp}.txt`,
      url: `https://nutraaxisweb.azurewebsites.net/ftp-test-production-${stamp}.txt`,
    },
    {
      label: 'staging',
      user: 'nutraaxisweb__staging\\$nutraaxisweb__staging',
      password: stagingPassword,
      localFile: '.ftp-test-staging.txt',
      remoteFile: `ftp-test-staging-${stamp}.txt`,
      url: `https://nutraaxisweb-staging.azurewebsites.net/ftp-test-staging-${stamp}.txt`,
    },
  ];

  console.log('Verify in browser or curl after upload:\n');
  for (const target of targets) {
    const url = await uploadTarget(baseCfg, target);
    console.log(`  ${target.label}: ${url}`);
  }
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
