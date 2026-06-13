/*
  NutraAxis Operations — enhancement request log
*/

IF OBJECT_ID(N'dbo.EnhancementLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.EnhancementLog (
        EnhancementLogID    INT             NOT NULL IDENTITY(1,1),
        EnhancementTitle    NVARCHAR(200)   NOT NULL,
        EnhDesc             NVARCHAR(MAX)   NULL,
        RequestedBy         NVARCHAR(200)   NULL,
        RequestDate         DATETIME2(0)    NULL,
        RequestStatus       NVARCHAR(20)    NOT NULL CONSTRAINT DF_EnhancementLog_RequestStatus DEFAULT (N'New'),
        ReqDueDate          DATETIME2(0)    NULL,
        ReqNotes            NVARCHAR(MAX)   NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_EnhancementLog_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate        DATETIME2(0)    NOT NULL CONSTRAINT DF_EnhancementLog_ModifiedDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_EnhancementLog PRIMARY KEY CLUSTERED (EnhancementLogID),
        CONSTRAINT CK_EnhancementLog_RequestStatus CHECK (
            RequestStatus IN (N'New', N'Review', N'InProgress', N'OnHold', N'Complete', N'Canceled')
        )
    );

    CREATE NONCLUSTERED INDEX IX_EnhancementLog_RequestStatus
        ON dbo.EnhancementLog (RequestStatus);

    CREATE NONCLUSTERED INDEX IX_EnhancementLog_RequestDate
        ON dbo.EnhancementLog (RequestDate DESC);
END;
GO
