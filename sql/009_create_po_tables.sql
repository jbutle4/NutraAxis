/*
  NutraAxis Operations — Purchase Order Management schema
*/

IF OBJECT_ID(N'dbo.Supplier', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.Supplier (
        SupplierID      INT             NOT NULL IDENTITY(1,1),
        SupplierName    NVARCHAR(200)   NOT NULL,
        SupplierCode    NVARCHAR(50)    NULL,
        ContactName     NVARCHAR(200)   NULL,
        ContactEmail    NVARCHAR(200)   NULL,
        ContactPhone    NVARCHAR(50)    NULL,
        IsActive        BIT             NOT NULL CONSTRAINT DF_Supplier_IsActive DEFAULT (1),
        QBO_SupplierID  NVARCHAR(32)    NULL,
        CreateDate      DATETIME2(0)    NOT NULL CONSTRAINT DF_Supplier_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate    DATETIME2(0)    NOT NULL CONSTRAINT DF_Supplier_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser  INT             NULL,

        CONSTRAINT PK_Supplier PRIMARY KEY CLUSTERED (SupplierID),
        CONSTRAINT UQ_Supplier_SupplierCode UNIQUE (SupplierCode),
        CONSTRAINT FK_Supplier_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID)
    );
END;
GO

IF OBJECT_ID(N'dbo.PurchaseOrder', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.PurchaseOrder (
        POID                    INT             NOT NULL IDENTITY(1,1),
        PONumber                NVARCHAR(50)    NOT NULL,
        SupplierID              INT             NOT NULL,
        POStatus                NVARCHAR(20)    NOT NULL CONSTRAINT DF_PurchaseOrder_POStatus DEFAULT (N'Draft'),
        OrderDate               DATE            NOT NULL,
        ExpectedDeliveryDate    DATE            NULL,
        Notes                   NVARCHAR(MAX)   NULL,
        Subtotal                DECIMAL(18,2)   NOT NULL CONSTRAINT DF_PurchaseOrder_Subtotal DEFAULT (0),
        CreatedByUser           INT             NOT NULL,
        CreateDate              DATETIME2(0)    NOT NULL CONSTRAINT DF_PurchaseOrder_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate            DATETIME2(0)    NOT NULL CONSTRAINT DF_PurchaseOrder_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser          INT             NULL,
        QBO_POID                NVARCHAR(32)    NULL,
        POQBOCreated            BIT             NOT NULL CONSTRAINT DF_PurchaseOrder_POQBOCreated DEFAULT (0),

        CONSTRAINT PK_PurchaseOrder PRIMARY KEY CLUSTERED (POID),
        CONSTRAINT UQ_PurchaseOrder_PONumber UNIQUE (PONumber),
        CONSTRAINT CK_PurchaseOrder_POStatus CHECK (
            POStatus IN (N'Draft', N'Submitted', N'Approved', N'Received', N'Cancelled')
        ),
        CONSTRAINT FK_PurchaseOrder_Supplier FOREIGN KEY (SupplierID)
            REFERENCES dbo.Supplier (SupplierID),
        CONSTRAINT FK_PurchaseOrder_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_PurchaseOrder_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_PurchaseOrder_SupplierID
        ON dbo.PurchaseOrder (SupplierID);

    CREATE NONCLUSTERED INDEX IX_PurchaseOrder_POStatus
        ON dbo.PurchaseOrder (POStatus);

    CREATE NONCLUSTERED INDEX IX_PurchaseOrder_OrderDate
        ON dbo.PurchaseOrder (OrderDate DESC);

    CREATE NONCLUSTERED INDEX IX_PurchaseOrder_QBO_POID
        ON dbo.PurchaseOrder (QBO_POID)
        WHERE QBO_POID IS NOT NULL;

    CREATE NONCLUSTERED INDEX IX_Supplier_QBO_SupplierID
        ON dbo.Supplier (QBO_SupplierID)
        WHERE QBO_SupplierID IS NOT NULL;
END;
GO

IF OBJECT_ID(N'dbo.POLineItem', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.POLineItem (
        POLineID            INT             NOT NULL IDENTITY(1,1),
        POID                INT             NOT NULL,
        LineNumber          INT             NOT NULL,
        ItemSKU             NVARCHAR(100)   NULL,
        ItemDescription     NVARCHAR(500)   NOT NULL,
        Quantity            DECIMAL(18,4)   NOT NULL,
        UnitPrice           DECIMAL(18,4)   NOT NULL,
        LineTotal           AS (Quantity * UnitPrice) PERSISTED,
        QuantityReceived    DECIMAL(18,4)   NOT NULL CONSTRAINT DF_POLineItem_QtyReceived DEFAULT (0),

        CONSTRAINT PK_POLineItem PRIMARY KEY CLUSTERED (POLineID),
        CONSTRAINT FK_POLineItem_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID) ON DELETE CASCADE,
        CONSTRAINT CK_POLineItem_Quantity CHECK (Quantity > 0),
        CONSTRAINT CK_POLineItem_UnitPrice CHECK (UnitPrice >= 0)
    );

    CREATE NONCLUSTERED INDEX IX_POLineItem_POID
        ON dbo.POLineItem (POID);
END;
GO
