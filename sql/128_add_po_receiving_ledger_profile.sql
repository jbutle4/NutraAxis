/*
  NutraAxis Operations — PO Receiving / DAS ledger profile (production | uat)
  Separates UAT test receipts and appointments from production in the same database.
  Same pattern as sql/126_add_ims_ledger_profile.sql.
*/

IF COL_LENGTH('dbo.POReceipt', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.POReceipt ADD LedgerProfile NVARCHAR(20) NULL;
GO

IF COL_LENGTH('dbo.DeliveryAppointmentScheduling', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.DeliveryAppointmentScheduling ADD LedgerProfile NVARCHAR(20) NULL;
GO

/* Existing live rows were created while Jazz wiring hit UAT — backfill to uat */
IF COL_LENGTH('dbo.POReceipt', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.POReceipt SET LedgerProfile = N'uat' WHERE LedgerProfile IS NULL;
GO

IF COL_LENGTH('dbo.DeliveryAppointmentScheduling', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.DeliveryAppointmentScheduling SET LedgerProfile = N'uat' WHERE LedgerProfile IS NULL;
GO

IF COL_LENGTH('dbo.POReceipt', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_POReceipt_LedgerProfile'
   )
    ALTER TABLE dbo.POReceipt
        ADD CONSTRAINT DF_POReceipt_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF COL_LENGTH('dbo.DeliveryAppointmentScheduling', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_DeliveryAppointmentScheduling_LedgerProfile'
   )
    ALTER TABLE dbo.DeliveryAppointmentScheduling
        ADD CONSTRAINT DF_DeliveryAppointmentScheduling_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF COL_LENGTH('dbo.POReceipt', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.POReceipt ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF COL_LENGTH('dbo.DeliveryAppointmentScheduling', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.DeliveryAppointmentScheduling ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF OBJECT_ID(N'dbo.CK_POReceipt_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.POReceipt', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.POReceipt
        ADD CONSTRAINT CK_POReceipt_LedgerProfile CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF OBJECT_ID(N'dbo.CK_DeliveryAppointmentScheduling_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.DeliveryAppointmentScheduling', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.DeliveryAppointmentScheduling
        ADD CONSTRAINT CK_DeliveryAppointmentScheduling_LedgerProfile CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_POReceipt_LedgerProfile'
      AND object_id = OBJECT_ID(N'dbo.POReceipt')
)
    CREATE NONCLUSTERED INDEX IX_POReceipt_LedgerProfile
        ON dbo.POReceipt (LedgerProfile, PORID DESC);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_DeliveryAppointmentScheduling_LedgerProfile'
      AND object_id = OBJECT_ID(N'dbo.DeliveryAppointmentScheduling')
)
    CREATE NONCLUSTERED INDEX IX_DeliveryAppointmentScheduling_LedgerProfile
        ON dbo.DeliveryAppointmentScheduling (LedgerProfile, ApptID DESC);
GO
