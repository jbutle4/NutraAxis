/*
  NutraAxis Operations — audit change log for INSERT / UPDATE / DELETE
*/

IF OBJECT_ID(N'dbo.AuditChangeLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AuditChangeLog (
        LogID        INT             NOT NULL IDENTITY(1,1),
        ChangeDate   DATETIME2(0)    NOT NULL CONSTRAINT DF_AuditChangeLog_ChangeDate DEFAULT (SYSUTCDATETIME()),
        UserID       INT             NOT NULL,
        ChangeSQL    NVARCHAR(MAX)   NOT NULL,
        ReverseSQL   NVARCHAR(MAX)   NOT NULL,
        RolledBackDate DATETIME2(0)  NULL,

        CONSTRAINT PK_AuditChangeLog PRIMARY KEY CLUSTERED (LogID),
        CONSTRAINT FK_AuditChangeLog_User FOREIGN KEY (UserID)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_AuditChangeLog_ChangeDate
        ON dbo.AuditChangeLog (ChangeDate DESC);

    CREATE NONCLUSTERED INDEX IX_AuditChangeLog_UserID
        ON dbo.AuditChangeLog (UserID);
END;
GO
