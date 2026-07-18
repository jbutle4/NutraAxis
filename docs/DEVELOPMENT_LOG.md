# NutraAxis Operations — Development Log

Running record of changes, deployments, and database work for the Operations site.

**Project root:** `/Users/jbutle4/Sites/nutraaxis`  
**Production host:** Azure App Service (`/site/wwwroot/`)  
**Database:** Azure SQL — `nutraaxisdb01.database.windows.net` / `nutraaxis`

---

## 2026-07-18 — Transfer JE smoke (asset accounts + retry)

- Root cause of first JE failure: `QBO_INV_ASSET_ACCOUNT_*` pointed at Ids from realm `9341457225657953` (stale `QBO_COA`), while the connected sandbox is `9341457230168529`.
- Created live sandbox accounts under Inventory Asset:
  - Cart.com `1150040000`, WPC WIP `1150040001`, CPPC `1150040002`.
- Transfer resolve now validates env Ids against the connected realm’s `QBO_COA`, then falls back to account name lookup.
- JE retry skips only when sync status is `Synced` (Error rows can retry); transfer view shows QBO doc status + **Retry QBO journal**.
- Smoke: Transfer **#1** CART→WPC_QUEUE qty 2 `NA-MT-004` — IMS Received; `NA-XFER-1` Synced; QBO JournalEntry **170** ($33.20).
- Note: App Service appsettings may still hold the old wrong Ids until Azure RBAC allows an update; name fallback covers the live realm.

## 2026-07-18 — Jazz → IMS CART align

- Portal `/inventory-jazz-ims-align/` previews Jazz on-hand vs IMS CART deltas and posts `JazzSyncReconcile` (IMS only).
- SQL `125` — `InventoryJazzImsAlignRun` audit (dry-run / apply).
- Confirm gate (`ALIGN`); optional zero-out for IMS SKUs missing from Jazz; link from Jazz vs IMS recon.
- Negative Jazz on-hand clamped to align target 0 (IMS QtyOK cannot go negative).
- Does **not** bootstrap QBO QtyOnHand from Jazz.
- Smoke (Jazz UAT): dry-run #1; failed partial #2; success run **#3** / txn **11** posted **11** lines — post-align mismatches vs clamped Jazz target **0** (`NA-MT-004`/`NA-HR-006` = 1000/999).

## 2026-07-18 — Jazz vs IMS CART balance recon

- Portal `/inventory-jazz-ims-recon/` compares Jazz mothership `on_hand_quantity` to IMS CART OK+Q+H.
- Resolves Jazz facility codes via `Facility.ExternalReferenceCode` (CART ← `FBF09`).
- Prod/UAT Jazz toggle + mismatches-only filter; Inventory hub card registered.
- Smoke (live Jazz + SQL): Jazz facility `FBF09`, 17 SKUs compared, **16 mismatches** (expected — IMS sandbox smoke qty ≠ Jazz mothership on-hand; e.g. `NA-MT-004` Jazz 1000 vs IMS 7).

## 2026-07-18 — Inventory adjustments (shrink/gain workflow)

- Portal `/inventory-adjustments/` — create Pending, approve/reject, Retry QBO.
- Approve posts IMS `AdjustmentLoss`/`AdjustmentGain` + QBO InventoryAdjustment; DocNumber `NA-ADJ-{id}`.
- SQL `124` adds `Adjustment` to `QBOInventorySyncLog.SyncType`.
- PHP QBO adj line detail fixed to `ItemAdjustmentLineDetail` + sync-log MERGE upsert.
- App Service env allowlist: `QBO_INV_ADJUST_ACCOUNT_ID` (+ asset account keys) now readable via `env()`.
- Movement recon flags pending, approved-unposted, and QBO Error/missing for adjustments.
- Smoke: Adjustment **1** Approved — `NA-MT-004` CART 8→7; IMS txn **9**; QBO adj **169**; sync `NA-ADJ-1` Synced.

## 2026-07-18 — Inventory sales sync smoke (sandbox)

- ACCS order tables were empty; seeded stage order `NA-SMOKE-SAL-001` via `sql/123_seed_sandbox_sales_sync_smoke_order.sql` (qty 2 × `NA-MT-004` / `NA-HR-006`).
- Hardened sales sync: reuse existing IMS `Sale` txn per header (no double decrement on QBO retry).
- QBO client: refresh 2 minutes early + force-refresh retry on HTTP 401 (stale JWT with future SQL expiry).
- Process Log **486** Success — IMS CART 10→8 both SKUs; QBO InventoryAdjustment txn `168`; docs `NA-SAL-1-1` / `NA-SAL-1-2` Synced.
- Re-run **487** skipped both lines; movement recon **488** / ReconRun **3** — 0 exceptions.

## 2026-07-18 — Inventory movement completeness recon (Layer 1)

