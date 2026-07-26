#!/usr/bin/env node
/**
 * Build a wwwroot deployment zip for Azure App Service (git-push deploy).
 * Mirrors the ignore rules from scripts/ftp-upload.js.
 *
 * Usage: node scripts/build-deploy-package.js [output.zip]
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const OUTPUT = path.resolve(process.argv[2] || path.join(ROOT, 'deploy-package.zip'));

const IGNORE_DIRS = new Set([
  '.git',
  '.github',
  '.vscode',
  '.cursor',
  'node_modules',
  'scripts',
  'sql',
  'docs',
  'functions',
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
  'deploy-package.zip',
  'AGENTS.md',
]);

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

function main() {
  const staging = fs.mkdtempSync(path.join(require('os').tmpdir(), 'nutraaxis-deploy-'));
  const files = collectFiles(ROOT);

  for (const rel of files) {
    const dest = path.join(staging, rel);
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.copyFileSync(path.join(ROOT, rel), dest);
  }

  if (fs.existsSync(OUTPUT)) {
    fs.unlinkSync(OUTPUT);
  }

  execFileSync('zip', ['-rq', OUTPUT, '.'], { cwd: staging, stdio: 'inherit' });
  fs.rmSync(staging, { recursive: true, force: true });

  const sizeMb = (fs.statSync(OUTPUT).size / (1024 * 1024)).toFixed(2);
  console.log(`Wrote ${OUTPUT} (${files.length} files, ${sizeMb} MB)`);
}

main();
