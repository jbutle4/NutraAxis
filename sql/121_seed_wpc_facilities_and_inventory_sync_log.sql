/*
  NutraAxis Operations — WPC location facilities + QBO inventory sync log
  Aligns IMS facilities with Accounting Ops v1.4 location IDs and adds
  idempotency storage for InventoryAdjustment / Journal Entry posts.
*/

DECLARE @SeedUserID INT;
SELECT @SeedUserID = MIN(UserID) FROM dbo.[User];

IF @SeedUserID IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'WPC_QUEUE')
        INSERT INTO dbo.Facility (
            FacilityCode, FacilityName, FacilityType, IsActive, Notes, CreatedByUser
        )
        VALUES (
            N'WPC_QUEUE',
            N'WPC — Awaiting Processing',
            N'Warehouse',
            1,
            N'White label stock queued at WPC (SQL location; maps to WLO workflows).',
            @SeedUserID
        );

    IF NOT EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'WPC_WIP')
        INSERT INTO dbo.Facility (
            FacilityCode, FacilityName, FacilityType, IsActive, Notes, CreatedByUser
        )
        VALUES (
            N'WPC_WIP',
            N'WPC — Work in Progress',
            N'Warehouse',
            1,
            N'White label stock in process at WPC.',
            @SeedUserID
        );
END;
GO

IF COL_LENGTH('dbo.Facility', 'IsMothership') IS NOT NULL
BEGIN
    UPDATE dbo.Facility
    SET
        IsMothership = 0,
        ReceivesPurchaseOrders = 0,
        IntegrationMode = N'Local'
    WHERE FacilityCode IN (N'WPC_QUEUE', N'WPC_WIP', N'WLO');
END;
GO

IF OBJECT_ID(N'dbo.QBOInventorySyncLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.QBOInventorySyncLog (
        SyncLogID           INT             NOT NULL IDENTITY(1,1),
        DocNumber           NVARCHAR(50)    NOT NULL,
        SyncType            NVARCHAR(30)    NOT NULL,
        ReferenceType       NVARCHAR(50)    NOT NULL,
        ReferenceID         INT             NOT NULL,
        ReferenceLineKey    NVARCHAR(100)   NULL,
        SKUCode             NVARCHAR(100)   NOT NULL,
        QtyChange           DECIMAL(18,4)   NOT NULL,
        FacilityCode        NVARCHAR(50)    NULL,
        QBO_TxnId           NVARCHAR(32)    NULL,
        QBO_SyncToken       NVARCHAR(32)    NULL,
        SyncStatus          NVARCHAR(20)    NOT NULL CONSTRAINT DF_QBOInvSync_Status DEFAULT (N'Pending'),
        SyncError           NVARCHAR(500)   NULL,
        SyncedAt            DATETIME2(0)    NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_QBOInvSync_Create DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_QBOInventorySyncLog PRIMARY KEY CLUSTERED (SyncLogID),
        CONSTRAINT UQ_QBOInventorySyncLog_DocNumber UNIQUE (DocNumber),
        CONSTRAINT CK_QBOInvSync_Type CHECK (
            SyncType IN (N'Receipt', N'Sale', N'TransferJE', N'TransferAdj', N'Opening', N'Reconcile')
        ),
        CONSTRAINT CK_QBOInvSync_Status CHECK (
            SyncStatus IN (N'Pending', N'Synced', N'Error', N'Skipped')
        )
    );

    CREATE NONCLUSTERED INDEX IX_QBOInvSync_Reference
        ON dbo.QBOInventorySyncLog (ReferenceType, ReferenceID, SyncType);

    CREATE NONCLUSTERED INDEX IX_QBOInvSync_SKU
        ON dbo.QBOInventorySyncLog (SKUCode, SyncStatus);
END;
GO

IF OBJECT_ID(N'dbo.InvSyncWatermark', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvSyncWatermark (
        WatermarkKey        NVARCHAR(50)    NOT NULL,
        WatermarkValue      NVARCHAR(100)   NULL,
        WatermarkAt         DATETIME2(0)    NULL,
        Notes               NVARCHAR(500)   NULL,
        ModifiedDate        DATETIME2(0)    NOT NULL CONSTRAINT DF_InvSyncWM_Modified DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_InvSyncWatermark PRIMARY KEY CLUSTERED (WatermarkKey)
    );
END;
GO

IF COL_LENGTH('dbo.POReceipt', 'JazzReceivedAt') IS NULL
    ALTER TABLE dbo.POReceipt ADD JazzReceivedAt DATETIME2(0) NULL;
GO

IF COL_LENGTH('dbo.POReceipt', 'IMSPostedAt') IS NULL
    ALTER TABLE dbo.POReceipt ADD IMSPostedAt DATETIME2(0) NULL;
GO
