/*
  NutraAxis Operations — QuickBooks Online linkage on PurchaseOrder and Supplier
*/

IF COL_LENGTH('dbo.PurchaseOrder', 'QBO_POID') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD QBO_POID NVARCHAR(32) NULL;
GO

IF COL_LENGTH('dbo.PurchaseOrder', 'POQBOCreated') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD POQBOCreated BIT NOT NULL
        CONSTRAINT DF_PurchaseOrder_POQBOCreated DEFAULT (0);
GO

IF COL_LENGTH('dbo.Supplier', 'QBO_SupplierID') IS NULL
    ALTER TABLE dbo.Supplier ADD QBO_SupplierID NVARCHAR(32) NULL;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_PurchaseOrder_QBO_POID'
      AND object_id = OBJECT_ID(N'dbo.PurchaseOrder')
)
    CREATE NONCLUSTERED INDEX IX_PurchaseOrder_QBO_POID
        ON dbo.PurchaseOrder (QBO_POID)
        WHERE QBO_POID IS NOT NULL;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_Supplier_QBO_SupplierID'
      AND object_id = OBJECT_ID(N'dbo.Supplier')
)
    CREATE NONCLUSTERED INDEX IX_Supplier_QBO_SupplierID
        ON dbo.Supplier (QBO_SupplierID)
        WHERE QBO_SupplierID IS NOT NULL;
GO
