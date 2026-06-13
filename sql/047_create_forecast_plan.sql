/*
  NutraAxis Operations — inventory forecast plan by SKU and month
*/

IF OBJECT_ID(N'dbo.ForecastPlan', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ForecastPlan (
        ForecastPlanID      INT             NOT NULL IDENTITY(1,1),
        SKU                 NVARCHAR(100)   NOT NULL,
        PlanYear            INT             NOT NULL,
        PlanMonth           INT             NOT NULL,

        ActualBeginOH       DECIMAL(18,4)   NULL,
        ActualReceipts      DECIMAL(18,4)   NULL,
        ActualSales         DECIMAL(18,4)   NULL,
        ActualEndOH         DECIMAL(18,4)   NULL,

        ForecastBeginOH     DECIMAL(18,4)   NULL,
        ForecastReceipts    DECIMAL(18,4)   NULL,
        ForecastSales       DECIMAL(18,4)   NULL,
        ForecastEndOH       DECIMAL(18,4)   NULL,

        BaselineAvg         DECIMAL(18,4)   NULL,
        TrendFactor         DECIMAL(8,4)    NULL,
        GeneratedAt         DATETIME2(0)    NOT NULL CONSTRAINT DF_ForecastPlan_GeneratedAt DEFAULT (SYSUTCDATETIME()),
        IsLocked            BIT             NOT NULL CONSTRAINT DF_ForecastPlan_IsLocked DEFAULT (0),
        ShortageFlag        BIT             NOT NULL CONSTRAINT DF_ForecastPlan_ShortageFlag DEFAULT (0),

        CONSTRAINT PK_ForecastPlan PRIMARY KEY CLUSTERED (ForecastPlanID),
        CONSTRAINT UQ_ForecastPlan_SKU_Year_Month UNIQUE (SKU, PlanYear, PlanMonth),
        CONSTRAINT CK_ForecastPlan_PlanMonth CHECK (PlanMonth BETWEEN 1 AND 12)
    );

    CREATE NONCLUSTERED INDEX IX_ForecastPlan_PlanYear_Month
        ON dbo.ForecastPlan (PlanYear DESC, PlanMonth DESC);

    CREATE NONCLUSTERED INDEX IX_ForecastPlan_SKU_PlanYear_Month
        ON dbo.ForecastPlan (SKU, PlanYear DESC, PlanMonth DESC);

    CREATE NONCLUSTERED INDEX IX_ForecastPlan_ShortageFlag
        ON dbo.ForecastPlan (ShortageFlag)
        WHERE ShortageFlag = 1;
END;
GO
