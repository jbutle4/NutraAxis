/*
  NutraAxis Operations — QuickBooks Online Item fields on dbo.SKUMaster
*/

IF COL_LENGTH('dbo.SKUMaster', 'QBO_ItemID') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_ItemID NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_SyncToken') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_SyncToken NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_DisplayName') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_DisplayName NVARCHAR(100) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_SyncedAt') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_SyncedAt DATETIME2(0) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_SyncStatus') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_SyncStatus NVARCHAR(30) NOT NULL
        CONSTRAINT DF_SKUMaster_QBO_SyncStatus DEFAULT (N'NotSynced');

IF COL_LENGTH('dbo.SKUMaster', 'QBO_SyncError') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_SyncError NVARCHAR(500) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_PurchaseDesc') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_PurchaseDesc NVARCHAR(4000) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_Taxable') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_Taxable BIT NOT NULL
        CONSTRAINT DF_SKUMaster_QBO_Taxable DEFAULT (1);

IF COL_LENGTH('dbo.SKUMaster', 'QBO_IncomeAccountRefValue') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_IncomeAccountRefValue NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_IncomeAccountRefName') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_IncomeAccountRefName NVARCHAR(255) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_ExpenseAccountRefValue') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_ExpenseAccountRefValue NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_ExpenseAccountRefName') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_ExpenseAccountRefName NVARCHAR(255) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_AssetAccountRefValue') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_AssetAccountRefValue NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_AssetAccountRefName') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_AssetAccountRefName NVARCHAR(255) NULL;
GO

IF OBJECT_ID(N'dbo.CK_SKUMaster_QBO_SyncStatus', N'C') IS NULL
BEGIN
    ALTER TABLE dbo.SKUMaster
        ADD CONSTRAINT CK_SKUMaster_QBO_SyncStatus CHECK (
            QBO_SyncStatus IN (N'NotSynced', N'Synced', N'Error', N'Pending')
        );
END;
GO
