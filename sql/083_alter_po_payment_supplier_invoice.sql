/*
  NutraAxis Operations — POPayment: pay POs or supplier invoices without a PO
*/

IF COL_LENGTH('dbo.POPayment', 'SupplierInvoiceID') IS NULL
BEGIN
    ALTER TABLE dbo.POPayment ADD SupplierInvoiceID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.FK_POPayment_SupplierInvoice', N'F') IS NULL
BEGIN
    ALTER TABLE dbo.POPayment
        ADD CONSTRAINT FK_POPayment_SupplierInvoice FOREIGN KEY (SupplierInvoiceID)
            REFERENCES dbo.SupplierInvoice (SupplierInvoiceID);
END;
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns c
    INNER JOIN sys.tables t ON t.object_id = c.object_id
    WHERE t.name = N'POPayment'
      AND c.name = N'POID'
      AND c.is_nullable = 0
)
BEGIN
    ALTER TABLE dbo.POPayment ALTER COLUMN POID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.CK_POPayment_Source', N'C') IS NULL
BEGIN
    ALTER TABLE dbo.POPayment
        ADD CONSTRAINT CK_POPayment_Source CHECK (
            POID IS NOT NULL OR SupplierInvoiceID IS NOT NULL
        );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_POPayment_SupplierInvoiceID'
      AND object_id = OBJECT_ID(N'dbo.POPayment')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_POPayment_SupplierInvoiceID
        ON dbo.POPayment (SupplierInvoiceID)
        WHERE SupplierInvoiceID IS NOT NULL;
END;
GO

IF COL_LENGTH('dbo.POPaymentAttachment', 'SupplierInvoiceID') IS NULL
BEGIN
    ALTER TABLE dbo.POPaymentAttachment ADD SupplierInvoiceID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.FK_POPaymentAttachment_SupplierInvoice', N'F') IS NULL
BEGIN
    ALTER TABLE dbo.POPaymentAttachment
        ADD CONSTRAINT FK_POPaymentAttachment_SupplierInvoice FOREIGN KEY (SupplierInvoiceID)
            REFERENCES dbo.SupplierInvoice (SupplierInvoiceID);
END;
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns c
    INNER JOIN sys.tables t ON t.object_id = c.object_id
    WHERE t.name = N'POPaymentAttachment'
      AND c.name = N'POID'
      AND c.is_nullable = 0
)
BEGIN
    ALTER TABLE dbo.POPaymentAttachment ALTER COLUMN POID INT NULL;
END;
GO
