/*
  NutraAxis Operations — Legal Agreements, Product Catalog, Links Index modules
*/

IF COL_LENGTH('dbo.Role', 'LegalAgreements') IS NULL
    ALTER TABLE dbo.Role ADD LegalAgreements NVARCHAR(10) NULL;

IF COL_LENGTH('dbo.Role', 'ProductCatalog') IS NULL
    ALTER TABLE dbo.Role ADD ProductCatalog NVARCHAR(10) NULL;

IF COL_LENGTH('dbo.Role', 'LinksIndex') IS NULL
    ALTER TABLE dbo.Role ADD LinksIndex NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_LegalAgreements_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_LegalAgreements_CRUD
    CHECK (LegalAgreements IS NULL OR LegalAgreements IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

IF OBJECT_ID(N'dbo.CK_Role_ProductCatalog_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_ProductCatalog_CRUD
    CHECK (ProductCatalog IS NULL OR ProductCatalog IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

IF OBJECT_ID(N'dbo.CK_Role_LinksIndex_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_LinksIndex_CRUD
    CHECK (LinksIndex IS NULL OR LinksIndex IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

UPDATE dbo.Role
SET
    LegalAgreements = N'CRUD',
    ProductCatalog  = N'CRUD',
    LinksIndex      = N'CRUD'
WHERE RoleName = N'Admin';
GO
