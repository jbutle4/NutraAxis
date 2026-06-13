/*
  NutraAxis Operations — SKU Master attachments for Product Catalog
*/

IF OBJECT_ID(N'dbo.SKUMasterAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SKUMasterAttachment (
        AttachmentID    INT             NOT NULL IDENTITY(1,1),
        SKUID           INT             NOT NULL,
        FileName        NVARCHAR(255)   NOT NULL,
        ContentType     NVARCHAR(100)   NOT NULL,
        FileSizeBytes   INT             NOT NULL,
        FileData        VARBINARY(MAX)  NOT NULL,
        AttachmentKind  NVARCHAR(30)    NOT NULL CONSTRAINT DF_SKUMasterAttachment_Kind DEFAULT (N'Other'),
        UploadedByUser  INT             NOT NULL,
        UploadDate      DATETIME2(0)    NOT NULL CONSTRAINT DF_SKUMasterAttachment_UploadDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_SKUMasterAttachment PRIMARY KEY CLUSTERED (AttachmentID),
        CONSTRAINT CK_SKUMasterAttachment_Kind CHECK (
            AttachmentKind IN (
                N'LabelPDF',
                N'SupplementFacts',
                N'SpecSheet',
                N'Image',
                N'Other'
            )
        ),
        CONSTRAINT FK_SKUMasterAttachment_SKUMaster FOREIGN KEY (SKUID)
            REFERENCES dbo.SKUMaster (SKUID) ON DELETE CASCADE,
        CONSTRAINT FK_SKUMasterAttachment_UploadedByUser FOREIGN KEY (UploadedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_SKUMasterAttachment_SKUID
        ON dbo.SKUMasterAttachment (SKUID);
END;
GO
