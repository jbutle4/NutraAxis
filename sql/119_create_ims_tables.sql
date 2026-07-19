/*
  NutraAxis Operations — Inventory Management System (IMS) Phase 0 schema
*/

IF OBJECT_ID(N'dbo.Facility', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.Facility (
        FacilityID      INT             NOT NULL IDENTITY(1,1),
        FacilityCode    NVARCHAR(50)    NOT NULL,
        FacilityName    NVARCHAR(200)   NOT NULL,
        FacilityType    NVARCHAR(30)    NOT NULL,
        Address         NVARCHAR(500)   NULL,
        IsActive        BIT             NOT NULL CONSTRAINT DF_Facility_IsActive DEFAULT (1),
        Notes           NVARCHAR(MAX)   NULL,
        CreatedByUser   INT             NOT NULL,
        CreateDate      DATETIME2(0)    NOT NULL CONSTRAINT DF_Facility_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate    DATETIME2(0)    NOT NULL CONSTRAINT DF_Facility_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedByUser  INT             NULL,

        CONSTRAINT PK_Facility PRIMARY KEY CLUSTERED (FacilityID),
        CONSTRAINT UQ_Facility_FacilityCode UNIQUE (FacilityCode),
        CONSTRAINT CK_Facility_FacilityType CHECK (
            FacilityType IN (
                N'Warehouse', N'3PL', N'CPPC', N'Transit',
                N'Virtual', N'QC Hold', N'Other'
            )
        ),
        CONSTRAINT FK_Facility_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_Facility_ModifiedByUser FOREIGN KEY (ModifiedByUser)
            REFERENCES dbo.[User] (UserID)
    );
END;
GO

IF OBJECT_ID(N'dbo.InvReasonCode', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvReasonCode (
        ReasonCodeID        INT             NOT NULL IDENTITY(1,1),
        ReasonCode          NVARCHAR(30)    NOT NULL,
        Description         NVARCHAR(200)   NOT NULL,
        AppliesToAdjustment BIT             NOT NULL CONSTRAINT DF_InvReasonCode_Adj DEFAULT (0),
        AppliesToTransfer   BIT             NOT NULL CONSTRAINT DF_InvReasonCode_Transfer DEFAULT (0),
        AppliesToReturn     BIT             NOT NULL CONSTRAINT DF_InvReasonCode_Return DEFAULT (0),
        AppliesToReceipt    BIT             NOT NULL CONSTRAINT DF_InvReasonCode_Receipt DEFAULT (0),
        AppliesToSale       BIT             NOT NULL CONSTRAINT DF_InvReasonCode_Sale DEFAULT (0),
        AppliesToCount      BIT             NOT NULL CONSTRAINT DF_InvReasonCode_Count DEFAULT (0),
        DefaultDirection    NCHAR(1)        NULL,
        IsActive            BIT             NOT NULL CONSTRAINT DF_InvReasonCode_Active DEFAULT (1),

        CONSTRAINT PK_InvReasonCode PRIMARY KEY CLUSTERED (ReasonCodeID),
        CONSTRAINT UQ_InvReasonCode_Code UNIQUE (ReasonCode),
        CONSTRAINT CK_InvReasonCode_Direction CHECK (
            DefaultDirection IS NULL OR DefaultDirection IN (N'+', N'-')
        )
    );
END;
GO

IF OBJECT_ID(N'dbo.InvTransaction', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvTransaction (
        TransactionID     INT             NOT NULL IDENTITY(1,1),
        TransactionDate   DATETIME2(0)    NOT NULL CONSTRAINT DF_InvTxn_Date DEFAULT (SYSUTCDATETIME()),
        TransactionType   NVARCHAR(30)    NOT NULL,
        ReferenceType     NVARCHAR(50)    NOT NULL,
        ReferenceID       INT             NOT NULL,
        Notes             NVARCHAR(500)   NULL,
        CreatedByUser     INT             NULL,
        CreatedAt         DATETIME2(0)    NOT NULL CONSTRAINT DF_InvTxn_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_InvTransaction PRIMARY KEY CLUSTERED (TransactionID),
        CONSTRAINT CK_InvTxn_Type CHECK (
            TransactionType IN (
                N'OpeningBalance', N'POReceipt', N'Sale', N'AdjustmentGain',
                N'AdjustmentLoss', N'TransferOut', N'TransferIn',
                N'CustomerReturn', N'StatusChange', N'JazzSyncReconcile'
            )
        ),
        CONSTRAINT FK_InvTxn_User FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID)
    );
END;
GO

IF OBJECT_ID(N'dbo.InvTransactionLine', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvTransactionLine (
        TransactionLineID INT             NOT NULL IDENTITY(1,1),
        TransactionID     INT             NOT NULL,
        LineNumber        INT             NOT NULL,
        SKUCode           NVARCHAR(100)   NOT NULL,
        FacilityCode      NVARCHAR(50)    NOT NULL,
        StatusBucket      NVARCHAR(20)    NOT NULL,
        QtyChange         DECIMAL(18,4)   NOT NULL,
        QtyBefore         DECIMAL(18,4)   NOT NULL,
        QtyAfter          DECIMAL(18,4)   NOT NULL,
        Notes             NVARCHAR(500)   NULL,

        CONSTRAINT PK_InvTransactionLine PRIMARY KEY CLUSTERED (TransactionLineID),
        CONSTRAINT UQ_InvTxnLine_Transaction_Line UNIQUE (TransactionID, LineNumber),
        CONSTRAINT CK_InvTxnLine_Bucket CHECK (
            StatusBucket IN (N'OK', N'Quarantine', N'OnHold', N'Destroy')
        ),
        CONSTRAINT FK_InvTxnLine_Transaction FOREIGN KEY (TransactionID)
            REFERENCES dbo.InvTransaction (TransactionID) ON DELETE CASCADE,
        CONSTRAINT FK_InvTxnLine_SKU FOREIGN KEY (SKUCode)
            REFERENCES dbo.SKUMaster (SKUCode),
        CONSTRAINT FK_InvTxnLine_Facility FOREIGN KEY (FacilityCode)
            REFERENCES dbo.Facility (FacilityCode)
    );

    CREATE NONCLUSTERED INDEX IX_InvTxnLine_SKU_Facility
        ON dbo.InvTransactionLine (SKUCode, FacilityCode);
END;
GO

IF OBJECT_ID(N'dbo.InvCurrentBalance', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvCurrentBalance (
        BalanceID         INT             NOT NULL IDENTITY(1,1),
        SKUCode           NVARCHAR(100)   NOT NULL,
        FacilityCode      NVARCHAR(50)    NOT NULL,
        QtyOK             DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InvCB_QtyOK DEFAULT (0),
        QtyQuarantine       DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InvCB_Quarantine DEFAULT (0),
        QtyOnHold           DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InvCB_OnHold DEFAULT (0),
        QtyDestroy          DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InvCB_Destroy DEFAULT (0),
        QtyReserved         DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InvCB_Reserved DEFAULT (0),
        QtyOnHand           AS (QtyOK + QtyQuarantine + QtyOnHold + QtyDestroy) PERSISTED,
        QtyAvailable        AS (QtyOK - QtyReserved) PERSISTED,
        LastTransactionID   INT             NULL,
        LastCountDate       DATE            NULL,
        LastUpdated         DATETIME2(0)    NOT NULL CONSTRAINT DF_InvCB_Updated DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_InvCurrentBalance PRIMARY KEY CLUSTERED (BalanceID),
        CONSTRAINT UQ_InvCurrentBalance_SKU_Facility UNIQUE (SKUCode, FacilityCode),
        CONSTRAINT CK_InvCB_QtyOK CHECK (QtyOK >= 0),
        CONSTRAINT CK_InvCB_QtyQuarantine CHECK (QtyQuarantine >= 0),
        CONSTRAINT CK_InvCB_QtyOnHold CHECK (QtyOnHold >= 0),
        CONSTRAINT CK_InvCB_QtyDestroy CHECK (QtyDestroy >= 0),
        CONSTRAINT CK_InvCB_QtyReserved CHECK (QtyReserved >= 0),
        CONSTRAINT FK_InvCB_SKU FOREIGN KEY (SKUCode)
            REFERENCES dbo.SKUMaster (SKUCode),
        CONSTRAINT FK_InvCB_Facility FOREIGN KEY (FacilityCode)
            REFERENCES dbo.Facility (FacilityCode)
    );
END;
GO

IF OBJECT_ID(N'dbo.InvAdjustment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvAdjustment (
        AdjustmentID    INT             NOT NULL IDENTITY(1,1),
        AdjustmentDate  DATETIME2(0)    NOT NULL CONSTRAINT DF_InvAdj_Date DEFAULT (SYSUTCDATETIME()),
        SKUCode         NVARCHAR(100)   NOT NULL,
        FacilityCode    NVARCHAR(50)    NOT NULL,
        StatusBucket    NVARCHAR(20)    NOT NULL,
        QtyAdjusted     DECIMAL(18,4)   NOT NULL,
        QtyBefore       DECIMAL(18,4)   NOT NULL,
        QtyAfter        DECIMAL(18,4)   NOT NULL,
        ReasonCodeID    INT             NOT NULL,
        Notes           NVARCHAR(MAX)   NULL,
        AdjStatus       NVARCHAR(20)    NOT NULL CONSTRAINT DF_InvAdj_Status DEFAULT (N'Pending'),
        CountSessionID  INT             NULL,
        ApprovedByUser  INT             NULL,
        ApprovedAt      DATETIME2(0)    NULL,
        CreatedByUser   INT             NOT NULL,
        CreateDate      DATETIME2(0)    NOT NULL CONSTRAINT DF_InvAdj_CreateDate DEFAULT (SYSUTCDATETIME()),
        TransactionID   INT             NULL,

        CONSTRAINT PK_InvAdjustment PRIMARY KEY CLUSTERED (AdjustmentID),
        CONSTRAINT CK_InvAdj_Bucket CHECK (
            StatusBucket IN (N'OK', N'Quarantine', N'OnHold', N'Destroy')
        ),
        CONSTRAINT CK_InvAdj_Status CHECK (
            AdjStatus IN (N'Pending', N'Approved', N'Rejected')
        ),
        CONSTRAINT FK_InvAdj_SKU FOREIGN KEY (SKUCode)
            REFERENCES dbo.SKUMaster (SKUCode),
        CONSTRAINT FK_InvAdj_Facility FOREIGN KEY (FacilityCode)
            REFERENCES dbo.Facility (FacilityCode),
        CONSTRAINT FK_InvAdj_ReasonCode FOREIGN KEY (ReasonCodeID)
            REFERENCES dbo.InvReasonCode (ReasonCodeID),
        CONSTRAINT FK_InvAdj_ApprovedBy FOREIGN KEY (ApprovedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_InvAdj_CreatedBy FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_InvAdj_Transaction FOREIGN KEY (TransactionID)
            REFERENCES dbo.InvTransaction (TransactionID)
    );

    CREATE NONCLUSTERED INDEX IX_InvAdjustment_SKU_Facility
        ON dbo.InvAdjustment (SKUCode, FacilityCode, AdjStatus);
END;
GO

IF OBJECT_ID(N'dbo.InvTransfer', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvTransfer (
        TransferID            INT             NOT NULL IDENTITY(1,1),
        TransferDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_InvTrf_Date DEFAULT (SYSUTCDATETIME()),
        SKUCode               NVARCHAR(100)   NOT NULL,
        FromFacilityCode      NVARCHAR(50)    NOT NULL,
        ToFacilityCode        NVARCHAR(50)    NOT NULL,
        FromStatusBucket      NVARCHAR(20)    NOT NULL,
        ToStatusBucket        NVARCHAR(20)    NOT NULL,
        QtyRequested          DECIMAL(18,4)   NOT NULL,
        QtyShipped            DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InvTrf_Shipped DEFAULT (0),
        QtyReceived           DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InvTrf_Received DEFAULT (0),
        ReasonCodeID          INT             NULL,
        TransferStatus        NVARCHAR(30)    NOT NULL CONSTRAINT DF_InvTrf_Status DEFAULT (N'Pending'),
        Notes                 NVARCHAR(MAX)   NULL,
        ShippedAt             DATETIME2(0)    NULL,
        ReceivedAt            DATETIME2(0)    NULL,
        OutboundTransactionID INT             NULL,
        InboundTransactionID  INT             NULL,
        RequestedByUser       INT             NOT NULL,
        CreateDate            DATETIME2(0)    NOT NULL CONSTRAINT DF_InvTrf_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_InvTrf_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedByUser        INT             NULL,

        CONSTRAINT PK_InvTransfer PRIMARY KEY CLUSTERED (TransferID),
        CONSTRAINT CK_InvTrf_NotSameFacility CHECK (FromFacilityCode <> ToFacilityCode),
        CONSTRAINT CK_InvTrf_FromBucket CHECK (
            FromStatusBucket IN (N'OK', N'Quarantine', N'OnHold', N'Destroy')
        ),
        CONSTRAINT CK_InvTrf_ToBucket CHECK (
            ToStatusBucket IN (N'OK', N'Quarantine', N'OnHold', N'Destroy')
        ),
        CONSTRAINT CK_InvTrf_Status CHECK (
            TransferStatus IN (
                N'Pending', N'InTransit', N'PartiallyReceived',
                N'Received', N'Cancelled'
            )
        ),
        CONSTRAINT CK_InvTrf_QtyPositive CHECK (QtyRequested > 0),
        CONSTRAINT FK_InvTrf_SKU FOREIGN KEY (SKUCode)
            REFERENCES dbo.SKUMaster (SKUCode),
        CONSTRAINT FK_InvTrf_FromFacility FOREIGN KEY (FromFacilityCode)
            REFERENCES dbo.Facility (FacilityCode),
        CONSTRAINT FK_InvTrf_ToFacility FOREIGN KEY (ToFacilityCode)
            REFERENCES dbo.Facility (FacilityCode),
        CONSTRAINT FK_InvTrf_ReasonCode FOREIGN KEY (ReasonCodeID)
            REFERENCES dbo.InvReasonCode (ReasonCodeID),
        CONSTRAINT FK_InvTrf_RequestedBy FOREIGN KEY (RequestedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_InvTrf_ModifiedBy FOREIGN KEY (ModifiedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_InvTrf_OutboundTxn FOREIGN KEY (OutboundTransactionID)
            REFERENCES dbo.InvTransaction (TransactionID),
        CONSTRAINT FK_InvTrf_InboundTxn FOREIGN KEY (InboundTransactionID)
            REFERENCES dbo.InvTransaction (TransactionID)
    );

    CREATE NONCLUSTERED INDEX IX_InvTransfer_SKU
        ON dbo.InvTransfer (SKUCode, TransferStatus);

    CREATE NONCLUSTERED INDEX IX_InvTransfer_FromFacility
        ON dbo.InvTransfer (FromFacilityCode, TransferDate DESC);

    CREATE NONCLUSTERED INDEX IX_InvTransfer_ToFacility
        ON dbo.InvTransfer (ToFacilityCode, TransferDate DESC);
END;
GO

IF OBJECT_ID(N'dbo.InvReturnReceipt', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvReturnReceipt (
        ReturnReceiptID        INT             NOT NULL IDENTITY(1,1),
        ReturnReceiptNumber    NVARCHAR(50)    NOT NULL,
        ReceiptDate            DATE            NOT NULL,
        AccsSalesOrderHeaderID INT             NULL,
        FacilityCode           NVARCHAR(50)    NOT NULL,
        ReturnReason           NVARCHAR(50)    NOT NULL,
        RRStatus               NVARCHAR(30)    NOT NULL CONSTRAINT DF_InvRR_Status DEFAULT (N'Draft'),
        Notes                  NVARCHAR(MAX)   NULL,
        CreatedByUser          INT             NOT NULL,
        CreateDate             DATETIME2(0)    NOT NULL CONSTRAINT DF_InvRR_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate           DATETIME2(0)    NOT NULL CONSTRAINT DF_InvRR_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedByUser         INT             NULL,

        CONSTRAINT PK_InvReturnReceipt PRIMARY KEY CLUSTERED (ReturnReceiptID),
        CONSTRAINT UQ_InvRR_Number UNIQUE (ReturnReceiptNumber),
        CONSTRAINT CK_InvRR_Reason CHECK (
            ReturnReason IN (
                N'CustomerService', N'MisOrder', N'MisDelivery',
                N'DamagedInDelivery', N'Quality', N'Other'
            )
        ),
        CONSTRAINT CK_InvRR_Status CHECK (
            RRStatus IN (N'Draft', N'Received', N'Closed')
        ),
        CONSTRAINT FK_InvRR_SalesOrder FOREIGN KEY (AccsSalesOrderHeaderID)
            REFERENCES dbo.AccsSalesOrderHeader (AccsSalesOrderHeaderID),
        CONSTRAINT FK_InvRR_Facility FOREIGN KEY (FacilityCode)
            REFERENCES dbo.Facility (FacilityCode),
        CONSTRAINT FK_InvRR_CreatedBy FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_InvRR_ModifiedBy FOREIGN KEY (ModifiedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_InvReturnReceipt_SalesOrder
        ON dbo.InvReturnReceipt (AccsSalesOrderHeaderID)
        WHERE AccsSalesOrderHeaderID IS NOT NULL;
END;
GO

IF OBJECT_ID(N'dbo.InvReturnReceiptLine', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvReturnReceiptLine (
        ReturnLineID           INT             NOT NULL IDENTITY(1,1),
        ReturnReceiptID        INT             NOT NULL,
        LineNumber             INT             NOT NULL,
        SKUCode                NVARCHAR(100)   NOT NULL,
        AccsSalesOrderDetailID INT             NULL,
        QtyReturned            DECIMAL(18,4)   NOT NULL,
        DispositionBucket      NVARCHAR(20)    NOT NULL,
        TransactionID          INT             NULL,
        Notes                  NVARCHAR(500)   NULL,

        CONSTRAINT PK_InvReturnReceiptLine PRIMARY KEY CLUSTERED (ReturnLineID),
        CONSTRAINT CK_InvRRL_Disposition CHECK (
            DispositionBucket IN (N'OK', N'Quarantine', N'OnHold', N'Destroy')
        ),
        CONSTRAINT CK_InvRRL_Qty CHECK (QtyReturned > 0),
        CONSTRAINT FK_InvRRL_Receipt FOREIGN KEY (ReturnReceiptID)
            REFERENCES dbo.InvReturnReceipt (ReturnReceiptID) ON DELETE CASCADE,
        CONSTRAINT FK_InvRRL_SKU FOREIGN KEY (SKUCode)
            REFERENCES dbo.SKUMaster (SKUCode),
        CONSTRAINT FK_InvRRL_SalesDetail FOREIGN KEY (AccsSalesOrderDetailID)
            REFERENCES dbo.AccsSalesOrderDetail (AccsSalesOrderDetailID),
        CONSTRAINT FK_InvRRL_Transaction FOREIGN KEY (TransactionID)
            REFERENCES dbo.InvTransaction (TransactionID)
    );
END;
GO

IF OBJECT_ID(N'dbo.InvCountSession', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvCountSession (
        CountSessionID  INT             NOT NULL IDENTITY(1,1),
        SessionNumber   NVARCHAR(50)    NOT NULL,
        FacilityCode    NVARCHAR(50)    NOT NULL,
        CountType       NVARCHAR(30)    NOT NULL,
        CountDate       DATE            NOT NULL,
        SessionStatus   NVARCHAR(30)    NOT NULL CONSTRAINT DF_InvCS_Status DEFAULT (N'Open'),
        Notes           NVARCHAR(MAX)   NULL,
        CountedByUser   INT             NOT NULL,
        ApprovedByUser  INT             NULL,
        ApprovedAt      DATETIME2(0)    NULL,
        CreatedByUser   INT             NOT NULL,
        CreateDate      DATETIME2(0)    NOT NULL CONSTRAINT DF_InvCS_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate    DATETIME2(0)    NOT NULL CONSTRAINT DF_InvCS_ModifiedDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_InvCountSession PRIMARY KEY CLUSTERED (CountSessionID),
        CONSTRAINT UQ_InvCS_Number UNIQUE (SessionNumber),
        CONSTRAINT CK_InvCS_Type CHECK (
            CountType IN (N'FullPhysical', N'CycleCount', N'SpotCheck')
        ),
        CONSTRAINT CK_InvCS_Status CHECK (
            SessionStatus IN (N'Open', N'InProgress', N'Reconciling', N'Closed', N'Cancelled')
        ),
        CONSTRAINT FK_InvCS_Facility FOREIGN KEY (FacilityCode)
            REFERENCES dbo.Facility (FacilityCode),
        CONSTRAINT FK_InvCS_CountedBy FOREIGN KEY (CountedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_InvCS_ApprovedBy FOREIGN KEY (ApprovedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_InvCS_CreatedBy FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID)
    );
END;
GO

IF OBJECT_ID(N'dbo.InvCountSessionDetail', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvCountSessionDetail (
        CountDetailID   INT             NOT NULL IDENTITY(1,1),
        CountSessionID  INT             NOT NULL,
        SKUCode         NVARCHAR(100)   NOT NULL,
        StatusBucket    NVARCHAR(20)    NOT NULL,
        QtySystem       DECIMAL(18,4)   NOT NULL,
        QtyPhysical     DECIMAL(18,4)   NULL,
        Variance        AS (QtyPhysical - QtySystem) PERSISTED,
        AdjustmentID    INT             NULL,
        Notes           NVARCHAR(500)   NULL,

        CONSTRAINT PK_InvCountSessionDetail PRIMARY KEY CLUSTERED (CountDetailID),
        CONSTRAINT UQ_InvCSD_Session_SKU_Bucket UNIQUE (CountSessionID, SKUCode, StatusBucket),
        CONSTRAINT CK_InvCSD_Bucket CHECK (
            StatusBucket IN (N'OK', N'Quarantine', N'OnHold', N'Destroy')
        ),
        CONSTRAINT FK_InvCSD_Session FOREIGN KEY (CountSessionID)
            REFERENCES dbo.InvCountSession (CountSessionID) ON DELETE CASCADE,
        CONSTRAINT FK_InvCSD_SKU FOREIGN KEY (SKUCode)
            REFERENCES dbo.SKUMaster (SKUCode),
        CONSTRAINT FK_InvCSD_Adjustment FOREIGN KEY (AdjustmentID)
            REFERENCES dbo.InvAdjustment (AdjustmentID)
    );

    CREATE NONCLUSTERED INDEX IX_InvCountSessionDetail_SessionID
        ON dbo.InvCountSessionDetail (CountSessionID, SKUCode);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_InvTransaction_Reference'
      AND object_id = OBJECT_ID(N'dbo.InvTransaction')
)
    CREATE NONCLUSTERED INDEX IX_InvTransaction_Reference
        ON dbo.InvTransaction (ReferenceType, ReferenceID);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_InvTransaction_Date'
      AND object_id = OBJECT_ID(N'dbo.InvTransaction')
)
    CREATE NONCLUSTERED INDEX IX_InvTransaction_Date
        ON dbo.InvTransaction (TransactionDate DESC);
GO

IF COL_LENGTH('dbo.InvAdjustment', 'CountSessionID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.foreign_keys
       WHERE name = N'FK_InvAdj_CountSession'
         AND parent_object_id = OBJECT_ID(N'dbo.InvAdjustment')
   )
BEGIN
    ALTER TABLE dbo.InvAdjustment
        ADD CONSTRAINT FK_InvAdj_CountSession FOREIGN KEY (CountSessionID)
            REFERENCES dbo.InvCountSession (CountSessionID);
END;
GO

DECLARE @SeedUserID INT;
SELECT @SeedUserID = MIN(UserID) FROM dbo.[User];

IF @SeedUserID IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'CART')
        INSERT INTO dbo.Facility (
            FacilityCode, FacilityName, FacilityType, IsActive, CreatedByUser
        )
        VALUES (N'CART', N'Cart.com', N'3PL', 1, @SeedUserID);

    IF NOT EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'CPPC')
        INSERT INTO dbo.Facility (
            FacilityCode, FacilityName, FacilityType, IsActive, CreatedByUser
        )
        VALUES (N'CPPC', N'CPPC', N'CPPC', 0, @SeedUserID);

    IF NOT EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'WLO')
        INSERT INTO dbo.Facility (
            FacilityCode, FacilityName, FacilityType, IsActive, CreatedByUser
        )
        VALUES (N'WLO', N'White Label Operations', N'Warehouse', 0, @SeedUserID);

    IF NOT EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'TRANSIT')
        INSERT INTO dbo.Facility (
            FacilityCode, FacilityName, FacilityType, IsActive, CreatedByUser
        )
        VALUES (N'TRANSIT', N'Transit', N'Transit', 1, @SeedUserID);
END;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'DAMAGE')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToAdjustment, DefaultDirection)
    VALUES (N'DAMAGE', N'Damaged goods write-off', 1, N'-');

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'COUNT_VAR')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToAdjustment, AppliesToCount, DefaultDirection)
    VALUES (N'COUNT_VAR', N'Cycle or physical count variance', 1, 1, NULL);

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'QUAR_RELEASE')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToAdjustment, DefaultDirection)
    VALUES (N'QUAR_RELEASE', N'Quarantine release to OK', 1, N'+');

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'OTHER_ADJ')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToAdjustment, DefaultDirection)
    VALUES (N'OTHER_ADJ', N'Other manual adjustment', 1, NULL);

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'PO_RECEIPT')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToReceipt, DefaultDirection)
    VALUES (N'PO_RECEIPT', N'Purchase order receipt', 1, N'+');

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'PROD_RECEIPT')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToReceipt, DefaultDirection)
    VALUES (N'PROD_RECEIPT', N'Production or inbound receipt', 1, N'+');

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'ORDER_SHIP')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToSale, DefaultDirection)
    VALUES (N'ORDER_SHIP', N'Customer order shipment', 1, N'-');

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'FAC_TRANSFER')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToTransfer)
    VALUES (N'FAC_TRANSFER', N'Facility transfer', 1);

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'REPLENISH')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToTransfer)
    VALUES (N'REPLENISH', N'Facility replenishment', 1);

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'CUST_RETURN')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToReturn, DefaultDirection)
    VALUES (N'CUST_RETURN', N'Customer return receipt', 1, N'+');

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'MISORDER')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToReturn, DefaultDirection)
    VALUES (N'MISORDER', N'Mis-order return', 1, N'+');

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'CYCLE_COUNT')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToCount)
    VALUES (N'CYCLE_COUNT', N'Cycle count session', 1);

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'FULL_PHYSICAL')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToCount)
    VALUES (N'FULL_PHYSICAL', N'Full physical inventory count', 1);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.InvCurrentBalance)
   AND OBJECT_ID(N'dbo.InventoryBalance', N'U') IS NOT NULL
   AND EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'CART')
