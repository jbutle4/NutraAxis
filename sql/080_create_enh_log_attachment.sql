/*
  NutraAxis Operations — Enhancement log screenshot attachments
*/

IF OBJECT_ID(N'dbo.EnhLogAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.EnhLogAttachment (
        AttachmentID     INT             NOT NULL IDENTITY(1,1),
        EnhancementLogID INT             NOT NULL,
        FileName         NVARCHAR(260)   NOT NULL,
        ContentType      NVARCHAR(120)   NOT NULL,
        FileSizeBytes    INT             NOT NULL,
        FileData         VARBINARY(MAX)  NOT NULL,
        AttachmentKind   NVARCHAR(50)    NOT NULL CONSTRAINT DF_EnhLogAttachment_Kind DEFAULT (N'Screenshot'),
        UploadedByUser   INT             NULL,
        UploadedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_EnhLogAttachment_UploadedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_EnhLogAttachment PRIMARY KEY CLUSTERED (AttachmentID),
        CONSTRAINT CK_EnhLogAttachment_Kind CHECK (
            AttachmentKind IN (N'Screenshot', N'Other')
        ),
        CONSTRAINT FK_EnhLogAttachment_EnhancementLog FOREIGN KEY (EnhancementLogID)
            REFERENCES dbo.EnhancementLog (EnhancementLogID) ON DELETE CASCADE,
        CONSTRAINT FK_EnhLogAttachment_User FOREIGN KEY (UploadedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_EnhLogAttachment_EnhancementLogID
        ON dbo.EnhLogAttachment (EnhancementLogID, UploadedAt DESC);
END;
GO
