/*
  NutraAxis Operations — extend Role table with module permission columns
*/

IF COL_LENGTH('dbo.Role', 'POManagement') IS NULL
    ALTER TABLE dbo.Role ADD POManagement NVARCHAR(10) NULL;
GO

IF COL_LENGTH('dbo.Role', 'InventoryReporting') IS NULL
    ALTER TABLE dbo.Role ADD InventoryReporting NVARCHAR(10) NULL;
GO

IF COL_LENGTH('dbo.Role', 'SalesReporting') IS NULL
    ALTER TABLE dbo.Role ADD SalesReporting NVARCHAR(10) NULL;
GO

IF COL_LENGTH('dbo.Role', 'InventoryForecasting') IS NULL
    ALTER TABLE dbo.Role ADD InventoryForecasting NVARCHAR(10) NULL;
GO

IF COL_LENGTH('dbo.Role', 'LabelingOperations') IS NULL
    ALTER TABLE dbo.Role ADD LabelingOperations NVARCHAR(10) NULL;
GO

IF COL_LENGTH('dbo.Role', 'OperationsDashboard') IS NULL
    ALTER TABLE dbo.Role ADD OperationsDashboard NVARCHAR(10) NULL;
GO
