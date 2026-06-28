/*
  NutraAxis Operations — Supplier invoice file attachments
*/

IF OBJECT_ID(N'dbo.SupplierInvoiceAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SupplierInvoiceAttachment (
        AttachmentID        INT             NOT NULL IDENTITY(1,1),
        SupplierInvoiceID   INT             NOT NULL,
        FileName            NVARCHAR(255)   NOT NULL,
        ContentType         NVARCHAR(100)   NOT NULL,
        FileSizeBytes       INT             NOT NULL,
        FileData            VARBINARY(MAX)  NOT NULL,
        AttachmentKind      NVARCHAR(30)    NOT NULL CONSTRAINT DF_SupplierInvoiceAttachment_Kind DEFAULT (N'InvoicePDF'),
        UploadedByUser      INT             NOT NULL,
        UploadDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_SupplierInvoiceAttachment_UploadDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_SupplierInvoiceAttachment PRIMARY KEY CLUSTERED (AttachmentID),
        CONSTRAINT CK_SupplierInvoiceAttachment_FileSizeBytes CHECK (FileSizeBytes >= 0),
        CONSTRAINT CK_SupplierInvoiceAttachment_Kind CHECK (
            AttachmentKind IN (N'InvoicePDF', N'Receipt', N'Supporting', N'Other')
        ),
        CONSTRAINT FK_SupplierInvoiceAttachment_SupplierInvoice FOREIGN KEY (SupplierInvoiceID)
            REFERENCES dbo.SupplierInvoice (SupplierInvoiceID) ON DELETE CASCADE,
        CONSTRAINT FK_SupplierInvoiceAttachment_UploadedByUser FOREIGN KEY (UploadedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_SupplierInvoiceAttachment_SupplierInvoiceID
        ON dbo.SupplierInvoiceAttachment (SupplierInvoiceID, UploadDate DESC);
END;
GO
