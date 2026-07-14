/*
  NutraAxis Operations — public website COA documents
*/

IF OBJECT_ID(N'dbo.CoaDocument', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.CoaDocument (
        CoaDocumentID     INT IDENTITY(1,1) NOT NULL,
        ProductName       NVARCHAR(200) NOT NULL,
        LotNumber         NVARCHAR(50) NOT NULL,
        ExpirationDate    DATE NOT NULL,
        ExpirationDisplay NVARCHAR(50) NULL,
        FileName          NVARCHAR(255) NOT NULL,
        ContentType       NVARCHAR(100) NOT NULL CONSTRAINT DF_CoaDocument_ContentType DEFAULT (N'application/pdf'),
        FileSizeBytes     INT NULL,
        BlobPath          NVARCHAR(512) NULL,
        Publish           BIT NOT NULL CONSTRAINT DF_CoaDocument_Publish DEFAULT ((1)),
        SortOrder         INT NOT NULL CONSTRAINT DF_CoaDocument_SortOrder DEFAULT ((0)),
        Notes             NVARCHAR(MAX) NULL,
        CreatedByUser     INT NULL,
        ModifiedByUser    INT NULL,
        CreateDate        DATETIME2(0) NOT NULL CONSTRAINT DF_CoaDocument_CreateDate DEFAULT (sysutcdatetime()),
        ModifiedDate      DATETIME2(0) NOT NULL CONSTRAINT DF_CoaDocument_ModifiedDate DEFAULT (sysutcdatetime()),
        CONSTRAINT PK_CoaDocument PRIMARY KEY CLUSTERED (CoaDocumentID),
        CONSTRAINT FK_CoaDocument_CreatedByUser FOREIGN KEY (CreatedByUser) REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_CoaDocument_ModifiedByUser FOREIGN KEY (ModifiedByUser) REFERENCES dbo.[User] (UserID)
    );
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'UQ_CoaDocument_Product_Lot'
      AND object_id = OBJECT_ID(N'dbo.CoaDocument')
)
    CREATE UNIQUE NONCLUSTERED INDEX UQ_CoaDocument_Product_Lot
        ON dbo.CoaDocument (ProductName ASC, LotNumber ASC);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_CoaDocument_Published_Sort'
      AND object_id = OBJECT_ID(N'dbo.CoaDocument')
)
    CREATE NONCLUSTERED INDEX IX_CoaDocument_Published_Sort
        ON dbo.CoaDocument (Publish ASC, SortOrder DESC, ProductName ASC, LotNumber ASC);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_CoaDocument_BlobPath'
      AND object_id = OBJECT_ID(N'dbo.CoaDocument')
)
    CREATE NONCLUSTERED INDEX IX_CoaDocument_BlobPath
        ON dbo.CoaDocument (BlobPath ASC)
        WHERE BlobPath IS NOT NULL;
GO
