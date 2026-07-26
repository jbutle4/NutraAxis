/*
  NutraAxis Operations — Jazz → IMS CART align run audit
  Records dry-run / apply sessions that post JazzSyncReconcile to CART.
  Does not change QuickBooks QtyOnHand.
*/

IF OBJECT_ID(N'dbo.InventoryJazzImsAlignRun', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InventoryJazzImsAlignRun (
        AlignRunID          INT             NOT NULL IDENTITY(1,1),
        StartedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_JazzImsAlign_Started DEFAULT (SYSUTCDATETIME()),
        FinishedAt          DATETIME2(0)    NULL,
        JazzEnvironment     NVARCHAR(20)    NOT NULL CONSTRAINT DF_JazzImsAlign_Env DEFAULT (N'production'),
        DryRun              BIT             NOT NULL CONSTRAINT DF_JazzImsAlign_Dry DEFAULT (1),
        ZeroMissingJazz     BIT             NOT NULL CONSTRAINT DF_JazzImsAlign_Zero DEFAULT (0),
        Status              NVARCHAR(20)    NOT NULL CONSTRAINT DF_JazzImsAlign_Status DEFAULT (N'Running'),
        CandidateCount      INT             NOT NULL CONSTRAINT DF_JazzImsAlign_Cand DEFAULT (0),
        PostedCount         INT             NOT NULL CONSTRAINT DF_JazzImsAlign_Posted DEFAULT (0),
        SkippedCount        INT             NOT NULL CONSTRAINT DF_JazzImsAlign_Skip DEFAULT (0),
        TransactionID       INT             NULL,
        SummaryMessage      NVARCHAR(500)   NULL,
        ErrorMessage        NVARCHAR(500)   NULL,
        TriggeredByUserID   INT             NULL,

        CONSTRAINT PK_InventoryJazzImsAlignRun PRIMARY KEY CLUSTERED (AlignRunID),
        CONSTRAINT CK_JazzImsAlign_Status CHECK (
            Status IN (N'Running', N'Success', N'Failed', N'DryRun')
        ),
        CONSTRAINT CK_JazzImsAlign_Env CHECK (
            JazzEnvironment IN (N'production', N'uat')
        ),
        CONSTRAINT FK_JazzImsAlign_Txn FOREIGN KEY (TransactionID)
            REFERENCES dbo.InvTransaction (TransactionID)
    );

    CREATE NONCLUSTERED INDEX IX_JazzImsAlign_Started
        ON dbo.InventoryJazzImsAlignRun (StartedAt DESC);
END;
GO
