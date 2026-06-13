/*
  NutraAxis Operations — Support module permission
*/

IF COL_LENGTH('dbo.Role', 'Support') IS NULL
    ALTER TABLE dbo.Role ADD Support NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_Support_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_Support_CRUD
    CHECK (Support IS NULL OR Support IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

UPDATE dbo.Role
SET Support = N'CRUD'
WHERE RoleName = N'Admin';
GO
