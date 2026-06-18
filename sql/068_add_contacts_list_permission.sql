/*
  NutraAxis Operations — Contacts List module permission
*/

IF COL_LENGTH('dbo.Role', 'ContactsList') IS NULL
    ALTER TABLE dbo.Role ADD ContactsList NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_ContactsList_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_ContactsList_CRUD
    CHECK (ContactsList IS NULL OR ContactsList IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

UPDATE dbo.Role
SET ContactsList = N'CRUD'
WHERE RoleName = N'Admin';
GO
