/*
  NutraAxis Operations — retry watcher columns on ProcessExecutionLog
*/

IF COL_LENGTH('dbo.ProcessExecutionLog', 'ProcessParams') IS NULL
    ALTER TABLE dbo.ProcessExecutionLog ADD ProcessParams NVARCHAR(MAX) NULL;
GO

IF COL_LENGTH('dbo.ProcessExecutionLog', 'AttemptCount') IS NULL
    ALTER TABLE dbo.ProcessExecutionLog ADD AttemptCount INT NOT NULL
        CONSTRAINT DF_ProcessExecutionLog_AttemptCount DEFAULT (0);
GO

IF COL_LENGTH('dbo.ProcessExecutionLog', 'MaxAttempts') IS NULL
    ALTER TABLE dbo.ProcessExecutionLog ADD MaxAttempts INT NOT NULL
        CONSTRAINT DF_ProcessExecutionLog_MaxAttempts DEFAULT (3);
GO

IF COL_LENGTH('dbo.ProcessExecutionLog', 'LastAttemptAt') IS NULL
    ALTER TABLE dbo.ProcessExecutionLog ADD LastAttemptAt DATETIME2(0) NULL;
GO

IF COL_LENGTH('dbo.ProcessExecutionLog', 'NextRetryAt') IS NULL
    ALTER TABLE dbo.ProcessExecutionLog ADD NextRetryAt DATETIME2(0) NULL;
GO

IF COL_LENGTH('dbo.ProcessExecutionLog', 'CreatedAt') IS NULL
    ALTER TABLE dbo.ProcessExecutionLog ADD CreatedAt DATETIME2(0) NULL;
GO

IF COL_LENGTH('dbo.ProcessExecutionLog', 'CreatedAt') IS NOT NULL
    UPDATE dbo.ProcessExecutionLog
    SET CreatedAt = StartedAt
    WHERE CreatedAt IS NULL;
GO

IF COL_LENGTH('dbo.ProcessExecutionLog', 'CreatedAt') IS NOT NULL
BEGIN
    ALTER TABLE dbo.ProcessExecutionLog ALTER COLUMN CreatedAt DATETIME2(0) NOT NULL;
END;
GO

UPDATE dbo.ProcessExecutionLog
SET LastAttemptAt = COALESCE(LastAttemptAt, FinishedAt, StartedAt)
WHERE LastAttemptAt IS NULL;
GO

IF OBJECT_ID(N'dbo.CK_ProcessExecutionLog_Status', N'C') IS NOT NULL
    ALTER TABLE dbo.ProcessExecutionLog DROP CONSTRAINT CK_ProcessExecutionLog_Status;
GO

ALTER TABLE dbo.ProcessExecutionLog
ADD CONSTRAINT CK_ProcessExecutionLog_Status CHECK (
    Status IN (N'Running', N'Success', N'Failed', N'Abandoned')
);
GO

IF OBJECT_ID(N'dbo.CK_ProcessExecutionLog_TriggerType', N'C') IS NOT NULL
    ALTER TABLE dbo.ProcessExecutionLog DROP CONSTRAINT CK_ProcessExecutionLog_TriggerType;
GO

ALTER TABLE dbo.ProcessExecutionLog
ADD CONSTRAINT CK_ProcessExecutionLog_TriggerType CHECK (
    TriggerType IN (N'Scheduled', N'Manual', N'Retry')
);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_ProcessExecutionLog_NextRetryAt'
      AND object_id = OBJECT_ID(N'dbo.ProcessExecutionLog')
)
    CREATE NONCLUSTERED INDEX IX_ProcessExecutionLog_NextRetryAt
        ON dbo.ProcessExecutionLog (NextRetryAt)
        WHERE NextRetryAt IS NOT NULL AND Status = N'Failed';
GO
