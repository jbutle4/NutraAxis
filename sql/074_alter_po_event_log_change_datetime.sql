/*
  NutraAxis Operations — PO Event Log: ChangeDateTime as DATETIME2
*/

IF COL_LENGTH('dbo.POEventLog', 'ChangeDateTime') IS NOT NULL
AND EXISTS (
    SELECT 1
    FROM sys.columns c
    INNER JOIN sys.types t ON t.user_type_id = c.user_type_id
    INNER JOIN sys.tables tb ON tb.object_id = c.object_id
    INNER JOIN sys.schemas s ON s.schema_id = tb.schema_id
    WHERE s.name = N'dbo'
      AND tb.name = N'POEventLog'
      AND c.name = N'ChangeDateTime'
      AND t.name = N'nvarchar'
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM sys.indexes i
        INNER JOIN sys.tables tb ON tb.object_id = i.object_id
        INNER JOIN sys.schemas s ON s.schema_id = tb.schema_id
        WHERE s.name = N'dbo'
          AND tb.name = N'POEventLog'
          AND i.name = N'IX_POEventLog_ChangeDateTime'
    )
        DROP INDEX IX_POEventLog_ChangeDateTime ON dbo.POEventLog;

    ALTER TABLE dbo.POEventLog ALTER COLUMN ChangeDateTime DATETIME2(0) NULL;

    CREATE NONCLUSTERED INDEX IX_POEventLog_ChangeDateTime
        ON dbo.POEventLog (ChangeDateTime DESC);
END;
GO
