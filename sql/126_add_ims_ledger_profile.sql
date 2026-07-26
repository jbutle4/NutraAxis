/*
  NutraAxis Operations — IMS ledger profile (production | uat row flag)
  Separates UAT/sandbox IMS ledger rows from production in the same nutraaxis database.
*/

IF COL_LENGTH('dbo.InvTransaction', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.InvTransaction ADD LedgerProfile NVARCHAR(20) NULL;
GO

IF COL_LENGTH('dbo.InvCurrentBalance', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.InvCurrentBalance ADD LedgerProfile NVARCHAR(20) NULL;
GO

IF COL_LENGTH('dbo.InvAdjustment', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.InvAdjustment ADD LedgerProfile NVARCHAR(20) NULL;
GO

IF COL_LENGTH('dbo.InvTransfer', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.InvTransfer ADD LedgerProfile NVARCHAR(20) NULL;
GO

IF COL_LENGTH('dbo.QBOInventorySyncLog', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.QBOInventorySyncLog ADD LedgerProfile NVARCHAR(20) NULL;
GO

IF OBJECT_ID(N'dbo.InventoryJazzImsAlignRun', N'U') IS NOT NULL
   AND COL_LENGTH('dbo.InventoryJazzImsAlignRun', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.InventoryJazzImsAlignRun ADD LedgerProfile NVARCHAR(20) NULL;
GO

IF OBJECT_ID(N'dbo.InventoryMovementReconRun', N'U') IS NOT NULL
   AND COL_LENGTH('dbo.InventoryMovementReconRun', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.InventoryMovementReconRun ADD LedgerProfile NVARCHAR(20) NULL;
GO

/* Backfill sandbox / pre-cutover IMS data to uat */
IF COL_LENGTH('dbo.InvTransaction', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.InvTransaction SET LedgerProfile = N'uat' WHERE LedgerProfile IS NULL;
GO

IF COL_LENGTH('dbo.InvCurrentBalance', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.InvCurrentBalance SET LedgerProfile = N'uat' WHERE LedgerProfile IS NULL;
GO

IF COL_LENGTH('dbo.InvAdjustment', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.InvAdjustment SET LedgerProfile = N'uat' WHERE LedgerProfile IS NULL;
GO

IF COL_LENGTH('dbo.InvTransfer', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.InvTransfer SET LedgerProfile = N'uat' WHERE LedgerProfile IS NULL;
GO

IF COL_LENGTH('dbo.QBOInventorySyncLog', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.QBOInventorySyncLog SET LedgerProfile = N'uat' WHERE LedgerProfile IS NULL;
GO

IF OBJECT_ID(N'dbo.InventoryJazzImsAlignRun', N'U') IS NOT NULL
   AND COL_LENGTH('dbo.InventoryJazzImsAlignRun', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.InventoryJazzImsAlignRun SET LedgerProfile = N'uat' WHERE LedgerProfile IS NULL;
GO

IF OBJECT_ID(N'dbo.InventoryMovementReconRun', N'U') IS NOT NULL
   AND COL_LENGTH('dbo.InventoryMovementReconRun', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.InventoryMovementReconRun SET LedgerProfile = N'uat' WHERE LedgerProfile IS NULL;
GO

IF COL_LENGTH('dbo.InvTransaction', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_InvTransaction_LedgerProfile'
   )
    ALTER TABLE dbo.InvTransaction
        ADD CONSTRAINT DF_InvTransaction_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF COL_LENGTH('dbo.InvCurrentBalance', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_InvCurrentBalance_LedgerProfile'
   )
    ALTER TABLE dbo.InvCurrentBalance
        ADD CONSTRAINT DF_InvCurrentBalance_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF COL_LENGTH('dbo.InvAdjustment', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_InvAdjustment_LedgerProfile'
   )
    ALTER TABLE dbo.InvAdjustment
        ADD CONSTRAINT DF_InvAdjustment_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF COL_LENGTH('dbo.InvTransfer', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_InvTransfer_LedgerProfile'
   )
    ALTER TABLE dbo.InvTransfer
        ADD CONSTRAINT DF_InvTransfer_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF COL_LENGTH('dbo.QBOInventorySyncLog', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_QBOInventorySyncLog_LedgerProfile'
   )
    ALTER TABLE dbo.QBOInventorySyncLog
        ADD CONSTRAINT DF_QBOInventorySyncLog_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF OBJECT_ID(N'dbo.InventoryJazzImsAlignRun', N'U') IS NOT NULL
   AND COL_LENGTH('dbo.InventoryJazzImsAlignRun', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_InventoryJazzImsAlignRun_LedgerProfile'
   )
    ALTER TABLE dbo.InventoryJazzImsAlignRun
        ADD CONSTRAINT DF_InventoryJazzImsAlignRun_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF OBJECT_ID(N'dbo.InventoryMovementReconRun', N'U') IS NOT NULL
   AND COL_LENGTH('dbo.InventoryMovementReconRun', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_InventoryMovementReconRun_LedgerProfile'
   )
    ALTER TABLE dbo.InventoryMovementReconRun
        ADD CONSTRAINT DF_InventoryMovementReconRun_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF COL_LENGTH('dbo.InvTransaction', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InvTransaction ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF COL_LENGTH('dbo.InvCurrentBalance', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InvCurrentBalance ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF COL_LENGTH('dbo.InvAdjustment', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InvAdjustment ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF COL_LENGTH('dbo.InvTransfer', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InvTransfer ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF COL_LENGTH('dbo.QBOInventorySyncLog', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.QBOInventorySyncLog ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF OBJECT_ID(N'dbo.InventoryJazzImsAlignRun', N'U') IS NOT NULL
   AND COL_LENGTH('dbo.InventoryJazzImsAlignRun', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InventoryJazzImsAlignRun ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF OBJECT_ID(N'dbo.InventoryMovementReconRun', N'U') IS NOT NULL
   AND COL_LENGTH('dbo.InventoryMovementReconRun', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InventoryMovementReconRun ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF OBJECT_ID(N'dbo.CK_InvTransaction_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.InvTransaction', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InvTransaction
        ADD CONSTRAINT CK_InvTransaction_LedgerProfile
        CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF OBJECT_ID(N'dbo.CK_InvCurrentBalance_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.InvCurrentBalance', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InvCurrentBalance
        ADD CONSTRAINT CK_InvCurrentBalance_LedgerProfile
        CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF OBJECT_ID(N'dbo.CK_InvAdjustment_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.InvAdjustment', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InvAdjustment
        ADD CONSTRAINT CK_InvAdjustment_LedgerProfile
        CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF OBJECT_ID(N'dbo.CK_InvTransfer_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.InvTransfer', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InvTransfer
        ADD CONSTRAINT CK_InvTransfer_LedgerProfile
        CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF OBJECT_ID(N'dbo.CK_QBOInventorySyncLog_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.QBOInventorySyncLog', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.QBOInventorySyncLog
        ADD CONSTRAINT CK_QBOInventorySyncLog_LedgerProfile
        CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF OBJECT_ID(N'dbo.CK_InventoryJazzImsAlignRun_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.InventoryJazzImsAlignRun', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InventoryJazzImsAlignRun
        ADD CONSTRAINT CK_InventoryJazzImsAlignRun_LedgerProfile
        CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF OBJECT_ID(N'dbo.CK_InventoryMovementReconRun_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.InventoryMovementReconRun', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.InventoryMovementReconRun
        ADD CONSTRAINT CK_InventoryMovementReconRun_LedgerProfile
        CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF COL_LENGTH('dbo.InvCurrentBalance', 'LedgerProfile') IS NOT NULL
   AND EXISTS (
       SELECT 1 FROM sys.key_constraints
       WHERE name = N'UQ_InvCurrentBalance_SKU_Facility'
         AND parent_object_id = OBJECT_ID(N'dbo.InvCurrentBalance')
   )
BEGIN
    ALTER TABLE dbo.InvCurrentBalance DROP CONSTRAINT UQ_InvCurrentBalance_SKU_Facility;
END;
GO

IF COL_LENGTH('dbo.InvCurrentBalance', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.key_constraints
       WHERE name = N'UQ_InvCurrentBalance_SKU_Facility_Profile'
         AND parent_object_id = OBJECT_ID(N'dbo.InvCurrentBalance')
   )
BEGIN
    ALTER TABLE dbo.InvCurrentBalance
        ADD CONSTRAINT UQ_InvCurrentBalance_SKU_Facility_Profile
        UNIQUE (SKUCode, FacilityCode, LedgerProfile);
END;
GO

IF COL_LENGTH('dbo.InvCurrentBalance', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.indexes
       WHERE name = N'IX_InvCurrentBalance_LedgerProfile'
         AND object_id = OBJECT_ID(N'dbo.InvCurrentBalance')
   )
    CREATE NONCLUSTERED INDEX IX_InvCurrentBalance_LedgerProfile
        ON dbo.InvCurrentBalance (LedgerProfile, FacilityCode, SKUCode);
GO

IF COL_LENGTH('dbo.InvAdjustment', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.indexes
       WHERE name = N'IX_InvAdjustment_LedgerProfile'
         AND object_id = OBJECT_ID(N'dbo.InvAdjustment')
   )
    CREATE NONCLUSTERED INDEX IX_InvAdjustment_LedgerProfile
        ON dbo.InvAdjustment (LedgerProfile, AdjStatus, AdjustmentID DESC);
GO

IF COL_LENGTH('dbo.InvTransfer', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.indexes
       WHERE name = N'IX_InvTransfer_LedgerProfile'
         AND object_id = OBJECT_ID(N'dbo.InvTransfer')
   )
    CREATE NONCLUSTERED INDEX IX_InvTransfer_LedgerProfile
        ON dbo.InvTransfer (LedgerProfile, TransferStatus, TransferID DESC);
GO
