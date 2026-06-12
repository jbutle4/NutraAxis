/*
  NutraAxis Operations — shortage flag on ForecastPlan (End OH < 0)
*/

IF COL_LENGTH('dbo.ForecastPlan', 'ShortageFlag') IS NULL
    ALTER TABLE dbo.ForecastPlan ADD ShortageFlag BIT NOT NULL
        CONSTRAINT DF_ForecastPlan_ShortageFlag DEFAULT (0);
GO

UPDATE dbo.ForecastPlan
SET ShortageFlag = CASE
    WHEN IsLocked = 1 AND ActualEndOH < 0 THEN 1
    WHEN IsLocked = 0 AND ForecastEndOH < 0 THEN 1
    ELSE 0
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_ForecastPlan_ShortageFlag'
      AND object_id = OBJECT_ID(N'dbo.ForecastPlan')
)
    CREATE NONCLUSTERED INDEX IX_ForecastPlan_ShortageFlag
        ON dbo.ForecastPlan (ShortageFlag)
        WHERE ShortageFlag = 1;
GO
