/*
  NutraAxis Operations — Inventory movement completeness reconciliation
  Stores scheduled/manual recon runs and actionable exception lines.
*/

IF OBJECT_ID(N'dbo.InventoryMovementReconRun', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InventoryMovementReconRun (
        ReconRunID          INT             NOT NULL IDENTITY(1,1),
        StartedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_InvMovReconRun_Started DEFAULT (SYSUTCDATETIME()),
        FinishedAt          DATETIME2(0)    NULL,
        TriggerType         NVARCHAR(20)    NOT NULL CONSTRAINT DF_InvMovReconRun_Trigger DEFAULT (N'Scheduled'),
        LookbackDays        INT             NOT NULL CONSTRAINT DF_InvMovReconRun_Lookback DEFAULT (90),
        Status              NVARCHAR(20)    NOT NULL CONSTRAINT DF_InvMovReconRun_Status DEFAULT (N'Running'),
        ReceiptExceptions   INT             NOT NULL CONSTRAINT DF_InvMovReconRun_Rcv DEFAULT (0),
        SaleExceptions      INT             NOT NULL CONSTRAINT DF_InvMovReconRun_Sale DEFAULT (0),
        TransferExceptions  INT             NOT NULL CONSTRAINT DF_InvMovReconRun_Trf DEFAULT (0),
        AdjustmentExceptions INT            NOT NULL CONSTRAINT DF_InvMovReconRun_Adj DEFAULT (0),
        TotalExceptions     INT             NOT NULL CONSTRAINT DF_InvMovReconRun_Total DEFAULT (0),
        SummaryMessage      NVARCHAR(500)   NULL,
        ErrorMessage        NVARCHAR(500)   NULL,
        TriggeredByUserID   INT             NULL,

        CONSTRAINT PK_InventoryMovementReconRun PRIMARY KEY CLUSTERED (ReconRunID),
        CONSTRAINT CK_InvMovReconRun_Status CHECK (
            Status IN (N'Running', N'Success', N'Failed')
        ),
        CONSTRAINT CK_InvMovReconRun_Trigger CHECK (
            TriggerType IN (N'Scheduled', N'Manual')
        )
    );

    CREATE NONCLUSTERED INDEX IX_InvMovReconRun_Started
        ON dbo.InventoryMovementReconRun (StartedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.InventoryMovementReconLine', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InventoryMovementReconLine (
        ReconLineID         INT             NOT NULL IDENTITY(1,1),
        ReconRunID          INT             NOT NULL,
        MovementType        NVARCHAR(30)    NOT NULL,
        ActionCode          NVARCHAR(50)    NOT NULL,
        Severity            NVARCHAR(20)    NOT NULL CONSTRAINT DF_InvMovReconLine_Sev DEFAULT (N'Action'),
        ReferenceType       NVARCHAR(50)    NOT NULL,
        ReferenceID         INT             NOT NULL,
        ReferenceKey        NVARCHAR(100)   NULL,
        SKUCode             NVARCHAR(100)   NULL,
        FacilityCode        NVARCHAR(50)    NULL,
        Qty                 DECIMAL(18,4)   NULL,
        SourceStatus        NVARCHAR(80)    NULL,
        ImsStatus           NVARCHAR(80)    NULL,
        QboStatus           NVARCHAR(80)    NULL,
        RecommendedAction   NVARCHAR(500)   NOT NULL,
        DetailMessage       NVARCHAR(500)   NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_InvMovReconLine_Create DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_InventoryMovementReconLine PRIMARY KEY CLUSTERED (ReconLineID),
        CONSTRAINT FK_InvMovReconLine_Run FOREIGN KEY (ReconRunID)
            REFERENCES dbo.InventoryMovementReconRun (ReconRunID),
        CONSTRAINT CK_InvMovReconLine_Movement CHECK (
            MovementType IN (N'Receipt', N'Sale', N'Transfer', N'Adjustment')
        ),
        CONSTRAINT CK_InvMovReconLine_Severity CHECK (
            Severity IN (N'Action', N'Warning', N'Info')
        )
    );

    CREATE NONCLUSTERED INDEX IX_InvMovReconLine_Run
        ON dbo.InventoryMovementReconLine (ReconRunID, MovementType, ActionCode);

    CREATE NONCLUSTERED INDEX IX_InvMovReconLine_Reference
        ON dbo.InventoryMovementReconLine (ReferenceType, ReferenceID);
END;
GO
