#!/usr/bin/env bash
# FTP-deploy Approvals Queue + Contacts List + hub/auth wiring to nutraaxisweb.
# Requires .vscode/sftp.json (gitignored).
set -euo pipefail
cd "$(dirname "$0")/.."
node scripts/ftp-upload-files.js \
  procurement-approvals/index.php \
  contacts-list/index.php \
  contacts-list/new.php \
  contacts-list/edit.php \
  contacts-list/view.php \
  contacts-list/delete.php \
  includes/contacts.php \
  includes/contact-form.php \
  includes/app.php \
  includes/auth.php \
  includes/admin.php \
  includes/site-documentation.php \
  operations-dashboard/index.php \
  my-account/index.php
