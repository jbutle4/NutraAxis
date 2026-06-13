/*
  NutraAxis Operations — Contract attachments for Legal Agreements
*/

IF OBJECT_ID(N'dbo.ContractAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ContractAttachment (
        AttachmentID    INT             NOT NULL IDENTITY(1,1),
        ContractID      INT             NOT NULL,
        FileName        NVARCHAR(255)   NOT NULL,
        ContentType     NVARCHAR(100)   NOT NULL,
        FileSizeBytes   INT             NOT NULL,
        FileData        VARBINARY(MAX)  NOT NULL,
        AttachmentKind  NVARCHAR(30)    NOT NULL CONSTRAINT DF_ContractAttachment_Kind DEFAULT (N'Other'),
        UploadedByUser  INT             NOT NULL,
        UploadDate      DATETIME2(0)    NOT NULL CONSTRAINT DF_ContractAttachment_UploadDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_ContractAttachment PRIMARY KEY CLUSTERED (AttachmentID),
        CONSTRAINT CK_ContractAttachment_Kind CHECK (
            AttachmentKind IN (
                N'ExecutedPDF',
                N'DraftPDF',
                N'Amendment',
                N'Supporting',
                N'Other'
            )
        ),
        CONSTRAINT FK_ContractAttachment_ContractRegister FOREIGN KEY (ContractID)
            REFERENCES dbo.ContractRegister (ContractID) ON DELETE CASCADE,
        CONSTRAINT FK_ContractAttachment_UploadedByUser FOREIGN KEY (UploadedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_ContractAttachment_ContractID
        ON dbo.ContractAttachment (ContractID);
END;
GO
