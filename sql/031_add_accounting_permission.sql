/*
  NutraAxis Operations — Accounting module permission
*/

IF COL_LENGTH('dbo.Role', 'Accounting') IS NULL
    ALTER TABLE dbo.Role ADD Accounting NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_Accounting_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_Accounting_CRUD
    CHECK (Accounting IS NULL OR Accounting IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

UPDATE dbo.Role
SET Accounting = N'CRUD'
WHERE RoleName = N'Admin';
GO
