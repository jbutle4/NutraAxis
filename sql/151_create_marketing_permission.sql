/*
  NutraAxis Operations — Marketing hub role permission (SEO Ops Track C)
*/

IF COL_LENGTH(N'dbo.Role', N'Marketing') IS NULL
    ALTER TABLE dbo.Role ADD Marketing NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_Marketing_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_Marketing_CRUD
    CHECK (Marketing IS NULL OR Marketing IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

UPDATE dbo.Role
SET Marketing = N'CRUD'
WHERE RoleName = N'Admin';
GO
