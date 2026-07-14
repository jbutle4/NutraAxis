/*
  NutraAxis Operations — product page enrichment (PDP HTML + information sheet PDF)
*/

IF OBJECT_ID(N'dbo.ProductEnrichment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ProductEnrichment (
        ProductEnrichmentID INT IDENTITY(1,1) NOT NULL,
        SKUCode             NVARCHAR(100) NOT NULL,
        ProductName         NVARCHAR(200) NULL,
        EnrichmentHtml      NVARCHAR(MAX) NULL,
        PdfLinkText         NVARCHAR(200) NULL,
        FileName            NVARCHAR(255) NULL,
        ContentType         NVARCHAR(100) NULL CONSTRAINT DF_ProductEnrichment_ContentType DEFAULT (N'application/pdf'),
        FileSizeBytes       INT NULL,
        BlobPath            NVARCHAR(512) NULL,
        Publish             BIT NOT NULL CONSTRAINT DF_ProductEnrichment_Publish DEFAULT ((0)),
        Notes               NVARCHAR(MAX) NULL,
        CreatedByUser       INT NULL,
        ModifiedByUser      INT NULL,
        CreateDate          DATETIME2(0) NOT NULL CONSTRAINT DF_ProductEnrichment_CreateDate DEFAULT (sysutcdatetime()),
        ModifiedDate        DATETIME2(0) NOT NULL CONSTRAINT DF_ProductEnrichment_ModifiedDate DEFAULT (sysutcdatetime()),
        CONSTRAINT PK_ProductEnrichment PRIMARY KEY CLUSTERED (ProductEnrichmentID),
        CONSTRAINT UQ_ProductEnrichment_SKUCode UNIQUE (SKUCode),
        CONSTRAINT FK_ProductEnrichment_CreatedByUser FOREIGN KEY (CreatedByUser) REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_ProductEnrichment_ModifiedByUser FOREIGN KEY (ModifiedByUser) REFERENCES dbo.[User] (UserID)
    );
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_ProductEnrichment_Publish_SKU'
      AND object_id = OBJECT_ID(N'dbo.ProductEnrichment')
)
    CREATE NONCLUSTERED INDEX IX_ProductEnrichment_Publish_SKU
        ON dbo.ProductEnrichment (Publish ASC, SKUCode ASC);
GO