BEGIN
    DECLARE @BootstrapUserID INT;
    DECLARE @OpeningTxnID INT;
    DECLARE @SnapshotAt DATETIME2(0);
    DECLARE @LineNumber INT = 0;

    SELECT @BootstrapUserID = MIN(UserID) FROM dbo.[User];
    SELECT @SnapshotAt = MAX(SnapshotDateTime) FROM dbo.InventoryBalance;

    IF @BootstrapUserID IS NOT NULL AND @SnapshotAt IS NOT NULL
    BEGIN
        INSERT INTO dbo.InvTransaction (
            TransactionType, ReferenceType, ReferenceID, Notes, CreatedByUser
        )
        VALUES (
            N'OpeningBalance',
            N'InventoryBalance',
            0,
            N'Opening balance bootstrap from latest InventoryBalance snapshot at ' + CONVERT(NVARCHAR(30), @SnapshotAt, 126),
            @BootstrapUserID
        );

        SET @OpeningTxnID = SCOPE_IDENTITY();

        INSERT INTO dbo.InvCurrentBalance (
            SKUCode, FacilityCode, QtyOK, LastTransactionID, LastUpdated
        )
        SELECT
            snap.SKU,
            N'CART',
            snap.OpeningQty,
            @OpeningTxnID,
            SYSUTCDATETIME()
        FROM (
            SELECT
                ib.SKU,
                SUM(ib.OnHandQuantity) AS OpeningQty
            FROM dbo.InventoryBalance ib
            WHERE ib.SnapshotDateTime = @SnapshotAt
              AND EXISTS (
                  SELECT 1 FROM dbo.SKUMaster sm WHERE sm.SKUCode = ib.SKU
              )
            GROUP BY ib.SKU
            HAVING SUM(ib.OnHandQuantity) > 0
        ) snap;

        INSERT INTO dbo.InvTransactionLine (
            TransactionID, LineNumber, SKUCode, FacilityCode,
            StatusBucket, QtyChange, QtyBefore, QtyAfter
        )
        SELECT
            @OpeningTxnID,
            ROW_NUMBER() OVER (ORDER BY b.SKUCode),
            b.SKUCode,
            b.FacilityCode,
            N'OK',
            b.QtyOK,
            0,
            b.QtyOK
        FROM dbo.InvCurrentBalance b
        WHERE b.FacilityCode = N'CART';
    END;
END;
GO
