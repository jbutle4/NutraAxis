/*
  NutraAxis — idempotency log for ACCS order → QuickBooks sandbox (test stack)
*/

IF OBJECT_ID(N'dbo.QBOOrderLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.QBOOrderLog (
        QBOOrderLogID         INT             NOT NULL IDENTITY(1,1),
        AccsEntityId          INT             NULL,
        IncrementId           NVARCHAR(50)    NOT NULL,
        SourceEnvironment     NVARCHAR(20)    NOT NULL,
        QBODocNumber          NVARCHAR(50)    NULL,
        QBOTransactionId      NVARCHAR(32)    NULL,
        QBORealmId            NVARCHAR(32)    NULL,
        Status                NVARCHAR(30)    NOT NULL,
        LastError             NVARCHAR(1000)  NULL,
        CreatedAt             DATETIME2(0)    NOT NULL CONSTRAINT DF_QBOOrderLog_Created DEFAULT (SYSUTCDATETIME()),
        PostedAt              DATETIME2(0)    NULL,

        CONSTRAINT PK_QBOOrderLog PRIMARY KEY CLUSTERED (QBOOrderLogID),
        CONSTRAINT UQ_QBOOrderLog_Increment UNIQUE (IncrementId, SourceEnvironment)
    );
END;
GO
