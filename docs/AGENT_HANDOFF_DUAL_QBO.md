# Agent handoff — Dual QBO + Accounting UAT (2026-07-21)

**For the next Cursor agent (prefer Local / This Computer):** continue from here. Do **not** re-implement completed work below.

## Session context

- Repo: `jbutle4/NutraAxis`
- Branch: **`main`** @ `5e491a8` (pushed)
- Live App Service: **`nutraaxisweb`** — dual-QBO PHP already **Kudu-deployed**
- Prior cloud agent run: https://cursor.com/agents/bc-8b53cf8b-9b69-4389-8425-57c33e0fe6aa  
  (Cloud egress IPs rotate; Azure SQL firewall often blocks cloud agents. Use **Local** for SQL.)

## Completed — do not redo

1. **Dual QBO connections** (one `dbo.QBOConnection` row per `Environment`: `sandbox` | `production`)
   - `includes/quickbooks.php` — per-env get/save/disconnect/OAuth; prod creds `QBO_CLIENT_ID_PROD` / `QBO_CLIENT_SECRET_PROD`
   - Hub dual CTAs: `includes/accounting-connection-dual-banner.php` on `/accounting/`
   - `accounting/connect.php`, `callback.php`, `disconnect.php` take `env`
2. **Mirrored Production + UAT pages**
   - Browse: `ap|ar|pos|inventory|suppliers|chart-of-accounts` + `*-uat.php` stubs
   - Modules: `supplier-invoices-uat/`, `invoice-payments-uat/`
   - UAT → sandbox company; Production pages → production company via `page-data-profile` + `accounting_bind_qbo_environment()`
   - Hub cards in `includes/app.php`; path map in `includes/data-profile.php`; helper `accounting_path()`
3. **Approvals Queue / Approvals** stay shared (production URLs in emails — intentional)
4. **Bill payment posting from Operations** — out of scope (no payment bank/CC account ID env variants)
5. **PR #17 QBO inventory** — leave alone (WIP)

## Only remaining work

### 1. Apply SQL migration (blocked from cloud; run Local or Portal)

File: `sql/120_qbo_connection_unique_environment.sql`  
Dedupes rows per Environment, then creates unique index `UX_QBOConnection_Environment`.

**Local agent / Mac ZSH** (from a clone that has `.env` with `DB_*`):

```zsh
cd /path/to/NutraAxis   # real git clone, not empty Pycharm folder
node scripts/run-sql-file.js sql/120_qbo_connection_unique_environment.sql
```

**Or Azure Portal:** SQL database `nutraaxis` on `nutraaxisdb01` → Query editor → run the two batches in that file (split on `GO`).

**Verify:**

```sql
SELECT name FROM sys.indexes
WHERE object_id = OBJECT_ID(N'dbo.QBOConnection')
  AND name = N'UX_QBOConnection_Environment';

SELECT Environment, COUNT(*) AS rows
FROM dbo.QBOConnection
GROUP BY Environment;
```

### 2. Smoke-check live (after SQL)

Logged-in Accounting user with Update:

- `/accounting/` shows Sandbox + Production connect/disconnect cards
- Connect Sandbox → OAuth → returns with Production still intact (and vice versa)
- `/accounting/ap-uat.php` (or Supplier Invoices UAT) loads sandbox company when sandbox connected
- `/accounting/ap.php` loads production company when production connected

### 3. Deploy note

If you change PHP after this handoff: deploy via FTP/Kudu to `nutraaxisweb`, then ensure `main` matches live (`AGENTS.md` deploy-then-merge). SQL is DB-only — no FTP for `sql/120`.

## App settings (already expected on App Service)

| Env | Keys |
|-----|------|
| Sandbox | `QBO_CLIENT_ID`, `QBO_CLIENT_SECRET` |
| Production | `QBO_CLIENT_ID_PROD`, `QBO_CLIENT_SECRET_PROD` |
| Shared | `QBO_REDIRECT_URI`, `SITE_URL`, `QBO_INSERT_STUB` as needed |

Do **not** rely on flipping `QBO_ENVIRONMENT` for page routing anymore.

## Local vs Cloud reminder

- **Local / This Computer** — use for Azure SQL and any allowlisted-IP work
- **Auto** can send work to Cloud — avoid for SQL
- This handoff file is on `main`; pull before continuing: `git pull origin main`
