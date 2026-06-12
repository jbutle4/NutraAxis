/*
  NutraAxis Operations — daily ACCS sales summary by SKU
*/

IF OBJECT_ID(N'dbo.DailySalesSummary', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.DailySalesSummary (
        DailySalesSummaryID   INT             NOT NULL IDENTITY(1,1),
        SummaryDate           DATE            NOT NULL,
        SKU                   NVARCHAR(100)   NOT NULL,
        SKUName               NVARCHAR(255)   NULL,
        SKUDescription        NVARCHAR(500)   NULL,
        QtySold               DECIMAL(18,4)   NOT NULL CONSTRAINT DF_DailySalesSummary_QtySold DEFAULT (0),
        SummaryCaptureDate    DATETIME2(0)    NOT NULL,

        CONSTRAINT PK_DailySalesSummary PRIMARY KEY CLUSTERED (DailySalesSummaryID)
    );

    CREATE NONCLUSTERED INDEX IX_DailySalesSummary_SummaryDate
        ON dbo.DailySalesSummary (SummaryDate DESC);

    CREATE NONCLUSTERED INDEX IX_DailySalesSummary_SKU_SummaryDate
        ON dbo.DailySalesSummary (SKU, SummaryDate DESC);
END;
GO