- Added `sql/122_create_inventory_movement_recon.sql` (`InventoryMovementReconRun` / `InventoryMovementReconLine`).
- Function job `inventory-movement-recon` scans receipts, sales, transfers, and adjustments for missing IMS/QBO posts.
- Process Log registry + timer (default 4:00 AM CT; disable on test app like other inventory timers).
- Portal `/inventory-movement-recon/` + Inventory hub card **Movement Completeness**.
- Deployed: SQL `122` on `nutraaxis`; Function App **Nutra-forecast-tool** (timer disabled); portal **nutraaxisweb**.
- Smoke: Process Log **483** / ReconRun **1** Success — `0` exceptions (POR 19 already Synced; no pending sales/transfers/adjs in window).

## 2026-07-18 — Inventory receipt sync unblocked (sandbox)

- Root cause: `SKUMaster.QBO_ItemID` pointed at stale NonInventory/Service Ids; live Inventory twins were different Ids.
- Receipt/sales sync now resolve live QBO Inventory Item Ids by SKU and upsert sync-log Error rows for retry.
- Simulated Jazz ASN `231041` / POR 19: IMS CART +10 for `NA-MT-004`/`NA-HR-006`; QBO InventoryAdjustment txn `167`; Process Log **481** Success.

---

## 2026-07-17 — QBO inventory sync Function App + portal deploy (sandbox)

- Published `functions/` to **Nutra-forecast-tool** (sandbox), including `inventory-receipt-sync` and `inventory-sales-sync`.
- Deployed portal zip to **nutraaxisweb** (Process Log registry + Run controls, IMS pages).
- Applied SQL `067`, `117`–`121` on `nutraaxis` (IMS tables, `POReceipt.IMSPostedAt`, `QBOInventorySyncLog`, WPC facilities).
- Set Function App `DB_NAME_INVENTORY_SYNC=nutraaxis`, disabled inventory timers on test app (`0 0 0 1 1 2099`), confirmed `QBO_INV_ADJUST_ACCOUNT_ID=85`.
- Smoke: Process Log run ids 463/464 — Inventory Receipt Sync / Inventory Sales Sync completed (`0` rows when no pending receipts/orders).

---

## 2026-06-05 — Project bootstrap

- Created default home page `index.php` with NutraAxis branding (teal/salmon palette, Inter font).
- Added `assets/logos/nutraaxis-logo.svg`.
- Configured deployment target to Azure App Service root (`/site/wwwroot/`).
- SFTP extension troubleshooting documented; FileZilla used for deployment (implicit FTPS, port 990).
- Added `npm run upload` FTP upload script as alternative deploy path.

---

## 2026-06-05 — Home page navigation & modules

- Replaced three marketing cards with **six operations function blocks**:
  - PO Management
  - Inventory Reporting
  - Sales Reporting
  - Inventory Forecasting
  - Labeling Operations
  - Operations Dashboard
- Added **hamburger navigation** with Applications + Account sections (Site Admin, My Account, Log Out).
- Refactored layout into shared includes (`includes/`) and `assets/css/operations.css`.
- Created module landing pages (one per function) with capability cards and “in development” status banner.

---

## 2026-06-05 — Azure SQL connectivity

- Added local `.env` for database credentials (gitignored, not uploaded).
- Verified connection to `nutraaxisdb01.database.windows.net`, database `nutraaxis`.
- Documented firewall requirement: client IP must be whitelisted in Azure SQL networking.
- Added connection test scripts: `scripts/test-db-connection.js`, `scripts/run-sql-file.js`.

---

## 2026-06-05 — IAM database schema

### `sql/001_create_iam_tables.sql`
- Created `dbo.Role` table.
- Created `dbo.[User]` table with FK to `Role.RoleID`.
- Added self-referencing FKs for `ModifiedbyUser` / `Modifiedbyuser`.
- Added indexes on `UserAssignedRole` and `LastLoginDate`.

### `sql/002_seed_admin_role.sql`
- Seeded **Admin** role (`Site Administrator`).

### `sql/003_seed_additional_roles.sql`
- Seeded roles:
  - Management User
  - Inventory User
  - Labeling User
  - Reporting User

### `sql/004_alter_role_module_permissions.sql`
- Extended `dbo.Role` with module permission columns (`NVARCHAR(10)`):
  - `POManagement`
  - `InventoryReporting`
  - `SalesReporting`
  - `InventoryForecasting`
  - `LabelingOperations`
  - `OperationsDashboard`
- Updated `001_create_iam_tables.sql` for greenfield installs.

---

## 2026-06-05 — PO Management NutraSeal fields, attachments, Excel import

