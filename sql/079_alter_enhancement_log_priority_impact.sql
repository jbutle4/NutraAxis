/*
  NutraAxis Operations — EnhancementLog priority and impact columns
*/

IF COL_LENGTH('dbo.EnhancementLog', 'Priority') IS NULL
    ALTER TABLE dbo.EnhancementLog ADD Priority NVARCHAR(50) NULL;
GO

IF COL_LENGTH('dbo.EnhancementLog', 'Impact') IS NULL
    ALTER TABLE dbo.EnhancementLog ADD Impact NVARCHAR(50) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = N'CK_EnhancementLog_Priority'
      AND parent_object_id = OBJECT_ID(N'dbo.EnhancementLog')
)
BEGIN
    ALTER TABLE dbo.EnhancementLog
        ADD CONSTRAINT CK_EnhancementLog_Priority CHECK (
            Priority IS NULL
            OR Priority IN (N'High', N'Medium', N'Low')
        );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = N'CK_EnhancementLog_Impact'
      AND parent_object_id = OBJECT_ID(N'dbo.EnhancementLog')
)
BEGIN
    ALTER TABLE dbo.EnhancementLog
        ADD CONSTRAINT CK_EnhancementLog_Impact CHECK (
            Impact IS NULL
            OR Impact IN (N'Critical', N'High', N'Medium', N'Low')
        );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_EnhancementLog_Priority'
      AND object_id = OBJECT_ID(N'dbo.EnhancementLog')
)
    CREATE NONCLUSTERED INDEX IX_EnhancementLog_Priority
        ON dbo.EnhancementLog (Priority);
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_EnhancementLog_Impact'
      AND object_id = OBJECT_ID(N'dbo.EnhancementLog')
)
    CREATE NONCLUSTERED INDEX IX_EnhancementLog_Impact
        ON dbo.EnhancementLog (Impact);
GO
