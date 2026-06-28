/*
  NutraAxis — staging/test database sync bookkeeping (lives in nutraaxis_test only)
*/

IF OBJECT_ID(N'dbo.StagingSyncState', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.StagingSyncState (
        TableName       NVARCHAR(128)   NOT NULL,
        LastSyncUtc     DATETIME2(0)    NOT NULL CONSTRAINT DF_StagingSyncState_LastSync DEFAULT ('1970-01-01T00:00:00'),
        LastRunUtc      DATETIME2(0)    NULL,
        RowsInserted    INT             NOT NULL CONSTRAINT DF_StagingSyncState_RowsInserted DEFAULT (0),
        RowsUpdated     INT             NOT NULL CONSTRAINT DF_StagingSyncState_RowsUpdated DEFAULT (0),
        RowsSkipped     INT             NOT NULL CONSTRAINT DF_StagingSyncState_RowsSkipped DEFAULT (0),
        LastError       NVARCHAR(4000)  NULL,

        CONSTRAINT PK_StagingSyncState PRIMARY KEY CLUSTERED (TableName)
    );
END;
GO

IF OBJECT_ID(N'dbo.StagingSyncRun', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.StagingSyncRun (
        StagingSyncRunID    INT             NOT NULL IDENTITY(1,1),
        StartedAt           DATETIME2(0)    NOT NULL,
        FinishedAt          DATETIME2(0)    NULL,
        Status              NVARCHAR(20)    NOT NULL,
        SummaryJson         NVARCHAR(MAX)   NULL,
        ErrorMessage        NVARCHAR(MAX)   NULL,

        CONSTRAINT PK_StagingSyncRun PRIMARY KEY CLUSTERED (StagingSyncRunID),
        CONSTRAINT CK_StagingSyncRun_Status CHECK (
            Status IN (N'Running', N'Success', N'Failed')
        )
    );

    CREATE NONCLUSTERED INDEX IX_StagingSyncRun_StartedAt
        ON dbo.StagingSyncRun (StartedAt DESC);
END;
GO
