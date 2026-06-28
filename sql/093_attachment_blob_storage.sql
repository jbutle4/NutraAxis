/*
  NutraAxis Operations — migrate attachment binary storage to Azure Blob Storage.
  Adds BlobPath column and makes FileData nullable on all attachment tables.
*/

-- dbo.POAttachment
IF COL_LENGTH('dbo.POAttachment', 'BlobPath') IS NULL
    ALTER TABLE dbo.POAttachment ADD BlobPath NVARCHAR(512) NULL;
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.POAttachment')
      AND name = N'FileData'
      AND is_nullable = 0
)
    ALTER TABLE dbo.POAttachment ALTER COLUMN FileData VARBINARY(MAX) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.POAttachment')
      AND name = N'IX_POAttachment_BlobPath'
)
    CREATE NONCLUSTERED INDEX IX_POAttachment_BlobPath
        ON dbo.POAttachment (BlobPath)
        WHERE BlobPath IS NOT NULL;
GO

-- dbo.ContractAttachment
IF COL_LENGTH('dbo.ContractAttachment', 'BlobPath') IS NULL
    ALTER TABLE dbo.ContractAttachment ADD BlobPath NVARCHAR(512) NULL;
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.ContractAttachment')
      AND name = N'FileData'
      AND is_nullable = 0
)
    ALTER TABLE dbo.ContractAttachment ALTER COLUMN FileData VARBINARY(MAX) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.ContractAttachment')
      AND name = N'IX_ContractAttachment_BlobPath'
)
    CREATE NONCLUSTERED INDEX IX_ContractAttachment_BlobPath
        ON dbo.ContractAttachment (BlobPath)
        WHERE BlobPath IS NOT NULL;
GO

-- dbo.SKUMasterAttachment
IF COL_LENGTH('dbo.SKUMasterAttachment', 'BlobPath') IS NULL
    ALTER TABLE dbo.SKUMasterAttachment ADD BlobPath NVARCHAR(512) NULL;
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.SKUMasterAttachment')
      AND name = N'FileData'
      AND is_nullable = 0
)
    ALTER TABLE dbo.SKUMasterAttachment ALTER COLUMN FileData VARBINARY(MAX) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.SKUMasterAttachment')
      AND name = N'IX_SKUMasterAttachment_BlobPath'
)
    CREATE NONCLUSTERED INDEX IX_SKUMasterAttachment_BlobPath
        ON dbo.SKUMasterAttachment (BlobPath)
        WHERE BlobPath IS NOT NULL;
GO

-- dbo.PORAttachment
IF COL_LENGTH('dbo.PORAttachment', 'BlobPath') IS NULL
    ALTER TABLE dbo.PORAttachment ADD BlobPath NVARCHAR(512) NULL;
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.PORAttachment')
      AND name = N'FileData'
      AND is_nullable = 0
)
    ALTER TABLE dbo.PORAttachment ALTER COLUMN FileData VARBINARY(MAX) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.PORAttachment')
      AND name = N'IX_PORAttachment_BlobPath'
)
    CREATE NONCLUSTERED INDEX IX_PORAttachment_BlobPath
        ON dbo.PORAttachment (BlobPath)
        WHERE BlobPath IS NOT NULL;
GO

-- dbo.POPaymentAttachment
IF COL_LENGTH('dbo.POPaymentAttachment', 'BlobPath') IS NULL
    ALTER TABLE dbo.POPaymentAttachment ADD BlobPath NVARCHAR(512) NULL;
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.POPaymentAttachment')
      AND name = N'FileData'
      AND is_nullable = 0
)
    ALTER TABLE dbo.POPaymentAttachment ALTER COLUMN FileData VARBINARY(MAX) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.POPaymentAttachment')
      AND name = N'IX_POPaymentAttachment_BlobPath'
)
    CREATE NONCLUSTERED INDEX IX_POPaymentAttachment_BlobPath
        ON dbo.POPaymentAttachment (BlobPath)
        WHERE BlobPath IS NOT NULL;
GO

-- dbo.SupplierInvoiceAttachment
IF COL_LENGTH('dbo.SupplierInvoiceAttachment', 'BlobPath') IS NULL
    ALTER TABLE dbo.SupplierInvoiceAttachment ADD BlobPath NVARCHAR(512) NULL;
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.SupplierInvoiceAttachment')
      AND name = N'FileData'
      AND is_nullable = 0
)
    ALTER TABLE dbo.SupplierInvoiceAttachment ALTER COLUMN FileData VARBINARY(MAX) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.SupplierInvoiceAttachment')
      AND name = N'IX_SupplierInvoiceAttachment_BlobPath'
)
    CREATE NONCLUSTERED INDEX IX_SupplierInvoiceAttachment_BlobPath
        ON dbo.SupplierInvoiceAttachment (BlobPath)
        WHERE BlobPath IS NOT NULL;
GO

-- dbo.EnhLogAttachment
IF COL_LENGTH('dbo.EnhLogAttachment', 'BlobPath') IS NULL
    ALTER TABLE dbo.EnhLogAttachment ADD BlobPath NVARCHAR(512) NULL;
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.EnhLogAttachment')
      AND name = N'FileData'
      AND is_nullable = 0
)
    ALTER TABLE dbo.EnhLogAttachment ALTER COLUMN FileData VARBINARY(MAX) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.EnhLogAttachment')
      AND name = N'IX_EnhLogAttachment_BlobPath'
)
    CREATE NONCLUSTERED INDEX IX_EnhLogAttachment_BlobPath
        ON dbo.EnhLogAttachment (BlobPath)
        WHERE BlobPath IS NOT NULL;
GO

-- dbo.TEAttachment
IF COL_LENGTH('dbo.TEAttachment', 'BlobPath') IS NULL
    ALTER TABLE dbo.TEAttachment ADD BlobPath NVARCHAR(512) NULL;
GO

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.TEAttachment')
      AND name = N'FileData'
      AND is_nullable = 0
)
    ALTER TABLE dbo.TEAttachment ALTER COLUMN FileData VARBINARY(MAX) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.TEAttachment')
      AND name = N'IX_TEAttachment_BlobPath'
)
    CREATE NONCLUSTERED INDEX IX_TEAttachment_BlobPath
        ON dbo.TEAttachment (BlobPath)
        WHERE BlobPath IS NOT NULL;
GO
