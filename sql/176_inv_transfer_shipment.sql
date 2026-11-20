-- Facility transfer shipment header (one order) + InvTransfer detail lines.

IF OBJECT_ID(N'dbo.InvTransferShipment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvTransferShipment (
        ShipmentID            INT             NOT NULL IDENTITY(1,1),
        LedgerProfile         NVARCHAR(20)    NOT NULL,
        FromFacilityCode      NVARCHAR(50)    NOT NULL,
        ToFacilityCode        NVARCHAR(50)    NOT NULL,
        FromStatusBucket      NVARCHAR(20)    NOT NULL CONSTRAINT DF_InvTrfShip_FromBucket DEFAULT (N'OK'),
        ToStatusBucket        NVARCHAR(20)    NOT NULL CONSTRAINT DF_InvTrfShip_ToBucket DEFAULT (N'OK'),
        ReasonCodeID          INT             NULL,
        SupplierID            INT             NULL,
        Notes                 NVARCHAR(MAX)   NULL,
        TransferStatus        NVARCHAR(30)    NOT NULL CONSTRAINT DF_InvTrfShip_Status DEFAULT (N'Pending'),
        JazzShipByDate        DATE            NULL,
        JazzShipmentNumber    NVARCHAR(50)    NULL,
        JazzSendStatus        NVARCHAR(20)    NULL,
        JazzSendError         NVARCHAR(500)   NULL,
        JazzAsnId             NVARCHAR(50)    NULL,
        JazzAsnStatus         NVARCHAR(30)    NULL,
        JazzSentAt            DATETIME2(0)    NULL,
        RequestedByUser       INT             NOT NULL,
        CreateDate            DATETIME2(0)    NOT NULL CONSTRAINT DF_InvTrfShip_Create DEFAULT (SYSUTCDATETIME()),
        ModifiedDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_InvTrfShip_Modified DEFAULT (SYSUTCDATETIME()),
        ModifiedByUser        INT             NULL,

        CONSTRAINT PK_InvTransferShipment PRIMARY KEY CLUSTERED (ShipmentID),
        CONSTRAINT FK_InvTrfShip_FromFacility FOREIGN KEY (FromFacilityCode)
            REFERENCES dbo.Facility (FacilityCode),
        CONSTRAINT FK_InvTrfShip_ToFacility FOREIGN KEY (ToFacilityCode)
            REFERENCES dbo.Facility (FacilityCode),
        CONSTRAINT FK_InvTrfShip_Reason FOREIGN KEY (ReasonCodeID)
            REFERENCES dbo.InvReasonCode (ReasonCodeID),
        CONSTRAINT FK_InvTrfShip_User FOREIGN KEY (RequestedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_InvTrfShip_Status CHECK (
            TransferStatus IN (N'Pending', N'InTransit', N'PartiallyReceived', N'Received', N'Cancelled')
        )
    );
END
GO

IF COL_LENGTH('dbo.InvTransfer', 'ShipmentID') IS NULL
    ALTER TABLE dbo.InvTransfer ADD ShipmentID INT NULL;
GO

IF COL_LENGTH('dbo.InvTransfer', 'LineNumber') IS NULL
    ALTER TABLE dbo.InvTransfer ADD LineNumber INT NULL;
GO

IF COL_LENGTH('dbo.InvTransfer', 'ShipmentID') IS NOT NULL
   AND OBJECT_ID(N'dbo.FK_InvTransfer_Shipment', N'F') IS NULL
    ALTER TABLE dbo.InvTransfer
        ADD CONSTRAINT FK_InvTransfer_Shipment FOREIGN KEY (ShipmentID)
            REFERENCES dbo.InvTransferShipment (ShipmentID);
GO