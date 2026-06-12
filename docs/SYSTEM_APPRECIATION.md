# NutraAxis Operations — System Appreciation Document

Support guide describing the Operations web application: purpose, hosting, pages, code structure, database objects, assets, and operational procedures.

*Last updated: 2026-06-05*

---

## 1. System overview

**NutraAxis Operations** is an internal PHP web portal for NutraAxis team workflows. It provides a branded entry point and landing pages for six operational modules. Identity and access management (IAM) data is stored in **Azure SQL Database**.

| Property | Value |
|----------|-------|
| Application type | PHP (server-rendered HTML) |
| Public marketing site | [NutraAxis staging](https://main--nutrasync-eds-staging--capocommerce.aem.live/) (separate AEM site; visual style reference) |
| Operations site | Azure App Service — document root `/site/wwwroot/` |
| Database server | `nutraaxisdb01.database.windows.net` |
| Database name | `nutraaxis` |
| Local project path | `/Users/jbutle4/Sites/nutraaxis` |

### Design system

- **Font:** Inter (Google Fonts)
- **Primary teal:** `#3d8b85` / `#2a6b65`
- **Accent salmon:** `#b35632`
- **Stylesheet:** `/assets/css/operations.css`
- **Logo:** `/assets/logos/nutraaxis-logo.svg`

---

## 2. Site map & pages

### Live / implemented pages

| URL path | File | Purpose |
|----------|------|---------|
| `/` | `index.php` | Operations home — welcome message + six module cards |
| `/po-management/` | `po-management/index.php` | PO Management landing page |
| `/inventory-reporting/` | `inventory-reporting/index.php` | Inventory Reporting landing page |
| `/sales-reporting/` | `sales-reporting/index.php` | Sales Reporting landing page |
| `/inventory-forecasting/` | `inventory-forecasting/index.php` | Inventory Forecasting landing page |
| `/labeling-operations/` | `labeling-operations/index.php` | Labeling Operations landing page |
| `/operations-dashboard/` | `operations-dashboard/index.php` | Operations Dashboard landing page |

### Navigation-only (not yet built)

| URL path | Nav label | Status |
|----------|-----------|--------|
| `/site-admin/` | Site Admin | Placeholder link |
| `/my-account/` | My Account | Placeholder link |
| `/logout/` | Log Out | Placeholder link |

### Shared UI on every page

- Sticky header with NutraAxis logo and **Operations** badge
- Hamburger menu (slide-in panel) listing all six modules + account links
- Footer with copyright
- Module pages highlight the active item in the nav

---

## 3. Directory structure

```
nutraaxis/
├── index.php                 # Home page
├── .env                      # Local secrets (DO NOT deploy)
├── .gitignore
├── package.json              # npm scripts (upload, db test)
│
├── assets/
│   ├── css/
│   │   └── operations.css    # Global styles
│   └── logos/
│       └── nutraaxis-logo.svg
│
├── includes/                 # Shared PHP layout & config
│   ├── app.php               # Nav data, icons, module definitions
│   ├── head.php              # HTML <head> + CSS link
│   ├── header.php            # Site header + hamburger nav
│   ├── footer.php            # Footer + nav JavaScript
│   ├── module-landing.php    # Module page body template
│   └── module-page.php       # Module page bootstrap
│
├── po-management/
│   └── index.php
├── inventory-reporting/
│   └── index.php
├── sales-reporting/
│   └── index.php
├── inventory-forecasting/
│   └── index.php
├── labeling-operations/
│   └── index.php
├── operations-dashboard/
│   └── index.php
│
├── sql/                      # Database migration & seed scripts
│   ├── 001_create_iam_tables.sql
│   ├── 002_seed_admin_role.sql
│   ├── 003_seed_additional_roles.sql
│   └── 004_alter_role_module_permissions.sql
│
├── scripts/                  # Dev / deploy utilities
│   ├── ftp-upload.js         # Deploy key files via FTPS
│   ├── run-sql-file.js       # Execute .sql against Azure SQL
│   ├── test-db-connection.js # Verify DB connectivity
│   └── test-extension-connect.js
│
├── docs/
│   ├── DEVELOPMENT_LOG.md    # Running change log
│   └── SYSTEM_APPRECIATION.md  # This document
│
├── .vscode/
│   ├── sftp.json             # Cursor SFTP config (optional)
│   └── settings.json
│
├── Archive Sites/            # Legacy HTML archives (not deployed)
└── node_modules/             # Local npm deps (not deployed)
```

### Do not deploy to production

- `.env` — contains database credentials
- `.vscode/`
- `Archive Sites/`
- `node_modules/`
- `scripts/` (optional; dev tools only)
- `docs/` (optional; internal reference)

---

## 4. PHP application objects

### `includes/app.php`

Central configuration file:

| Object | Description |
|--------|-------------|
| `icon_svg($name, $size)` | Returns inline SVG for module icons |
| `$appFunctions` | Array of six modules (slug, title, href, icon) |
| `$accountLinks` | Site Admin, My Account, Log Out |
| `$modulePages` | Per-module landing content (headline, lead, capabilities) |
| `get_module($slug)` | Lookup merged module config by slug |

### Page render flow

**Home (`index.php`):**
```
app.php → head.php → header.php → [home content] → footer.php
```

**Module pages (`{module}/index.php`):**
```
$moduleSlug = '...'
module-page.php → app.php → head → header → module-landing → footer
```

---

## 5. Database objects (Azure SQL)

### Server & database

| Setting | Value |
|---------|-------|
| Server | `nutraaxisdb01.database.windows.net` |
| Database | `nutraaxis` |
| Port | 1433 (TLS required) |

### Table: `dbo.Role`

| Column | Type | Notes |
|--------|------|-------|
| `RoleID` | INT IDENTITY | Primary key |
| `RoleName` | NVARCHAR(100) | Unique |
| `RoleDesc` | NVARCHAR(MAX) | Role description |
| `RoleCreateDate` | DATETIME2 | Default UTC now |
| `ModifiedbyUser` | INT NULL | FK → `[User].UserID` |
| `POManagement` | NVARCHAR(10) NULL | Module permission |
| `InventoryReporting` | NVARCHAR(10) NULL | Module permission |
| `SalesReporting` | NVARCHAR(10) NULL | Module permission |
| `InventoryForecasting` | NVARCHAR(10) NULL | Module permission |
| `LabelingOperations` | NVARCHAR(10) NULL | Module permission |
| `OperationsDashboard` | NVARCHAR(10) NULL | Module permission |
| `UserAdmin` | NVARCHAR(10) NULL | User administration permission |
| `RoleAdmin` | NVARCHAR(10) NULL | Role administration permission |

**Seeded roles:**

| RoleName | RoleDesc |
|----------|----------|
| Admin | Site Administrator |
| Management User | Management Operations User |
| Inventory User | Inventory Operations User |
| Labeling User | Labeling Operations User |
| Reporting User | Reporting User |

All permission columns use a **CRUD** encoding (see §9). Values are currently `NULL` for all roles (no access).

### Table: `dbo.[User]`

| Column | Type | Notes |
|--------|------|-------|
| `UserID` | INT IDENTITY | Primary key |
| `UserName` | NVARCHAR(200) | Display name |
| `UserLogin` | NVARCHAR(100) | Unique login |
| `UserPassword` | NVARCHAR(256) | *Should be hashed at app layer* |
| `UserAssignedRole` | INT | FK → `Role.RoleID` |
| `CreateDate` | DATETIME2 | Default UTC now |
| `ModifiedDate` | DATETIME2 | Default UTC now |
| `LastPasswordReset` | DATETIME2 NULL | |
| `LastLoginDate` | DATETIME2 NULL | |
| `Modifiedbyuser` | INT NULL | FK → `[User].UserID` |

**Indexes:** `IX_User_UserAssignedRole`, `IX_User_LastLoginDate`

### SQL script execution order

Run in this order on a new database:

1. `001_create_iam_tables.sql`
2. `002_seed_admin_role.sql`
3. `003_seed_additional_roles.sql`
4. `004_alter_role_module_permissions.sql` *(only if 001 was run before module permissions were added)*
5. `005_alter_role_admin_permissions.sql` *(only if 001 was run before admin permissions were added)*
6. `006_permission_crud_constraints.sql` — validates permission column values

```bash
node scripts/run-sql-file.js sql/001_create_iam_tables.sql
node scripts/run-sql-file.js sql/002_seed_admin_role.sql
node scripts/run-sql-file.js sql/003_seed_additional_roles.sql
```

---

## 6. Configuration & secrets

### Local: `.env`

| Variable | Purpose |
|----------|---------|
| `DB_HOST` | SQL server FQDN |
| `DB_NAME` | Database name (`nutraaxis`) |
| `DB_USER` | SQL login |
| `DB_PASS` | SQL password (quoted if special chars) |
| `DB_PORT` | `1433` |
| `AZURE_RESOURCE_GROUP` | Optional, for Azure CLI |
| `AZURE_SUBSCRIPTION_ID` | Optional |

### Production

Store connection strings in **Azure App Service → Configuration → Connection strings**, not in deployed files.

### Azure SQL firewall

- **Development:** Whitelist developer IP under SQL Server → Networking.
- **Production:** Enable **Allow Azure services** so App Service can connect.

---

## 7. Deployment

### FileZilla (primary)

| Setting | Value |
|---------|-------|
| Protocol | FTP — implicit TLS |
| Host | `waws-prod-bn1-287.ftp.azurewebsites.windows.net` |
| User | `nutraaxisweb\$nutraaxisweb` |
| Remote path | `/site/wwwroot/` |

**Minimum deploy set:**

- `index.php`
- `includes/` (entire folder)
- `assets/` (css + logos)
- Each module folder (`po-management/`, etc.)

### npm alternative

```bash
npm run upload
```

---

## 8. Development utilities

| Command | Purpose |
|---------|---------|
| `npm run upload` | FTPS upload of `index.php` + logo |
| `npm run test-ftp` | Test SFTP-style FTP connection settings |
| `node scripts/test-db-connection.js` | Verify Azure SQL login |
| `node scripts/run-sql-file.js sql/<file>` | Execute SQL script |

---

## 9. Permissions — CRUD access model

Each permission column on `dbo.Role` stores a **subset of CRUD** actions the role may perform for that module or admin area.

| Letter | Action | Typical use |
|--------|--------|-------------|
| **C** | Create | Add new records (POs, users, roles, etc.) |
| **R** | Read | View lists, reports, and detail screens |
| **U** | Update | Edit existing records |
| **D** | Delete | Remove or deactivate records |

### Encoding rules

- Store letters in **canonical order**: `C`, then `R`, then `U`, then `D`.
- `NULL` or empty = **no access** to that area.
- `CRUD` = full access (create, read, update, delete).
- Partial access examples: `R` (view only), `RU` (view + edit), `CR` (create + view).

### Valid values (enforced by `006_permission_crud_constraints.sql`)

```
C, R, U, D,
CR, CU, CD, RU, RD, UD,
CRU, CRD, CUD, RUD, CRUD
```

### Examples by role (recommended starting point)

| Role | Suggested permissions |
|------|----------------------|
| Admin | `CRUD` on all columns including `UserAdmin` and `RoleAdmin` |
| Management User | `CRUD` on operational modules; `R` on reporting |
| Inventory User | `CRUD` on `InventoryReporting`, `InventoryForecasting`; `R` elsewhere |
| Labeling User | `CRUD` on `LabelingOperations`; `R` elsewhere |
| Reporting User | `R` on `SalesReporting`, `InventoryReporting`, `OperationsDashboard` |

### Module ↔ column mapping

| Site module / area | `Role` permission column |
|--------------------|--------------------------|
| PO Management | `POManagement` |
| Inventory Reporting | `InventoryReporting` |
| Sales Reporting | `SalesReporting` |
| Inventory Forecasting | `InventoryForecasting` |
| Labeling Operations | `LabelingOperations` |
| Operations Dashboard | `OperationsDashboard` |
| Site Admin — Users | `UserAdmin` |
| Site Admin — Roles | `RoleAdmin` |

### PHP helpers

`includes/permissions.php` provides:

| Function | Purpose |
|----------|---------|
| `permission_has($value, 'R')` | Test for a single CRUD letter |
| `permission_can_read($value)` | Shorthand for Read |
| `permission_can_create/update/delete($value)` | Shorthand for C / U / D |
| `permission_label($value)` | Human label, e.g. `CRUD` → "Create, Read, Update, Delete" |
| `permission_is_valid($value)` | Validate before save |

Example:

```php
require_once __DIR__ . '/permissions.php';

if (permission_can_read($role['POManagement'])) {
    // show PO module
}
```

---

## 10. Support contacts & references

| Resource | Location |
|----------|----------|
| Change history | `docs/DEVELOPMENT_LOG.md` |
| Brand reference site | https://main--nutrasync-eds-staging--capocommerce.aem.live/ |
| Azure Portal | SQL server `nutraaxisdb01`, App Service FTP host above |
| Archive / legacy HTML | `Archive Sites/` (local only, not production) |

---

## 11. Quick troubleshooting

| Issue | Check |
|-------|-------|
| Site shows old home page | Confirm `index.php` uploaded to `/site/wwwroot/` |
| Module page 404 | Upload corresponding `{module}/index.php` folder |
| CSS/logo missing | Use root paths (`/assets/...`); upload `assets/` folder |
| SQL connection timeout | Azure SQL firewall — whitelist your IP |
| SQL login failed | Verify `.env` credentials; check `DB_NAME=nutraaxis` |
| SFTP extension fails | Use FileZilla or `npm run upload` (port 21 explicit TLS for extension) |

---

*This document should be updated whenever pages, schema, or deployment procedures change. See `DEVELOPMENT_LOG.md` for chronological change history.*
