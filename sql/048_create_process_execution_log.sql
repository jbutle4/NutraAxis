/*
  NutraAxis Operations — scheduled and manual process execution log
*/

IF OBJECT_ID(N'dbo.ProcessExecutionLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ProcessExecutionLog (
        ProcessExecutionLogID   INT             NOT NULL IDENTITY(1,1),
        ProcessCode             NVARCHAR(50)    NOT NULL,
        ProcessName             NVARCHAR(200)   NOT NULL,
        StartedAt               DATETIME2(0)    NOT NULL,
        FinishedAt              DATETIME2(0)    NULL,
        Status                  NVARCHAR(20)    NOT NULL,
        ResultMessage           NVARCHAR(500)   NULL,
        ErrorMessage            NVARCHAR(MAX)   NULL,
        TriggerType             NVARCHAR(20)    NOT NULL CONSTRAINT DF_ProcessExecutionLog_TriggerType DEFAULT (N'Scheduled'),
        TriggeredByUserID       INT             NULL,
        ResultJson              NVARCHAR(MAX)   NULL,
        ProcessParams           NVARCHAR(MAX)   NULL,
        AttemptCount            INT             NOT NULL CONSTRAINT DF_ProcessExecutionLog_AttemptCount DEFAULT (0),
        MaxAttempts             INT             NOT NULL CONSTRAINT DF_ProcessExecutionLog_MaxAttempts DEFAULT (3),
        LastAttemptAt           DATETIME2(0)    NULL,
        NextRetryAt             DATETIME2(0)    NULL,
        CreatedAt               DATETIME2(0)    NOT NULL,

        CONSTRAINT PK_ProcessExecutionLog PRIMARY KEY CLUSTERED (ProcessExecutionLogID),
        CONSTRAINT CK_ProcessExecutionLog_Status CHECK (
            Status IN (N'Running', N'Success', N'Failed', N'Abandoned')
        ),
        CONSTRAINT CK_ProcessExecutionLog_TriggerType CHECK (
            TriggerType IN (N'Scheduled', N'Manual', N'Retry')
        ),
        CONSTRAINT FK_ProcessExecutionLog_User FOREIGN KEY (TriggeredByUserID)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_ProcessExecutionLog_StartedAt
        ON dbo.ProcessExecutionLog (StartedAt DESC);

    CREATE NONCLUSTERED INDEX IX_ProcessExecutionLog_ProcessCode_StartedAt
        ON dbo.ProcessExecutionLog (ProcessCode, StartedAt DESC);

    CREATE NONCLUSTERED INDEX IX_ProcessExecutionLog_Status_StartedAt
        ON dbo.ProcessExecutionLog (Status, StartedAt DESC);

    CREATE NONCLUSTERED INDEX IX_ProcessExecutionLog_NextRetryAt
        ON dbo.ProcessExecutionLog (NextRetryAt)
        WHERE NextRetryAt IS NOT NULL AND Status = N'Failed';
END;
GO
