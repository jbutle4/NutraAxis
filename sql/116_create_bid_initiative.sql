/*
  NutraAxis Operations — Procurement Initiatives & Bids (non-PO estimates)
*/

IF OBJECT_ID(N'dbo.BidInitiative', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.BidInitiative (
        InitiativeID        INT             NOT NULL IDENTITY(1,1),
        InitiativeNumber    NVARCHAR(30)    NOT NULL,
        Title               NVARCHAR(200)   NOT NULL,
        Description         NVARCHAR(MAX)   NULL,
        Category            NVARCHAR(50)    NULL,
        OwnerUserID         INT             NULL,
        TargetAwardDate     DATE            NULL,
        BudgetAmount        DECIMAL(18,2)   NULL,
        Status              NVARCHAR(30)    NOT NULL CONSTRAINT DF_BidInitiative_Status DEFAULT (N'Draft'),
        CreatedByUser       INT             NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_BidInitiative_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate        DATETIME2(0)    NOT NULL CONSTRAINT DF_BidInitiative_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedByUser      INT             NULL,

        CONSTRAINT PK_BidInitiative PRIMARY KEY CLUSTERED (InitiativeID),
        CONSTRAINT UQ_BidInitiative_Number UNIQUE (InitiativeNumber),
        CONSTRAINT FK_BidInitiative_Owner FOREIGN KEY (OwnerUserID)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_BidInitiative_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_BidInitiative_ModifiedByUser FOREIGN KEY (ModifiedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_BidInitiative_Status CHECK (
            Status IN (
                N'Draft',
                N'Open for Bids',
                N'Under Review',
                N'Awarded',
                N'Cancelled',
                N'Closed'
            )
        ),
        CONSTRAINT CK_BidInitiative_BudgetAmount CHECK (
            BudgetAmount IS NULL OR BudgetAmount >= 0
        )
    );

    CREATE NONCLUSTERED INDEX IX_BidInitiative_Status
        ON dbo.BidInitiative (Status, ModifiedDate DESC);

    CREATE NONCLUSTERED INDEX IX_BidInitiative_OwnerUserID
        ON dbo.BidInitiative (OwnerUserID)
        WHERE OwnerUserID IS NOT NULL;
END;
GO

IF OBJECT_ID(N'dbo.BidEstimate', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.BidEstimate (
        BidEstimateID               INT             NOT NULL IDENTITY(1,1),
        InitiativeID                INT             NOT NULL,
        SupplierID                  INT             NULL,
        VendorName                  NVARCHAR(200)   NOT NULL,
        ContactName                 NVARCHAR(200)   NULL,
        ContactEmail                NVARCHAR(200)   NULL,
        ContactPhone                NVARCHAR(50)    NULL,
        BidAmount                   DECIMAL(18,2)   NOT NULL,
        CurrencyCode                NVARCHAR(10)    NOT NULL CONSTRAINT DF_BidEstimate_Currency DEFAULT (N'USD'),
        SubmittedDate               DATE            NULL,
        ValidUntil                  DATE            NULL,
        Notes                       NVARCHAR(MAX)   NULL,
        Status                      NVARCHAR(30)    NOT NULL CONSTRAINT DF_BidEstimate_Status DEFAULT (N'Received'),
        AwardedSupplierInvoiceID    INT             NULL,
        CreatedByUser               INT             NULL,
        CreateDate                  DATETIME2(0)    NOT NULL CONSTRAINT DF_BidEstimate_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate                DATETIME2(0)    NOT NULL CONSTRAINT DF_BidEstimate_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedByUser              INT             NULL,

        CONSTRAINT PK_BidEstimate PRIMARY KEY CLUSTERED (BidEstimateID),
        CONSTRAINT FK_BidEstimate_Initiative FOREIGN KEY (InitiativeID)
            REFERENCES dbo.BidInitiative (InitiativeID) ON DELETE CASCADE,
        CONSTRAINT FK_BidEstimate_Supplier FOREIGN KEY (SupplierID)
            REFERENCES dbo.Supplier (SupplierID),
        CONSTRAINT FK_BidEstimate_SupplierInvoice FOREIGN KEY (AwardedSupplierInvoiceID)
            REFERENCES dbo.SupplierInvoice (SupplierInvoiceID),
        CONSTRAINT FK_BidEstimate_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_BidEstimate_ModifiedByUser FOREIGN KEY (ModifiedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_BidEstimate_Status CHECK (
            Status IN (
                N'Received',
                N'Under Review',
                N'Selected',
                N'Not Selected',
                N'Withdrawn'
            )
        ),
        CONSTRAINT CK_BidEstimate_BidAmount CHECK (BidAmount >= 0)
    );

    CREATE NONCLUSTERED INDEX IX_BidEstimate_InitiativeID
        ON dbo.BidEstimate (InitiativeID, Status);

    CREATE NONCLUSTERED INDEX IX_BidEstimate_SupplierID
        ON dbo.BidEstimate (SupplierID)
        WHERE SupplierID IS NOT NULL;

    CREATE UNIQUE NONCLUSTERED INDEX UQ_BidEstimate_OneSelectedPerInitiative
        ON dbo.BidEstimate (InitiativeID)
        WHERE Status = N'Selected';
END;
GO

IF OBJECT_ID(N'dbo.BidEstimateAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.BidEstimateAttachment (
        AttachmentID        INT             NOT NULL IDENTITY(1,1),
        BidEstimateID       INT             NOT NULL,
        FileName            NVARCHAR(255)   NOT NULL,
        ContentType         NVARCHAR(100)   NOT NULL,
        FileSizeBytes       INT             NOT NULL,
        FileData            VARBINARY(MAX)  NULL,
        BlobPath            NVARCHAR(512)   NULL,
        AttachmentKind      NVARCHAR(30)    NOT NULL CONSTRAINT DF_BidEstimateAttachment_Kind DEFAULT (N'Estimate'),
        UploadedByUser      INT             NOT NULL,
        UploadDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_BidEstimateAttachment_UploadDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_BidEstimateAttachment PRIMARY KEY CLUSTERED (AttachmentID),
        CONSTRAINT CK_BidEstimateAttachment_FileSizeBytes CHECK (FileSizeBytes >= 0),
        CONSTRAINT CK_BidEstimateAttachment_Kind CHECK (
            AttachmentKind IN (N'Estimate', N'Invoice', N'Quote', N'Supporting', N'Other')
        ),
        CONSTRAINT FK_BidEstimateAttachment_BidEstimate FOREIGN KEY (BidEstimateID)
            REFERENCES dbo.BidEstimate (BidEstimateID) ON DELETE CASCADE,
        CONSTRAINT FK_BidEstimateAttachment_UploadedByUser FOREIGN KEY (UploadedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_BidEstimateAttachment_BidEstimateID
        ON dbo.BidEstimateAttachment (BidEstimateID, UploadDate DESC);
END;
GO
