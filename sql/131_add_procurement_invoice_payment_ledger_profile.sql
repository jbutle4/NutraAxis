/*
  NutraAxis Operations — SupplierInvoice / POPayment ledger profile (production | uat)
*/

IF COL_LENGTH('dbo.SupplierInvoice', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.SupplierInvoice ADD LedgerProfile NVARCHAR(20) NULL;
GO

IF COL_LENGTH('dbo.POPayment', 'LedgerProfile') IS NULL
    ALTER TABLE dbo.POPayment ADD LedgerProfile NVARCHAR(20) NULL;
GO

/* Inherit from linked purchase order when present */
IF COL_LENGTH('dbo.SupplierInvoice', 'LedgerProfile') IS NOT NULL
    UPDATE si
    SET LedgerProfile = po.LedgerProfile
    FROM dbo.SupplierInvoice si
    INNER JOIN dbo.PurchaseOrder po ON po.POID = si.POID
    WHERE si.LedgerProfile IS NULL
      AND COL_LENGTH('dbo.PurchaseOrder', 'LedgerProfile') IS NOT NULL;
GO

IF COL_LENGTH('dbo.SupplierInvoice', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.SupplierInvoice
    SET LedgerProfile = N'production'
    WHERE LedgerProfile IS NULL;
GO

IF COL_LENGTH('dbo.POPayment', 'LedgerProfile') IS NOT NULL
    UPDATE p
    SET LedgerProfile = po.LedgerProfile
    FROM dbo.POPayment p
    INNER JOIN dbo.PurchaseOrder po ON po.POID = p.POID
    WHERE p.LedgerProfile IS NULL
      AND COL_LENGTH('dbo.PurchaseOrder', 'LedgerProfile') IS NOT NULL;
GO

IF COL_LENGTH('dbo.POPayment', 'LedgerProfile') IS NOT NULL
    UPDATE p
    SET LedgerProfile = si.LedgerProfile
    FROM dbo.POPayment p
    INNER JOIN dbo.SupplierInvoice si ON si.SupplierInvoiceID = p.SupplierInvoiceID
    WHERE p.LedgerProfile IS NULL
      AND COL_LENGTH('dbo.SupplierInvoice', 'LedgerProfile') IS NOT NULL;
GO

IF COL_LENGTH('dbo.POPayment', 'LedgerProfile') IS NOT NULL
    UPDATE dbo.POPayment
    SET LedgerProfile = N'production'
    WHERE LedgerProfile IS NULL;
GO

IF COL_LENGTH('dbo.SupplierInvoice', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_SupplierInvoice_LedgerProfile'
   )
    ALTER TABLE dbo.SupplierInvoice
        ADD CONSTRAINT DF_SupplierInvoice_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF COL_LENGTH('dbo.POPayment', 'LedgerProfile') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.default_constraints
       WHERE name = N'DF_POPayment_LedgerProfile'
   )
    ALTER TABLE dbo.POPayment
        ADD CONSTRAINT DF_POPayment_LedgerProfile DEFAULT (N'production') FOR LedgerProfile;
GO

IF COL_LENGTH('dbo.SupplierInvoice', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.SupplierInvoice ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF COL_LENGTH('dbo.POPayment', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.POPayment ALTER COLUMN LedgerProfile NVARCHAR(20) NOT NULL;
GO

IF OBJECT_ID(N'dbo.CK_SupplierInvoice_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.SupplierInvoice', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.SupplierInvoice
        ADD CONSTRAINT CK_SupplierInvoice_LedgerProfile CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF OBJECT_ID(N'dbo.CK_POPayment_LedgerProfile', N'C') IS NULL
   AND COL_LENGTH('dbo.POPayment', 'LedgerProfile') IS NOT NULL
    ALTER TABLE dbo.POPayment
        ADD CONSTRAINT CK_POPayment_LedgerProfile CHECK (LedgerProfile IN (N'production', N'uat'));
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_SupplierInvoice_LedgerProfile'
      AND object_id = OBJECT_ID(N'dbo.SupplierInvoice')
)
    CREATE NONCLUSTERED INDEX IX_SupplierInvoice_LedgerProfile
        ON dbo.SupplierInvoice (LedgerProfile, TxnDate DESC, SupplierInvoiceID DESC);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_POPayment_LedgerProfile'
      AND object_id = OBJECT_ID(N'dbo.POPayment')
)
    CREATE NONCLUSTERED INDEX IX_POPayment_LedgerProfile
        ON dbo.POPayment (LedgerProfile, PaymentDate DESC, PaymentID DESC);
GO
