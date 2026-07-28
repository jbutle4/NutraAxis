/*
  NutraAxis Operations — PurchaseOrder ledger profile (production | uat)
  Segregates UAT test POs from production in the same database.
*/

IF COL_LENGTH('dbo.PurchaseOrder', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD LedgerProfile NVARCHAR(20) NULL;
GO

IF COL_LENGTH('dbo.PurchaseOrder', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.PurchaseOrder
    SET LedgerProfile = CASE
        WHEN PONumber LIKE N'%-UAT' OR PONumber LIKE N'%-uat' THEN N'uat'
        ELSE N'production'
    END
    WHERE LedgerProfile IS NULL;
GO

IF COL_LENGTH('dbo.PurchaseOrder', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_PurchaseOrder_LedgerProfile'
   )
    ALTER TABLE dbo.PurchaseOrder
        ADD CONSTRAINT DF_PurchaseOrder_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF COL_LENGTH('dbo.PurchaseOrder', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.PurchaseOrder ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF OBJECT_ID(N'dbo.CK_PurchaseOrder_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.PurchaseOrder', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.PurchaseOrder
        ADD CONSTRAINT CK_PurchaseOrder_LedgerProfile CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF EXISTS (
    SELECT 1 FROM sys.key_constraints
    WHERE name = N'UQ_PurchaseOrder_PONumber'
      AND parent_object_id = OBJECT_ID(N'dbo.PurchaseOrder')
)
    ALTER TABLE dbo.PurchaseOrder DROP CONSTRAINT UQ_PurchaseOrder_PONumber;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.key_constraints
    WHERE name = N'UQ_PurchaseOrder_PONumber_LedgerProfile'
      AND parent_object_id = OBJECT_ID(N'dbo.PurchaseOrder')
)
    ALTER TABLE dbo.PurchaseOrder
        ADD CONSTRAINT UQ_PurchaseOrder_PONumber_LedgerProfile UNIQUE (PONumber, LedgerProfile);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_PurchaseOrder_LedgerProfile'
      AND object_id = OBJECT_ID(N'dbo.PurchaseOrder')
)
    CREATE NONCLUSTERED INDEX IX_PurchaseOrder_LedgerProfile
        ON dbo.PurchaseOrder (LedgerProfile, OrderDate DESC, POID DESC);
GO
