#!/usr/bin/env node
/**
 * Mimics Natizyskunk SFTP extension FTP _doConnect() exactly.
 * Usage: node scripts/test-extension-connect.js
 */

const fs = require('fs');
const path = require('path');

const cfg = JSON.parse(fs.readFileSync(path.join(__dirname, '../.vscode/sftp.json'), 'utf8'));
const defaults = {
  remotePath: './',
  uploadOnSave: false,
  protocol: 'sftp',
  connectTimeout: 10000,
  secure: false,
};
const merged = { ...defaults, ...cfg };

// Extension adds debug before connect
merged.debug = (msg) => {
  const m = msg.match(/^\[connection\] (>|<) (.*?)(\r\n)?$/);
  if (m && !m[2].match(/200 NOOP/)) {
    const line = m[2].match(/^PASS /) ? 'PASS ******' : m[2];
    console.log(`${m[1]} ${line}`);
  }
};

function omit(obj, keys) {
  return Object.keys(obj).reduce((acc, key) => {
    if (!keys.includes(key)) acc[key] = obj[key];
    return acc;
  }, {});
}

const FTP = require('ftp');

function extensionDoConnect(e) {
  return new Promise((resolve, reject) => {
    const client = new FTP();
    let connected = false;
    const { username, connectTimeout = 3000 } = e;
    const rest = omit(e, ['username', 'connectTimeout']);

    const timer = setTimeout(() => {
      if (!connected) {
        client.end();
        reject(new Error('Timeout while connecting to server (extension wrapper)'));
      }
    }, connectTimeout);

    client
      .on('ready', () => {
        connected = true;
        clearTimeout(timer);
        resolve('READY');
        client.end();
      })
      .on('error', (err) => {
        clearTimeout(timer);
        reject(err);
      })
      .connect(
        Object.assign(
          { keepalive: 10000, pasvTimeout: connectTimeout },
          rest,
          { connTimeout: connectTimeout, user: username }
        )
      );
  });
}

console.log('Config:', {
  protocol: merged.protocol,
  port: merged.port,
  secure: merged.secure,
  connectTimeout: merged.connectTimeout,
  username: merged.username,
});

extensionDoConnect(merged)
  .then((msg) => {
    console.log('Success:', msg);
  })
  .catch((err) => {
    console.error('Failed:', err.message);
    process.exit(1);
  });
