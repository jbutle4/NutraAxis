/*
  NutraAxis Operations — NutraSeal-style PO fields and attachments
*/

IF COL_LENGTH('dbo.Supplier', 'Address') IS NULL
    ALTER TABLE dbo.Supplier ADD Address NVARCHAR(500) NULL;
GO

IF COL_LENGTH('dbo.PurchaseOrder', 'BuyerName') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD BuyerName NVARCHAR(200) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'BuyerAddress') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD BuyerAddress NVARCHAR(500) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'BuyerContactName') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD BuyerContactName NVARCHAR(200) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'BuyerContactEmail') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD BuyerContactEmail NVARCHAR(200) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'BuyerContactPhone') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD BuyerContactPhone NVARCHAR(50) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'SupplierAddress') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD SupplierAddress NVARCHAR(500) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'PaymentTerms') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD PaymentTerms NVARCHAR(100) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'DeliveryTerms') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD DeliveryTerms NVARCHAR(200) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'ReferenceDocuments') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD ReferenceDocuments NVARCHAR(MAX) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'ShippingHandling') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD ShippingHandling DECIMAL(18,2) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'TotalDue') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD TotalDue DECIMAL(18,2) NULL;
GO
IF COL_LENGTH('dbo.PurchaseOrder', 'SpecialInstructions') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD SpecialInstructions NVARCHAR(MAX) NULL;
GO

IF COL_LENGTH('dbo.POLineItem', 'QuoteNumber') IS NULL
    ALTER TABLE dbo.POLineItem ADD QuoteNumber NVARCHAR(50) NULL;
GO
IF COL_LENGTH('dbo.POLineItem', 'ExpirationDate') IS NULL
    ALTER TABLE dbo.POLineItem ADD ExpirationDate DATE NULL;
GO

IF OBJECT_ID(N'dbo.POAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.POAttachment (
        AttachmentID    INT             NOT NULL IDENTITY(1,1),
        POID            INT             NOT NULL,
        FileName        NVARCHAR(255)   NOT NULL,
        ContentType     NVARCHAR(100)   NOT NULL,
        FileSizeBytes   INT             NOT NULL,
        FileData        VARBINARY(MAX)  NOT NULL,
        AttachmentKind  NVARCHAR(30)    NOT NULL CONSTRAINT DF_POAttachment_Kind DEFAULT (N'SourcePDF'),
        UploadedByUser  INT             NOT NULL,
        UploadDate      DATETIME2(0)    NOT NULL CONSTRAINT DF_POAttachment_UploadDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_POAttachment PRIMARY KEY CLUSTERED (AttachmentID),
        CONSTRAINT CK_POAttachment_Kind CHECK (
            AttachmentKind IN (N'SourcePDF', N'SignedPDF', N'ImportExcel', N'ImportCSV', N'Other')
        ),
        CONSTRAINT FK_POAttachment_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID) ON DELETE CASCADE,
        CONSTRAINT FK_POAttachment_UploadedByUser FOREIGN KEY (UploadedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_POAttachment_POID
        ON dbo.POAttachment (POID);
END;
GO
