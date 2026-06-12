/*
  NutraAxis Operations — extend Role table with admin permission columns
*/

IF COL_LENGTH('dbo.Role', 'UserAdmin') IS NULL
    ALTER TABLE dbo.Role ADD UserAdmin NVARCHAR(10) NULL;
GO

IF COL_LENGTH('dbo.Role', 'RoleAdmin') IS NULL
    ALTER TABLE dbo.Role ADD RoleAdmin NVARCHAR(10) NULL;
GO
