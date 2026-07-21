# NutraAxis Operations — Development Log

Running record of changes, deployments, and database work for the Operations site.

**Project root:** `/Users/jbutle4/Sites/nutraaxis`  
**Production host:** Azure App Service (`/site/wwwroot/`)  
**Database:** Azure SQL — `nutraaxisdb01.database.windows.net` / `nutraaxis`

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

---

## 2026-07-21 — Land Approvals Queue + Contacts List; stop session overwrites

- Merged **PR #15** (`feature/procurement-invoices-and-bids`) into `main` — Approvals Queue (`/procurement-approvals/`) + procurement invoice/bid hub wiring.
- Ported **Contacts List** from commit `e8a75f3` (PR #12 tip was too conflicted to force-merge) onto `main` as `57afc22`; resolved `includes/admin.php` to keep Payment/QBO/Provider permissions **and** `ContactsList`.
- Merged clean open PRs: **#13** (FTP `.tmp` hygiene + orphan cleanup script), **#10** (portal pages data sources CSV), **#9** (SKU recommended/web description SQL — note parallel `sql/067_*` filenames with Contacts).
- Ported PR **#11** catalog full-width markup onto `product-catalog/index.php` (CSS already on `main`); PR remains open for manual close (integration token cannot close PRs).
- PR **#12** Contacts extracted; full UAT branch still conflicts — leave for manual close. **PR #17** QBO inventory draft left **WIP** (do not merge).
- Added root [`AGENTS.md`](../AGENTS.md): **deploy-then-merge** + **pre-merge open-branch conflict scan**.
- SQL: apply `sql/067_create_contacts_list.sql` + `sql/068_add_contacts_list_permission.sql` on Azure if not already applied (`node scripts/run-sql-file.js …`).
- Deploy: Cloud agent session had no `.vscode/sftp.json` / `.env`. Verified live already auth-redirects `/procurement-approvals/` and `/contacts-list/` (page trees present). Hub card wiring still needs a local FTP of `includes/app.php` (+ auth/admin/contacts includes) via:

  ```bash
  node scripts/ftp-upload-files.js \
    procurement-approvals/index.php \
    contacts-list/index.php contacts-list/new.php contacts-list/edit.php \
    contacts-list/view.php contacts-list/delete.php \
    includes/contacts.php includes/contact-form.php \
    includes/app.php includes/auth.php includes/admin.php \
    includes/site-documentation.php \
    operations-dashboard/index.php my-account/index.php
  ```

### Deploy follow-up (same day)

- Deployed Approvals Queue + Contacts List + hub/auth includes to `nutraaxisweb` via **Kudu VFS** (`$nutraaxisweb` publishing credentials). Verified remote files contain `procurement-approvals` / `contacts-list` / `ContactsList`.
- Live URLs `/procurement-approvals/` and `/contacts-list/` return auth redirect (302).
- Azure SQL from this cloud IP is firewalled — could not re-run `067`/`068` here; App Service can still use existing Contacts schema if already applied. Whitelist agent IP or run migrations from an allowed network if ContactsList column/table is missing.
