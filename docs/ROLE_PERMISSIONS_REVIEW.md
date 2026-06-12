# Role Permissions — Review Draft

**Status:** Applied to Azure SQL database.

**Apply when ready:**
```bash
node scripts/run-sql-file.js sql/007_seed_role_permissions.sql
```

---

## Permission matrix

| Permission column | Admin | Management User | Inventory User | Labeling User | Reporting User |
|-------------------|:-----:|:---------------:|:--------------:|:-------------:|:--------------:|
| POManagement | CRUD | CRUD | R | R | — |
| InventoryReporting | CRUD | R | CRUD | R | R |
| SalesReporting | CRUD | R | R | R | R |
| InventoryForecasting | CRUD | CRUD | CRUD | R | — |
| LabelingOperations | CRUD | CRUD | R | CRUD | — |
| OperationsDashboard | CRUD | R | R | R | R |
| UserAdmin | CRUD | R | — | — | — |
| RoleAdmin | CRUD | R | — | — | — |

*— = NULL (no access)*

---

## Rationale by role

### Admin
Full `CRUD` on every module and admin area. Only role that can manage users and roles.

### Management User
- **CRUD** on day-to-day operations: PO Management, Inventory Forecasting, Labeling Operations.
- **R** on reporting surfaces: Inventory Reporting, Sales Reporting, Operations Dashboard.
- **R** on Site Admin areas (view users and roles; no create/update/delete).

### Inventory User
- **CRUD** on inventory-focused modules: Inventory Reporting, Inventory Forecasting.
- **R** on related areas (PO, Sales, Labeling, Dashboard) for context without edit rights.
- No Site Admin access.

### Labeling User
- **CRUD** on Labeling Operations only.
- **R** elsewhere for reference.
- No Site Admin access.

### Reporting User
- **R** on Sales Reporting, Inventory Reporting, Operations Dashboard.
- No access to operational modules (PO, Forecasting, Labeling) or Site Admin.

---

## Adjust before applying

Edit `sql/007_seed_role_permissions.sql` if you want to change any cell, then run the script. Common adjustments:

- Give Management User `RU` instead of `R` on reporting modules (view + edit reports).
- Restrict Inventory User PO access from `R` to `NULL`.
- Grant Reporting User read on Inventory Forecasting.

---

*Source script: `sql/007_seed_role_permissions.sql`*
