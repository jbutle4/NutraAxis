/*
  NutraAxis Operations — provider signup attachments use Azure Blob (app-encrypted).
  Adds BlobPath + IsEncrypted; makes FileData nullable.
*/

IF COL_LENGTH('dbo.ProviderSignupAttachment', 'BlobPath') IS NULL
    ALTER TABLE dbo.ProviderSignupAttachment ADD BlobPath NVARCHAR(512) NULL;
GO

IF COL_LENGTH('dbo.ProviderSignupAttachment', 'IsEncrypted') IS NULL
    ALTER TABLE dbo.ProviderSignupAttachment ADD IsEncrypted BIT NOT NULL
        CONSTRAINT DF_ProviderSignupAttachment_IsEncrypted DEFAULT (0);
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.ProviderSignupAttachment')
      AND name = N'FileData'
      AND is_nullable = 0
)
    ALTER TABLE dbo.ProviderSignupAttachment ALTER COLUMN FileData VARBINARY(MAX) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.ProviderSignupAttachment')
      AND name = N'IX_ProviderSignupAttachment_BlobPath'
)
    CREATE NONCLUSTERED INDEX IX_ProviderSignupAttachment_BlobPath
        ON dbo.ProviderSignupAttachment (BlobPath)
        WHERE BlobPath IS NOT NULL;
GO
