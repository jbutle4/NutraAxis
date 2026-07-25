# Local agent handoff — deploy invoice changes + SQL migration

Use this document with a **Cursor Local agent** (not Cloud). Cloud agents cannot access `.vscode/sftp.json`, Azure SQL firewall, or `az login` on your machine.

**Repo:** `jbutle4/NutraAxis`  
**Live App Service:** `nutraaxisweb` (`https://nutraaxisweb.azurewebsites.net`)  
**Resource group:** `NutraSync`  
**Deploy method:** FTPS (not git push)

---

## Current state (as of 2026-07-25)

| Item | Status |
|------|--------|
| Supplier invoice UI changes | On **`main`** @ commit `96453b0` |
| PR #17 (`jbutle4/cursor/qbo-inventory-cycle-dee4`) | Rebased onto `main`; GitHub shows **MERGEABLE** / **CLEAN** |
| SQL `sql/120_qbo_connection_unique_environment.sql` | Written; **not applied** to Azure SQL |
| Dual QBO + Accounting UAT | On `main`; previously Kudu-deployed for hub/OAuth |
| Invoice form deploy | **Not yet live** (blocked in cloud — no FTP creds) |

---

## Prerequisites

1. **Local clone** of NutraAxis with `main` up to date:

   ```bash
   cd ~/NutraAxis   # adjust path
   git fetch origin
   git checkout main
   git pull origin main
   ```

2. **`.vscode/sftp.json`** (gitignored) with production FTPS credentials:

   ```json
   {
     "host": "waws-prod-bn1-287.ftp.azurewebsites.windows.net",
     "port": 21,
     "username": "nutraaxisweb\\$nutraaxisweb",
     "password": "<from Azure Portal → nutraaxisweb → Deployment Center → FTPS credentials>",
     "secure": "implicit",
     "remotePath": "/site/wwwroot"
   }
   ```

   Portal path: **Azure Portal → App Service `nutraaxisweb` → Deployment Center → FTPS credentials**.

3. **Node dependencies** (for upload scripts):

   ```bash
   npm install
   ```

4. **`.env`** for SQL scripts (if running migration):

   ```
   DB_HOST=nutraaxisdb01.database.windows.net
   DB_NAME=nutraaxis
   DB_USER=<your-user>
   DB_PASS=<your-password>
   ```

5. **Azure SQL firewall:** whitelist your current public IP on SQL server `nutraaxisdb01` (Networking → Add client IP).

6. **Cursor:** switch to **Local** agent before starting.

---

## Task 1 — Deploy supplier invoice changes

### What changed (`96453b0`)

- AP Account ID and line Account ID are **dropdowns** from QBO chart of accounts (cached COA + live API fallback).
- Compact inline header labels on supplier invoice form (INV#, INV Date, etc.).
- Clearer validation when line account is missing.

### Files to upload

```bash
node scripts/ftp-upload-files.js \
  includes/supplier-invoice.php \
  includes/supplier-invoice-form.php \
  includes/quickbooks.php \
  accounting/supplier-invoices/new.php \
  accounting/supplier-invoices/edit.php \
  assets/css/operations.css
```

### Verify after deploy

1. Open **Accounting → Supplier Invoices → New** (production URL).
2. Confirm header fields use compact inline labels.
3. Confirm **AP ACCT ID** is a `<select>` populated from QBO accounts.
4. Confirm each line **Account ID** is a `<select>`, not free text.
5. Submit a test invoice (or edit draft) — prior Avalara errors were often from missing **line** `account_ref_value`, not header AP fields.

### Deploy-then-merge rule

Per `AGENTS.md`: invoice changes are already on `main`. After deploy, **do not** leave live ahead of `main`. No extra merge needed for this task unless you made local-only edits.

---

## Task 2 — Apply SQL migration (dual QBO connections)

**File:** `sql/120_qbo_connection_unique_environment.sql`

Enforces unique `Environment` on `dbo.QBOConnection` so sandbox and production OAuth rows can coexist.

```bash
node scripts/run-sql-file.js sql/120_qbo_connection_unique_environment.sql
```

**Alternative:** run the SQL batches manually in **Azure Portal → SQL database `nutraaxis` → Query editor**.

If connection fails: check `.env` `DB_*` values and SQL server firewall for your IP.

---

## Task 3 (optional) — Merge PR #17 inventory cycle

Only after invoice deploy + SQL 120 if you want inventory on production:

- **Branch:** `jbutle4/cursor/qbo-inventory-cycle-dee4`
- **PR:** https://github.com/jbutle4/NutraAxis/pull/17
- Rebase onto `main` is complete; merge state was **CLEAN**.

After merge, deploy inventory-related paths separately (full `includes/`, new `inventory-*` modules, `functions/` to Function App **Nutra-forecast-tool**, additional SQL scripts). See `docs/QBO_INVENTORY_CYCLE_RUNBOOK.md`.

**Do not** blind-merge `feat/uat-production-portal-segregation` — it had heavy conflicts with `main`.

---

## Copy-paste prompt for local agent

```
Read docs/AGENT_HANDOFF_LOCAL_DEPLOY.md and complete Task 1 and Task 2.

1. Deploy supplier invoice changes from main (96453b0) to nutraaxisweb via
   node scripts/ftp-upload-files.js using .vscode/sftp.json.
   Files: includes/supplier-invoice.php, includes/supplier-invoice-form.php,
   includes/quickbooks.php, accounting/supplier-invoices/new.php,
   accounting/supplier-invoices/edit.php, assets/css/operations.css.

2. Verify Accounting → Supplier Invoices → New shows AP and line account dropdowns.

3. Run sql/120_qbo_connection_unique_environment.sql against Azure SQL
   (node scripts/run-sql-file.js) after confirming .env and SQL firewall.

Report what was deployed and whether SQL 120 applied successfully.
```

---

## Reference

| Topic | Location |
|-------|----------|
| Deploy rules | `AGENTS.md` |
| FTPS host/user | `docs/SYSTEM_APPRECIATION.md` §7 |
| Dual QBO context | `docs/AGENT_HANDOFF_DUAL_QBO.md` |
| Upload all files | `npm run upload` or `node scripts/ftp-upload.js` |
| Upload specific files | `node scripts/ftp-upload-files.js <paths…>` |
| Test FTP prod + staging | `node scripts/ftp-test-environments.js` (needs `az login` for staging password fallback) |

---

## Out of scope unless explicitly requested

- Bill payment posting from Operations
- Full PR #17 inventory deploy before merge
- Merging `feat/uat-production-portal-segregation`
