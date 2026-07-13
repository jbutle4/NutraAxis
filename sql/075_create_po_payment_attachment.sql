/*
  NutraAxis Operations — PO payment file attachments
*/

IF OBJECT_ID(N'dbo.POPaymentAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.POPaymentAttachment (
        POPaymentAttachmentID INT             NOT NULL IDENTITY(1,1),
        POID                  INT             NOT NULL,
        PaymentID               INT             NOT NULL,
        FileName                NVARCHAR(255)   NOT NULL,
        ContentType             NVARCHAR(100)   NOT NULL,
        FileSizeBytes           INT             NOT NULL,
        FileData                VARBINARY(MAX)  NOT NULL,
        AttachmentKind          NVARCHAR(50)    NOT NULL CONSTRAINT DF_POPaymentAttachment_Kind DEFAULT (N'Other'),
        UploadedByUser          INT             NOT NULL,
        UploadDate              DATETIME2(0)    NOT NULL CONSTRAINT DF_POPaymentAttachment_UploadDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_POPaymentAttachment PRIMARY KEY CLUSTERED (POPaymentAttachmentID),
        CONSTRAINT CK_POPaymentAttachment_FileSizeBytes CHECK (FileSizeBytes >= 0),
        CONSTRAINT FK_POPaymentAttachment_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID),
        CONSTRAINT FK_POPaymentAttachment_Payment FOREIGN KEY (PaymentID)
            REFERENCES dbo.POPayment (PaymentID) ON DELETE CASCADE,
        CONSTRAINT FK_POPaymentAttachment_UploadedByUser FOREIGN KEY (UploadedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_POPaymentAttachment_POID
        ON dbo.POPaymentAttachment (POID);

    CREATE NONCLUSTERED INDEX IX_POPaymentAttachment_PaymentID
        ON dbo.POPaymentAttachment (PaymentID, UploadDate DESC);
END;
GO
