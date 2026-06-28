#!/usr/bin/env node
/**
 * Upload specific files to Azure App Service via FTPS.
 * Usage: node scripts/ftp-upload-files.js path/to/file1 path/to/file2
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const CONFIG_PATH = path.join(ROOT, '.vscode', 'sftp.json');

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

async function main() {
  const relFiles = process.argv.slice(2).map((f) => f.replace(/\\/g, '/'));
  if (relFiles.length === 0) {
    console.error('Usage: node scripts/ftp-upload-files.js <file> [file...]');
    process.exit(1);
  }

  const cfg = loadConfig();
  console.log(`Connecting to ${cfg.host}:${cfg.port}...`);
  const client = await connect(cfg);

  try {
    for (const rel of relFiles) {
      const localPath = path.join(ROOT, rel);
      if (!fs.existsSync(localPath)) {
        throw new Error(`File not found: ${rel}`);
      }
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
