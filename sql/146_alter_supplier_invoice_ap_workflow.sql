/*
  Supplier invoice AP workflow: Paid/Closed statuses and ASN match fields.
*/

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = N'CK_SupplierInvoice_SyncStatus')
    ALTER TABLE dbo.SupplierInvoice DROP CONSTRAINT CK_SupplierInvoice_SyncStatus;
GO

ALTER TABLE dbo.SupplierInvoice
    ADD CONSTRAINT CK_SupplierInvoice_SyncStatus CHECK (
        SyncStatus IN (
            N'Draft',
            N'Submitted for Approval',
            N'Sent Back for Comment',
            N'Rejected',
            N'Posted',
            N'Failed',
            N'Voided',
            N'Paid',
            N'Closed'
        )
    );
GO

IF COL_LENGTH('dbo.SupplierInvoice', 'AsnStatus') IS NULL
BEGIN
    ALTER TABLE dbo.SupplierInvoice ADD AsnStatus NVARCHAR(20) NULL
        CONSTRAINT DF_SupplierInvoice_AsnStatus DEFAULT (N'Unmatched');
END;
GO

IF COL_LENGTH('dbo.SupplierInvoice', 'PORID') IS NULL
BEGIN
    ALTER TABLE dbo.SupplierInvoice ADD PORID INT NULL;
END;
GO

IF COL_LENGTH('dbo.SupplierInvoice', 'JazzASN') IS NULL
BEGIN
    ALTER TABLE dbo.SupplierInvoice ADD JazzASN NVARCHAR(50) NULL;
END;
GO

IF OBJECT_ID(N'dbo.FK_SupplierInvoice_POReceipt', N'F') IS NULL
   AND OBJECT_ID(N'dbo.POReceipt', N'U') IS NOT NULL
   AND COL_LENGTH('dbo.SupplierInvoice', 'PORID') IS NOT NULL
BEGIN
    ALTER TABLE dbo.SupplierInvoice
        ADD CONSTRAINT FK_SupplierInvoice_POReceipt FOREIGN KEY (PORID)
            REFERENCES dbo.POReceipt (PORID);
END;
GO

UPDATE dbo.SupplierInvoice
SET AsnStatus = N'Unmatched'
WHERE AsnStatus IS NULL;
GO
