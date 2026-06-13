/*
  NutraAxis Operations — seed CRUD permissions by role (DRAFT — review before running)

  Encoding: C=Create, R=Read, U=Update, D=Delete (canonical order)
  NULL = no access

  Run only after review:
    node scripts/run-sql-file.js sql/007_seed_role_permissions.sql
*/

-- ---------------------------------------------------------------------------
-- Admin — full access to all modules and administration
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = N'CRUD',
    InventoryReporting     = N'CRUD',
    SalesReporting         = N'CRUD',
    InventoryForecasting   = N'CRUD',
    LabelingOperations     = N'CRUD',
    OperationsDashboard    = N'CRUD',
    UserAdmin              = N'CRUD',
    RoleAdmin              = N'CRUD'
WHERE RoleName = N'Admin';
GO

-- ---------------------------------------------------------------------------
-- Management User — operate core workflows; read-only on reporting modules
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = N'CRUD',
    InventoryReporting     = N'R',
    SalesReporting         = N'R',
    InventoryForecasting   = N'CRUD',
    LabelingOperations     = N'CRUD',
    OperationsDashboard    = N'R',
    UserAdmin              = N'R',
    RoleAdmin              = N'R'
WHERE RoleName = N'Management User';
GO

-- ---------------------------------------------------------------------------
-- Inventory User — full inventory modules; read-only elsewhere
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = N'R',
    InventoryReporting     = N'CRUD',
    SalesReporting         = N'R',
    InventoryForecasting   = N'CRUD',
    LabelingOperations     = N'R',
    OperationsDashboard    = N'R',
    UserAdmin              = NULL,
    RoleAdmin              = NULL
WHERE RoleName = N'Inventory User';
GO

-- ---------------------------------------------------------------------------
-- Labeling User — full labeling module; read-only elsewhere
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = N'R',
    InventoryReporting     = N'R',
    SalesReporting         = N'R',
    InventoryForecasting   = N'R',
    LabelingOperations     = N'CRUD',
    OperationsDashboard    = N'R',
    UserAdmin              = NULL,
    RoleAdmin              = NULL
WHERE RoleName = N'Labeling User';
GO

-- ---------------------------------------------------------------------------
-- Reporting User — read-only on reporting and dashboard modules only
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = NULL,
    InventoryReporting     = N'R',
    SalesReporting         = N'R',
    InventoryForecasting   = NULL,
    LabelingOperations     = NULL,
    OperationsDashboard    = N'R',
    UserAdmin              = NULL,
    RoleAdmin              = NULL
WHERE RoleName = N'Reporting User';
GO
