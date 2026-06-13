/*
  NutraAxis Operations — PO production status tracking per PO line
*/

IF OBJECT_ID(N'dbo.POProductionStatus', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.POProductionStatus (
        ProductionStatusID      INT             NOT NULL IDENTITY(1,1),
        POID                    INT             NOT NULL,
        POLineID                INT             NOT NULL,
        MfgStatus               NVARCHAR(30)    NOT NULL CONSTRAINT DF_POProductionStatus_MfgStatus DEFAULT (N'Not Started'),
        BottlePackagingStatus   NVARCHAR(30)    NOT NULL CONSTRAINT DF_POProductionStatus_BottleStatus DEFAULT (N'Not Started'),
        BulkTestStatus          NVARCHAR(30)    NOT NULL CONSTRAINT DF_POProductionStatus_BulkTest DEFAULT (N'Not Started'),
        BottleTestStatus        NVARCHAR(30)    NOT NULL CONSTRAINT DF_POProductionStatus_BottleTest DEFAULT (N'Not Started'),
        TargetShipDate          DATE            NULL,
        ActualShipDate          DATE            NULL,
        PalletCount             INT             NULL,
        EstWeightLbs            DECIMAL(18,2)   NULL,
        Comments                NVARCHAR(MAX)   NULL,
        LastUpdatedByUser       INT             NULL,
        LastUpdatedDate         DATETIME2(0)    NOT NULL CONSTRAINT DF_POProductionStatus_LastUpdated DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_POProductionStatus PRIMARY KEY CLUSTERED (ProductionStatusID),
        CONSTRAINT UQ_POProductionStatus_POLineID UNIQUE (POLineID),
        CONSTRAINT FK_POProductionStatus_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID),
        CONSTRAINT FK_POProductionStatus_POLineItem FOREIGN KEY (POLineID)
            REFERENCES dbo.POLineItem (POLineID) ON DELETE CASCADE,
        CONSTRAINT FK_POProductionStatus_LastUpdatedByUser FOREIGN KEY (LastUpdatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_POProductionStatus_MfgStatus CHECK (
            MfgStatus IN (N'Not Started', N'In Production', N'Complete', N'On Hold', N'Issue')
        ),
        CONSTRAINT CK_POProductionStatus_BottlePackagingStatus CHECK (
            BottlePackagingStatus IN (N'Not Started', N'In Progress', N'Complete', N'Issue')
        ),
        CONSTRAINT CK_POProductionStatus_BulkTestStatus CHECK (
            BulkTestStatus IN (N'Not Started', N'Submitted', N'Passed', N'Failed', N'On Hold')
        ),
        CONSTRAINT CK_POProductionStatus_BottleTestStatus CHECK (
            BottleTestStatus IN (N'Not Started', N'Submitted', N'Passed', N'Failed', N'On Hold')
        ),
        CONSTRAINT CK_POProductionStatus_PalletCount CHECK (
            PalletCount IS NULL OR PalletCount >= 0
        ),
        CONSTRAINT CK_POProductionStatus_EstWeightLbs CHECK (
            EstWeightLbs IS NULL OR EstWeightLbs >= 0
        )
    );

    CREATE NONCLUSTERED INDEX IX_POProductionStatus_POID
        ON dbo.POProductionStatus (POID);
END;
GO
