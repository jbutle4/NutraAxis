/*
  NutraAxis Operations — Supplier invoices for QuickBooks Online Bill sync

  Maps to QBO Bill entity. Required to create a Bill in QBO:
    - VendorRef.value
    - Line[] with Amount, DetailType, and either:
        AccountBasedExpenseLineDetail.AccountRef.value, or
        ItemBasedExpenseLineDetail.ItemRef.value (+ Qty/UnitPrice as needed)
*/

IF OBJECT_ID(N'dbo.SupplierInvoice', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SupplierInvoice (
        SupplierInvoiceID       INT             NOT NULL IDENTITY(1,1),
        SupplierID              INT             NOT NULL,
        POID                    INT             NULL,
        DocNumber               NVARCHAR(21)    NULL,
        TxnDate                 DATE            NOT NULL,
        DueDate                 DATE            NULL,
        VendorRefValue          NVARCHAR(32)    NOT NULL,
        VendorRefName           NVARCHAR(200)   NULL,
        APAccountRefValue       NVARCHAR(32)    NULL,
        APAccountRefName        NVARCHAR(200)   NULL,
        CurrencyRefValue        NVARCHAR(10)    NULL,
        CurrencyRefName         NVARCHAR(50)    NULL,
        ExchangeRate            DECIMAL(18,6)   NULL,
        GlobalTaxCalculation    NVARCHAR(20)    NULL,
        PrivateNote             NVARCHAR(4000)  NULL,
        Memo                    NVARCHAR(4000)  NULL,
        TotalAmt                DECIMAL(18,2)   NOT NULL CONSTRAINT DF_SupplierInvoice_TotalAmt DEFAULT (0),
        Balance                 DECIMAL(18,2)   NULL,
        DepartmentRefValue      NVARCHAR(32)    NULL,
        DepartmentRefName       NVARCHAR(200)   NULL,
        QBO_BillId              NVARCHAR(32)    NULL,
        QBO_SyncToken           NVARCHAR(32)    NULL,
        QBO_RealmId             NVARCHAR(32)    NULL,
        SyncStatus              NVARCHAR(30)    NOT NULL CONSTRAINT DF_SupplierInvoice_SyncStatus DEFAULT (N'Draft'),
        LastSyncError           NVARCHAR(1000)  NULL,
        LastSyncAt              DATETIME2(0)    NULL,
        CreatedByUser           INT             NULL,
        CreateDate              DATETIME2(0)    NOT NULL CONSTRAINT DF_SupplierInvoice_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate            DATETIME2(0)    NOT NULL CONSTRAINT DF_SupplierInvoice_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedByUser          INT             NULL,

        CONSTRAINT PK_SupplierInvoice PRIMARY KEY CLUSTERED (SupplierInvoiceID),
        CONSTRAINT FK_SupplierInvoice_Supplier FOREIGN KEY (SupplierID)
            REFERENCES dbo.Supplier (SupplierID),
        CONSTRAINT FK_SupplierInvoice_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID),
        CONSTRAINT FK_SupplierInvoice_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_SupplierInvoice_ModifiedByUser FOREIGN KEY (ModifiedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_SupplierInvoice_SyncStatus CHECK (
            SyncStatus IN (N'Draft', N'Ready', N'Posted', N'Failed', N'Voided')
        ),
        CONSTRAINT CK_SupplierInvoice_GlobalTaxCalculation CHECK (
            GlobalTaxCalculation IS NULL
            OR GlobalTaxCalculation IN (N'TaxExcluded', N'TaxInclusive', N'NotApplicable')
        ),
        CONSTRAINT CK_SupplierInvoice_TotalAmt CHECK (TotalAmt >= 0)
    );

    CREATE NONCLUSTERED INDEX IX_SupplierInvoice_SupplierID
        ON dbo.SupplierInvoice (SupplierID);

    CREATE NONCLUSTERED INDEX IX_SupplierInvoice_POID
        ON dbo.SupplierInvoice (POID)
        WHERE POID IS NOT NULL;

    CREATE NONCLUSTERED INDEX IX_SupplierInvoice_TxnDate
        ON dbo.SupplierInvoice (TxnDate DESC);

    CREATE NONCLUSTERED INDEX IX_SupplierInvoice_SyncStatus
        ON dbo.SupplierInvoice (SyncStatus);

    CREATE NONCLUSTERED INDEX IX_SupplierInvoice_QBO_BillId
        ON dbo.SupplierInvoice (QBO_BillId)
        WHERE QBO_BillId IS NOT NULL;

    CREATE NONCLUSTERED INDEX IX_SupplierInvoice_DocNumber
        ON dbo.SupplierInvoice (DocNumber)
        WHERE DocNumber IS NOT NULL;
END;
GO

IF OBJECT_ID(N'dbo.SupplierInvoiceLine', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SupplierInvoiceLine (
        SupplierInvoiceLineID   INT             NOT NULL IDENTITY(1,1),
        SupplierInvoiceID       INT             NOT NULL,
        LineNumber              INT             NOT NULL,
        QBO_LineId              NVARCHAR(32)    NULL,
        Description             NVARCHAR(4000)  NULL,
        Amount                  DECIMAL(18,2)   NOT NULL,
        DetailType              NVARCHAR(40)    NOT NULL,
        AccountRefValue         NVARCHAR(32)    NULL,
        AccountRefName          NVARCHAR(200)   NULL,
        ItemRefValue            NVARCHAR(32)    NULL,
        ItemRefName             NVARCHAR(200)   NULL,
        Qty                     DECIMAL(18,4)   NULL,
        UnitPrice               DECIMAL(18,4)   NULL,
        TaxCodeRefValue         NVARCHAR(32)    NULL,
        TaxCodeRefName          NVARCHAR(100)   NULL,
        BillableStatus          NVARCHAR(20)    NULL,
        CustomerRefValue        NVARCHAR(32)    NULL,
        CustomerRefName         NVARCHAR(200)   NULL,
        ClassRefValue           NVARCHAR(32)    NULL,
        ClassRefName            NVARCHAR(200)   NULL,
        POLineID                INT             NULL,

        CONSTRAINT PK_SupplierInvoiceLine PRIMARY KEY CLUSTERED (SupplierInvoiceLineID),
        CONSTRAINT FK_SupplierInvoiceLine_SupplierInvoice FOREIGN KEY (SupplierInvoiceID)
            REFERENCES dbo.SupplierInvoice (SupplierInvoiceID) ON DELETE CASCADE,
        CONSTRAINT FK_SupplierInvoiceLine_POLineItem FOREIGN KEY (POLineID)
            REFERENCES dbo.POLineItem (POLineID),
        CONSTRAINT UQ_SupplierInvoiceLine_LineNumber UNIQUE (SupplierInvoiceID, LineNumber),
        CONSTRAINT CK_SupplierInvoiceLine_DetailType CHECK (
            DetailType IN (N'AccountBasedExpenseLineDetail', N'ItemBasedExpenseLineDetail')
        ),
        CONSTRAINT CK_SupplierInvoiceLine_Amount CHECK (Amount >= 0),
        CONSTRAINT CK_SupplierInvoiceLine_BillableStatus CHECK (
            BillableStatus IS NULL
            OR BillableStatus IN (N'Billable', N'NotBillable', N'HasBeenBilled')
        )
    );

    CREATE NONCLUSTERED INDEX IX_SupplierInvoiceLine_SupplierInvoiceID
        ON dbo.SupplierInvoiceLine (SupplierInvoiceID, LineNumber);

    CREATE NONCLUSTERED INDEX IX_SupplierInvoiceLine_POLineID
        ON dbo.SupplierInvoiceLine (POLineID)
        WHERE POLineID IS NOT NULL;
END;
GO
