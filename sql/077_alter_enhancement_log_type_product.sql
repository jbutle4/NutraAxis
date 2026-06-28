/*
  NutraAxis Operations — EnhancementLog type and IT product columns
*/

IF COL_LENGTH('dbo.EnhancementLog', 'EnhType') IS NULL
    ALTER TABLE dbo.EnhancementLog ADD EnhType NVARCHAR(50) NULL;
GO

IF COL_LENGTH('dbo.EnhancementLog', 'ITProduct') IS NULL
    ALTER TABLE dbo.EnhancementLog ADD ITProduct NVARCHAR(100) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = N'CK_EnhancementLog_EnhType'
      AND parent_object_id = OBJECT_ID(N'dbo.EnhancementLog')
)
BEGIN
    ALTER TABLE dbo.EnhancementLog
        ADD CONSTRAINT CK_EnhancementLog_EnhType CHECK (
            EnhType IS NULL
            OR EnhType IN (N'Enhancement', N'Bug', N'UI', N'New Feature')
        );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = N'CK_EnhancementLog_ITProduct'
      AND parent_object_id = OBJECT_ID(N'dbo.EnhancementLog')
)
BEGIN
    ALTER TABLE dbo.EnhancementLog
        ADD CONSTRAINT CK_EnhancementLog_ITProduct CHECK (
            ITProduct IS NULL
            OR ITProduct IN (
                N'ACCS',
                N'QBO',
                N'Operations Portal',
                N'Integration or Automation',
                N'Other - add in description'
            )
        );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_EnhancementLog_EnhType'
      AND object_id = OBJECT_ID(N'dbo.EnhancementLog')
)
    CREATE NONCLUSTERED INDEX IX_EnhancementLog_EnhType
        ON dbo.EnhancementLog (EnhType);
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_EnhancementLog_ITProduct'
      AND object_id = OBJECT_ID(N'dbo.EnhancementLog')
)
    CREATE NONCLUSTERED INDEX IX_EnhancementLog_ITProduct
        ON dbo.EnhancementLog (ITProduct);
GO
