/*
  NutraAxis Operations — PO Event Log
*/

IF OBJECT_ID(N'dbo.POEventLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.POEventLog (
        POEventID       INT             NOT NULL IDENTITY(1,1),
        POID            INT             NOT NULL,
        OtherID         INT             NULL,
        OtherEventTable NVARCHAR(128)   NULL,
        ChangeEvent     NVARCHAR(200)   NULL,
        ChangeUser      NVARCHAR(200)   NULL,
        ChangeDateTime  DATETIME2(0)    NULL,
        ChangeNotes     NVARCHAR(MAX)   NULL,

        CONSTRAINT PK_POEventLog PRIMARY KEY CLUSTERED (POEventID),
        CONSTRAINT FK_POEventLog_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID)
    );

    CREATE NONCLUSTERED INDEX IX_POEventLog_POID
        ON dbo.POEventLog (POID);

    CREATE NONCLUSTERED INDEX IX_POEventLog_ChangeDateTime
        ON dbo.POEventLog (ChangeDateTime DESC);
END;
GO
