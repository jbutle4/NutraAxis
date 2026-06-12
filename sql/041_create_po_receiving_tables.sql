/*
  NutraAxis Operations — PO Receiving (ASN) tables
*/

IF OBJECT_ID(N'dbo.POReceipt', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.POReceipt (
        PORID                   INT             NOT NULL IDENTITY(1,1),
        POID                    INT             NOT NULL,
        PONumber                NVARCHAR(50)    NOT NULL,
        ExpectedDate            DATE            NULL,
        ScheduledReceiptDate    DATE            NULL,
        ScheduledReceiptTime    TIME(0)         NULL,
        AppointmentMade         BIT             NOT NULL CONSTRAINT DF_POReceipt_AppointmentMade DEFAULT (0),
        ActualReceiptDate       DATE            NULL,
        DeliveryAddress         NVARCHAR(500)   NULL,
        PORStatus               NVARCHAR(30)    NOT NULL CONSTRAINT DF_POReceipt_Status DEFAULT (N'Draft'),
        JazzASN                 NVARCHAR(50)    NULL,
        PORNotes                NVARCHAR(MAX)   NULL,
        CreatedBy               NVARCHAR(200)   NULL,
        ModifiedBy              NVARCHAR(200)   NULL,
        CreateDate              DATETIME2(0)    NOT NULL CONSTRAINT DF_POReceipt_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate            DATETIME2(0)    NOT NULL CONSTRAINT DF_POReceipt_ModifiedDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_POReceipt PRIMARY KEY CLUSTERED (PORID),
        CONSTRAINT FK_POReceipt_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID),
        CONSTRAINT CK_POReceipt_Status CHECK (
            PORStatus IN (N'Draft', N'Scheduled', N'Transmitted', N'Complete', N'Cancelled')
        )
    );

    CREATE NONCLUSTERED INDEX IX_POReceipt_POID
        ON dbo.POReceipt (POID);

    CREATE NONCLUSTERED INDEX IX_POReceipt_PORStatus
        ON dbo.POReceipt (PORStatus);

    CREATE NONCLUSTERED INDEX IX_POReceipt_ScheduledReceiptDate
        ON dbo.POReceipt (ScheduledReceiptDate)
        WHERE ScheduledReceiptDate IS NOT NULL;
END;
GO

IF OBJECT_ID(N'dbo.PORDetail', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.PORDetail (
        PORDID              INT             NOT NULL IDENTITY(1,1),
        PORID               INT             NOT NULL,
        POLineID            INT             NOT NULL,
        ItemSKU             NVARCHAR(100)   NULL,
        ItemDescription     NVARCHAR(500)   NOT NULL,
        QuantityExpected    DECIMAL(18,4)   NOT NULL,
        QuantityReceived    DECIMAL(18,4)   NOT NULL CONSTRAINT DF_PORDetail_QtyReceived DEFAULT (0),
        LINote              NVARCHAR(250)   NULL,

        CONSTRAINT PK_PORDetail PRIMARY KEY CLUSTERED (PORDID),
        CONSTRAINT FK_PORDetail_POReceipt FOREIGN KEY (PORID)
            REFERENCES dbo.POReceipt (PORID) ON DELETE CASCADE,
        CONSTRAINT FK_PORDetail_POLineItem FOREIGN KEY (POLineID)
            REFERENCES dbo.POLineItem (POLineID),
        CONSTRAINT CK_PORDetail_QuantityExpected CHECK (QuantityExpected >= 0),
        CONSTRAINT CK_PORDetail_QuantityReceived CHECK (QuantityReceived >= 0)
    );

    CREATE NONCLUSTERED INDEX IX_PORDetail_PORID
        ON dbo.PORDetail (PORID);

    CREATE NONCLUSTERED INDEX IX_PORDetail_POLineID
        ON dbo.PORDetail (POLineID);
END;
GO

IF OBJECT_ID(N'dbo.PORAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.PORAttachment (
        AttachmentID    INT             NOT NULL IDENTITY(1,1),
        PORID           INT             NOT NULL,
        FileName        NVARCHAR(255)   NOT NULL,
        ContentType     NVARCHAR(100)   NOT NULL,
        FileSizeBytes   INT             NOT NULL,
        FileData        VARBINARY(MAX)  NOT NULL,
        AttachmentKind  NVARCHAR(30)    NOT NULL CONSTRAINT DF_PORAttachment_Kind DEFAULT (N'Other'),
        UploadedByUser  INT             NOT NULL,
        UploadDate      DATETIME2(0)    NOT NULL CONSTRAINT DF_PORAttachment_UploadDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_PORAttachment PRIMARY KEY CLUSTERED (AttachmentID),
        CONSTRAINT CK_PORAttachment_Kind CHECK (
            AttachmentKind IN (
                N'ASN',
                N'BOL',
                N'PackingSlip',
                N'Photo',
                N'Other'
            )
        ),
        CONSTRAINT FK_PORAttachment_POReceipt FOREIGN KEY (PORID)
            REFERENCES dbo.POReceipt (PORID) ON DELETE CASCADE,
        CONSTRAINT FK_PORAttachment_UploadedByUser FOREIGN KEY (UploadedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_PORAttachment_PORID
        ON dbo.PORAttachment (PORID);
END;
GO

CREATE OR ALTER TRIGGER dbo.TR_PORDetail_SyncPOLineItemQtyReceived
ON dbo.PORDetail
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @lines TABLE (POLineID INT PRIMARY KEY);

    INSERT INTO @lines (POLineID)
    SELECT DISTINCT POLineID FROM inserted
    UNION
    SELECT DISTINCT POLineID FROM deleted;

    UPDATE li
    SET QuantityReceived = ISNULL(totals.TotalReceived, 0)
    FROM dbo.POLineItem li
    INNER JOIN @lines affected ON affected.POLineID = li.POLineID
    LEFT JOIN (
        SELECT d.POLineID, SUM(d.QuantityReceived) AS TotalReceived
        FROM dbo.PORDetail d
        INNER JOIN dbo.POReceipt r ON r.PORID = d.PORID
        GROUP BY d.POLineID
    ) totals ON totals.POLineID = li.POLineID;
END;
GO
