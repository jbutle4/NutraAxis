# Role Permissions

**Status:** Operational roles aligned via `sql/076_align_operational_role_permissions.sql` (June 2026).

**Apply / refresh:**
```bash
node scripts/run-sql-file.js sql/076_align_operational_role_permissions.sql
```

Module permission columns are defined in `includes/auth.php` (`MODULE_PERMISSION_COLUMNS`). New Supply Chain pages inherit existing columns — for example PO Payments and Jazz ASNs use `POManagement`; Jazz Item Master and UAT inventory pages use `InventoryReporting`; Process Log and Site Documentation use `OperationsDashboard`.

---

## Permission matrix — operational roles

| Permission column | Admin | Management User | Inventory User | Labeling User | Reporting User | PO Approver |
|-------------------|:-----:|:---------------:|:--------------:|:-------------:|:--------------:|:-----------:|
| POManagement | CRUD | CRUD | R | R | — | R |
| POApproval | CRUD | — | — | — | — | RU |
| InventoryReporting | CRUD | CRU | CRUD | R | R | — |
| SalesReporting | CRUD | CRUD | R | R | R | — |
| InventoryForecasting | CRUD | CRUD | CRUD | R | R | — |
| LabelingOperations | CRUD | CRUD | R | CRUD | — | — |
| OperationsDashboard | CRUD | CRU | R | R | R | R |
| LegalAgreements | CRUD | CRUD | — | — | — | — |
| ProductCatalog | CRUD | CRUD | R | R | R | R |
| LinksIndex | CRUD | CRUD | — | — | — | — |
| Support | CRUD | CRUD | — | — | — | — |
| Accounting | CRUD | CRUD | — | — | R | — |
| UserAdmin | CRUD | R | — | — | — | — |
| RoleAdmin | CRUD | R | — | — | — | — |

*— = NULL (no access)*

Dedicated single-module roles also exist: **Legal User**, **Catalog User**, **Links User**, **Support**, **Accounting**, **PO Processor**, **T&E Approver**, **Email_Alerts**.

---

## Rationale by role

### Admin
Full `CRUD` on every module and admin area, including PO Approval and T&E Approval.

### Management User
- **CRUD** on day-to-day operations: PO Management, Inventory Forecasting, Labeling, Legal, Product Catalog, Links, Support, Accounting.
- **CRU** on Inventory Reporting and Operations Dashboard (view/create/update without delete on those surfaces).
- **CRUD** on Sales Reporting.
- **R** on Site Admin (view users and roles only).

### Inventory User
- **CRUD** on Inventory Reporting (Jazz, ACCS, reconciliation, Jazz Item Master, UAT inventory pages), and Inventory Forecasting.
- **R** on PO Management (PO Management, Receiving, Payments, Jazz ASNs, Delivery Schedule, Suppliers), Product Catalog, Sales Reporting, Labeling, and Operations Dashboard.
- No Legal, Support, Accounting, or Site Admin access.

### Labeling User
- **CRUD** on Labeling Operations.
- **R** on PO, inventory reporting, sales, forecasting, product catalog, and Operations Dashboard for reference.

### Reporting User
- **R** on Inventory Reporting, Sales Reporting, Inventory Forecasting, Product Catalog, Accounting (read-only QuickBooks views), and Operations Dashboard (Process Log, Site Documentation, System Performance Dashboard).
- No PO, Labeling, Legal, Support, or Site Admin access.

### PO Approver
- **R** on PO Management pages and Product Catalog (SKU context on PO lines).
- **RU** on PO Approval queue.
- **R** on Operations Dashboard for portal navigation.

---

## Module → permission mapping (Supply Chain hub)

| Page | Permission column |
|------|-------------------|
| PO Management, PO Receiving, PO Payments, Delivery Schedule Log, Supplier Management, Jazz ASNs | POManagement |
| ACCS Inventory, Jazz Current Inventory, Inventory Reconciliation, Inventory Forecasting, Jazz Item Master | InventoryReporting |
| UAT / stage inventory pages (`*-uat/`) | InventoryReporting (same column as production) |
| Product SKU Master | ProductCatalog |

---

## Related migrations

Run in order on a new environment:

1. `sql/007_seed_role_permissions.sql` — base operational roles
2. `sql/021_add_new_module_permissions.sql` — Legal, Product Catalog, Links
3. `sql/029_add_support_permission.sql` — Support
4. `sql/031_add_accounting_permission.sql` — Accounting
5. `sql/076_align_operational_role_permissions.sql` — full operational matrix (this file)

---

*Source script: `sql/076_align_operational_role_permissions.sql`*