- Extended PO schema to match NutraSeal sample PDF (buyer, supplier, terms, quote #, exp date, shipping, total due).
- `POAttachment` table — PDF/Excel stored as `VARBINARY(MAX)` blob in Azure SQL.
- Excel/CSV import template (`assets/templates/`) + `/po-management/import.php`.
- Attach PDF on create; upload/download attachments on PO view.
- `sql/011_alter_po_nutraseal_fields.sql`, `012_seed_nutraseal_supplier.sql`.

---

## 2026-06-05 — PO Management application (v1)

- Database: `Supplier`, `PurchaseOrder`, `POLineItem` (`009_create_po_tables.sql`).
- Sample suppliers seeded (`010_seed_po_suppliers.sql`).
- App pages: PO list, create, view, edit, delete, status workflow (Draft → Submitted → Approved).
- CRUD gated by `POManagement` role permission.
- Includes: `po.php`, `po-nav.php`, `po-form.php`.

**Apply schema:**
```bash
node scripts/run-sql-file.js sql/009_create_po_tables.sql
node scripts/run-sql-file.js sql/010_seed_po_suppliers.sql
```

---

## 2026-06-05 — Site Admin pages

- **Users** (`/site-admin/users/`): list, create, edit, delete — gated by `UserAdmin` CRUD.
- **Roles** (`/site-admin/roles/`): list, view, create, edit, delete — gated by `RoleAdmin` CRUD.
- Permission matrix editor with C/R/U/D checkboxes per module and admin area.
- Shared admin includes: `admin.php`, `admin-nav.php`, `admin-permission-grid.php`.
- Site Admin hub updated with links to Users and Roles (removed in-development banner).

---

## 2026-06-05 — Login and IAM enforcement

- Session-based authentication against `dbo.[User]` and `dbo.Role`.
- New pages: `/login/`, `/logout/`, `/my-account/`, `/site-admin/`.
- Home page: **Log In** link in header and hero; module links redirect to login when signed out.
- Protected pages require login; modules require **Read** on the matching role permission column.
- Site Admin requires **Read** on `UserAdmin` or `RoleAdmin`.
- Nav and home module cards filtered by role when signed in.
- New includes: `init.php`, `auth.php`, `database.php`, `env.php`, `access-denied.php`.
- **Deploy note:** App Service needs DB credentials in `.env` or App Settings.
- Applied `007_seed_role_permissions.sql` to Azure SQL (all five roles).

---

## 2026-06-05 — Seed users

- `sql/008_seed_users.sql` — inserted three Management User accounts (RoleID 2):
  - Josh Stoneking (`jstoneking@wellsrx.com`)
  - Madison Landis (`mlandis@nfcllc.com`)
  - Jennifer Richmond (`jrichmond@nfcllc.com`)
- Initial password: `welcome1` (plain text; hash when login is implemented).
- `Modifiedbyuser` = 1 for all three.

---

## 2026-06-05 — Role permission seed (draft, pending review)

- `sql/007_seed_role_permissions.sql` — proposed CRUD values for all five roles.
- `docs/ROLE_PERMISSIONS_REVIEW.md` — permission matrix for review.
- Management User: `UserAdmin` and `RoleAdmin` set to `R` (per review).
- **Not applied to database** — awaiting user approval.

---

## 2026-06-05 — CRUD permission model

- Defined access encoding: permission columns store CRUD letter combinations (`C`, `R`, `U`, `D` and subsets in canonical order).
- `NULL` = no access; `CRUD` = full access.
- `sql/006_permission_crud_constraints.sql` — CHECK constraints on all eight permission columns.
- `includes/permissions.php` — PHP helpers (`permission_has`, `permission_can_read`, etc.).
- Documented in `SYSTEM_APPRECIATION.md` §9.

---

## 2026-06-05 — Role admin permission columns

- Added `UserAdmin` and `RoleAdmin` columns to `dbo.Role` (`NVARCHAR(10)`).
- `sql/005_alter_role_admin_permissions.sql` — migration for existing databases.
- Updated `001_create_iam_tables.sql` for greenfield installs.

---

## 2026-06-05 — Documentation

- Added `docs/DEVELOPMENT_LOG.md` (this file).
- Added `docs/SYSTEM_APPRECIATION.md` (system support guide).

---

## Pending / not yet implemented

| Item | Status |
|------|--------|
| User login / session authentication | Not started |
| Password hashing (bcrypt/Argon2) | Not started |
| Role permission values per module | Columns exist; values NULL |
| Admin / account / logout pages | Nav links only (404) |
| PHP database connection layer | Not started |
| App Service SQL firewall (Azure services) | May be required for production |

---

## How to append to this log

When making changes, add a dated section with:
1. **What** changed (files, tables, pages).
2. **Why** (brief purpose).
3. **Deploy notes** (if applicable).
4. **SQL scripts** run (filename + order).

Example:
```markdown
## YYYY-MM-DD — Short title
- Bullet describing change
- `sql/00N_script_name.sql` — description
```
