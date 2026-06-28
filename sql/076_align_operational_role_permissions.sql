/*
  NutraAxis Operations — align operational role permissions with current modules

  Covers Supply Chain sub-modules (PO Payments, Jazz Item Master, UAT inventory pages),
  Product Catalog, Accounting, Support, Legal, Links, and Operations Dashboard utilities
  (Process Log, Site Documentation, System Performance Dashboard).

  Encoding: C=Create, R=Read, U=Update, D=Delete
  NULL = no access

  Apply:
    node scripts/run-sql-file.js sql/076_align_operational_role_permissions.sql
*/

-- ---------------------------------------------------------------------------
-- Management User — full operational access; read/update on admin areas
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = N'CRUD',
    InventoryReporting     = N'CRU',
    SalesReporting         = N'CRUD',
    InventoryForecasting   = N'CRUD',
    LabelingOperations     = N'CRUD',
    OperationsDashboard    = N'CRU',
    LegalAgreements        = N'CRUD',
    ProductCatalog         = N'CRUD',
    LinksIndex             = N'CRUD',
    Support                = N'CRUD',
    Accounting             = N'CRUD',
    UserAdmin              = N'R',
    RoleAdmin              = N'R',
    ModifiedbyUser         = 1
WHERE RoleName = N'Management User';
GO

-- ---------------------------------------------------------------------------
-- Inventory User — CRUD inventory modules; read PO, catalog, sales, dashboard
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = N'R',
    InventoryReporting     = N'CRUD',
    SalesReporting         = N'R',
    InventoryForecasting   = N'CRUD',
    LabelingOperations     = N'R',
    OperationsDashboard    = N'R',
    ProductCatalog         = N'R',
    UserAdmin              = NULL,
    RoleAdmin              = NULL,
    ModifiedbyUser         = 1
WHERE RoleName = N'Inventory User';
GO

-- ---------------------------------------------------------------------------
-- Labeling User — CRUD labeling; read reference modules
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = N'R',
    InventoryReporting     = N'R',
    SalesReporting         = N'R',
    InventoryForecasting   = N'R',
    LabelingOperations     = N'CRUD',
    OperationsDashboard    = N'R',
    ProductCatalog         = N'R',
    UserAdmin              = NULL,
    RoleAdmin              = NULL,
    ModifiedbyUser         = 1
WHERE RoleName = N'Labeling User';
GO

-- ---------------------------------------------------------------------------
-- Reporting User — read-only reporting, dashboard, catalog, and accounting
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = NULL,
    InventoryReporting     = N'R',
    SalesReporting         = N'R',
    InventoryForecasting   = N'R',
    LabelingOperations     = NULL,
    OperationsDashboard    = N'R',
    ProductCatalog         = N'R',
    Accounting             = N'R',
    UserAdmin              = NULL,
    RoleAdmin              = NULL,
    ModifiedbyUser         = 1
WHERE RoleName = N'Reporting User';
GO

-- ---------------------------------------------------------------------------
-- PO Approver — PO view + approval queue; portal hub read for navigation
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = N'R',
    POApproval             = N'RU',
    OperationsDashboard    = N'R',
    ProductCatalog         = N'R',
    ModifiedbyUser         = 1
WHERE RoleName = N'PO Approver';
GO

-- ---------------------------------------------------------------------------
-- PO Processor — PO read + T&E payment processing context
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = N'R',
    OperationsDashboard    = N'R',
    ProductCatalog         = N'R',
    TEManagement           = N'R',
    ModifiedbyUser         = 1
WHERE RoleName = N'PO Processor';
GO

-- ---------------------------------------------------------------------------
-- T&E Approver — expense review; PO/catalog read for context
-- ---------------------------------------------------------------------------
UPDATE dbo.Role
SET
    POManagement           = N'R',
    OperationsDashboard    = N'R',
    ProductCatalog         = N'R',
    TEManagement           = N'R',
    TEApproval             = N'RU',
    ModifiedbyUser         = 1
WHERE RoleName = N'T&E Approver';
GO
