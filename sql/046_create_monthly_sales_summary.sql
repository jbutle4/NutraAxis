/*
  NutraAxis Operations — monthly sales rollup (materialized from DailySalesSummary)
*/

IF OBJECT_ID(N'dbo.MonthlySalesSummary', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MonthlySalesSummary (
        MonthlySalesSummaryID   INT             NOT NULL IDENTITY(1,1),
        SKU                     NVARCHAR(100)   NOT NULL,
        SaleYear                INT             NOT NULL,
        SaleMonth               INT             NOT NULL,
        MonthStart              DATE            NOT NULL,
        TotalQty                DECIMAL(18,4)   NOT NULL CONSTRAINT DF_MonthlySalesSummary_TotalQty DEFAULT (0),
        LastUpdatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MonthlySalesSummary_LastUpdated DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_MonthlySalesSummary PRIMARY KEY CLUSTERED (MonthlySalesSummaryID),
        CONSTRAINT UQ_MonthlySalesSummary_SKU_Year_Month UNIQUE (SKU, SaleYear, SaleMonth),
        CONSTRAINT CK_MonthlySalesSummary_SaleMonth CHECK (SaleMonth BETWEEN 1 AND 12)
    );

    CREATE NONCLUSTERED INDEX IX_MonthlySalesSummary_MonthStart
        ON dbo.MonthlySalesSummary (MonthStart DESC);

    CREATE NONCLUSTERED INDEX IX_MonthlySalesSummary_SKU_MonthStart
        ON dbo.MonthlySalesSummary (SKU, MonthStart DESC);
END;
GO
