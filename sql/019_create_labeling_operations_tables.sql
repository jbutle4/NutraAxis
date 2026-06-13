/*
  NutraAxis Operations — Labeling Operations schema
*/

IF OBJECT_ID(N'dbo.LabelTemplate', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.LabelTemplate (
        TemplateID          INT             NOT NULL IDENTITY(1,1),
        LabelScope          NVARCHAR(20)    NOT NULL,
        CustomerName        NVARCHAR(200)   NULL,
        SKU                 NVARCHAR(100)   NOT NULL,
        LabelName           NVARCHAR(200)   NOT NULL,
        TemplateStatus      NVARCHAR(30)    NOT NULL CONSTRAINT DF_LabelTemplate_Status DEFAULT (N'Active'),
        CurrentVersionNo    NVARCHAR(20)    NULL,
        Notes               NVARCHAR(MAX)   NULL,
        CreatedByUser       INT             NOT NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_LabelTemplate_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate        DATETIME2(0)    NOT NULL CONSTRAINT DF_LabelTemplate_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser      INT             NULL,

        CONSTRAINT PK_LabelTemplate PRIMARY KEY CLUSTERED (TemplateID),
        CONSTRAINT CK_LabelTemplate_Scope CHECK (LabelScope IN (N'Customer', N'Internal')),
        CONSTRAINT CK_LabelTemplate_Status CHECK (
            TemplateStatus IN (N'Active', N'Draft', N'Retired')
        ),
        CONSTRAINT FK_LabelTemplate_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_LabelTemplate_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE UNIQUE NONCLUSTERED INDEX UX_LabelTemplate_CustomerSku
        ON dbo.LabelTemplate (CustomerName, SKU)
        WHERE LabelScope = N'Customer';

    CREATE UNIQUE NONCLUSTERED INDEX UX_LabelTemplate_InternalSku
        ON dbo.LabelTemplate (SKU)
        WHERE LabelScope = N'Internal';

    CREATE NONCLUSTERED INDEX IX_LabelTemplate_SKU
        ON dbo.LabelTemplate (SKU);
END;
GO

IF OBJECT_ID(N'dbo.LabelTemplateVersion', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.LabelTemplateVersion (
        VersionID           INT             NOT NULL IDENTITY(1,1),
        TemplateID          INT             NOT NULL,
        VersionNumber       NVARCHAR(20)    NOT NULL,
        RevisionNotes       NVARCHAR(MAX)   NULL,
        VersionStatus       NVARCHAR(30)    NOT NULL CONSTRAINT DF_LabelTemplateVersion_Status DEFAULT (N'Draft'),
        EffectiveDate       DATE            NULL,
        ApprovedByUser      INT             NULL,
        ApprovedDate        DATETIME2(0)    NULL,
        CreatedByUser       INT             NOT NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_LabelTemplateVersion_CreateDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_LabelTemplateVersion PRIMARY KEY CLUSTERED (VersionID),
        CONSTRAINT CK_LabelTemplateVersion_Status CHECK (
            VersionStatus IN (N'Draft', N'Approved', N'Superseded')
        ),
        CONSTRAINT FK_LabelTemplateVersion_Template FOREIGN KEY (TemplateID)
            REFERENCES dbo.LabelTemplate (TemplateID) ON DELETE CASCADE,
        CONSTRAINT FK_LabelTemplateVersion_ApprovedByUser FOREIGN KEY (ApprovedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_LabelTemplateVersion_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_LabelTemplateVersion_TemplateID
        ON dbo.LabelTemplateVersion (TemplateID, CreateDate DESC);
END;
GO

IF OBJECT_ID(N'dbo.LabelOrderRun', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.LabelOrderRun (
        RunID               INT             NOT NULL IDENTITY(1,1),
        RunNumber           NVARCHAR(50)    NOT NULL,
        RunStatus           NVARCHAR(30)    NOT NULL CONSTRAINT DF_LabelOrderRun_Status DEFAULT (N'Planned'),
        RunDate             DATE            NOT NULL,
        Notes               NVARCHAR(MAX)   NULL,
        CreatedByUser       INT             NOT NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_LabelOrderRun_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate        DATETIME2(0)    NOT NULL CONSTRAINT DF_LabelOrderRun_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser      INT             NULL,

        CONSTRAINT PK_LabelOrderRun PRIMARY KEY CLUSTERED (RunID),
        CONSTRAINT UQ_LabelOrderRun_RunNumber UNIQUE (RunNumber),
        CONSTRAINT CK_LabelOrderRun_Status CHECK (
            RunStatus IN (N'Planned', N'In Progress', N'Completed', N'Cancelled')
        ),
        CONSTRAINT FK_LabelOrderRun_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_LabelOrderRun_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID)
    );
END;
GO

IF OBJECT_ID(N'dbo.BatchPrintOrder', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.BatchPrintOrder (
        PrintOrderID        INT             NOT NULL IDENTITY(1,1),
        RunID               INT             NOT NULL,
        VendorName          NVARCHAR(200)   NOT NULL,
        VendorOrderNumber   NVARCHAR(100)   NULL,
        OrderStatus         NVARCHAR(30)    NOT NULL CONSTRAINT DF_BatchPrintOrder_Status DEFAULT (N'Ordered'),
        OrderDate           DATE            NOT NULL,
        ExpectedDeliveryDate DATE           NULL,
        Notes               NVARCHAR(MAX)   NULL,
        CreatedByUser       INT             NOT NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_BatchPrintOrder_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate        DATETIME2(0)    NOT NULL CONSTRAINT DF_BatchPrintOrder_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser      INT             NULL,

        CONSTRAINT PK_BatchPrintOrder PRIMARY KEY CLUSTERED (PrintOrderID),
        CONSTRAINT CK_BatchPrintOrder_Status CHECK (
            OrderStatus IN (N'Ordered', N'In Production', N'Shipped', N'Received', N'Cancelled')
        ),
        CONSTRAINT FK_BatchPrintOrder_Run FOREIGN KEY (RunID)
            REFERENCES dbo.LabelOrderRun (RunID),
        CONSTRAINT FK_BatchPrintOrder_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_BatchPrintOrder_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_BatchPrintOrder_RunID
        ON dbo.BatchPrintOrder (RunID);
END;
GO

IF OBJECT_ID(N'dbo.LabelComplianceReview', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.LabelComplianceReview (
        ReviewID            INT             NOT NULL IDENTITY(1,1),
        ReviewSubject       NVARCHAR(40)    NOT NULL,
        SubjectID           INT             NOT NULL,
        ReviewStatus        NVARCHAR(30)    NOT NULL CONSTRAINT DF_LabelComplianceReview_Status DEFAULT (N'Pending'),
        ReviewerName        NVARCHAR(200)   NOT NULL,
        ReviewDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_LabelComplianceReview_ReviewDate DEFAULT (SYSUTCDATETIME()),
        Comments            NVARCHAR(MAX)   NULL,
        CreatedByUser       INT             NOT NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_LabelComplianceReview_CreateDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_LabelComplianceReview PRIMARY KEY CLUSTERED (ReviewID),
        CONSTRAINT CK_LabelComplianceReview_Subject CHECK (
            ReviewSubject IN (N'BatchPrintOrder', N'LabelOrderRun', N'WhiteLabelOrder', N'LabelTemplate')
        ),
        CONSTRAINT CK_LabelComplianceReview_Status CHECK (
            ReviewStatus IN (N'Pending', N'In Review', N'Approved', N'Rejected')
        ),
        CONSTRAINT FK_LabelComplianceReview_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_LabelComplianceReview_Subject
        ON dbo.LabelComplianceReview (ReviewSubject, SubjectID, ReviewDate DESC);
END;
GO

IF OBJECT_ID(N'dbo.WhiteLabelProductionOrder', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.WhiteLabelProductionOrder (
        WLPOID              INT             NOT NULL IDENTITY(1,1),
        ExternalOrderID     NVARCHAR(100)   NOT NULL,
        ExternalOrderNumber NVARCHAR(100)   NULL,
        CustomerName        NVARCHAR(200)   NOT NULL,
        OrderDate           DATE            NOT NULL,
        OrderStatus         NVARCHAR(30)    NOT NULL CONSTRAINT DF_WhiteLabelProductionOrder_Status DEFAULT (N'Received'),
        ShipByDate          DATE            NULL,
        SourceSystem        NVARCHAR(50)    NOT NULL CONSTRAINT DF_WhiteLabelProductionOrder_Source DEFAULT (N'Adobe Commerce'),
        Notes               NVARCHAR(MAX)   NULL,
        ImportedDate        DATETIME2(0)    NOT NULL CONSTRAINT DF_WhiteLabelProductionOrder_ImportedDate DEFAULT (SYSUTCDATETIME()),
        CreatedByUser       INT             NOT NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_WhiteLabelProductionOrder_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate        DATETIME2(0)    NOT NULL CONSTRAINT DF_WhiteLabelProductionOrder_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser      INT             NULL,

        CONSTRAINT PK_WhiteLabelProductionOrder PRIMARY KEY CLUSTERED (WLPOID),
        CONSTRAINT UQ_WhiteLabelProductionOrder_ExternalOrderID UNIQUE (ExternalOrderID),
        CONSTRAINT CK_WhiteLabelProductionOrder_Status CHECK (
            OrderStatus IN (N'Received', N'In Production', N'Labeling', N'Ready to Ship', N'Shipped', N'Cancelled')
        ),
        CONSTRAINT FK_WhiteLabelProductionOrder_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_WhiteLabelProductionOrder_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_WhiteLabelProductionOrder_OrderDate
        ON dbo.WhiteLabelProductionOrder (OrderDate DESC);
END;
GO

IF OBJECT_ID(N'dbo.WhiteLabelProductionOrderLine', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.WhiteLabelProductionOrderLine (
        LineID              INT             NOT NULL IDENTITY(1,1),
        WLPOID              INT             NOT NULL,
        LineNumber          INT             NOT NULL,
        SKU                 NVARCHAR(100)   NOT NULL,
        ProductName         NVARCHAR(300)   NOT NULL,
        Quantity            DECIMAL(18,4)   NOT NULL,
        TemplateID          INT             NULL,
        LineStatus          NVARCHAR(30)    NOT NULL CONSTRAINT DF_WhiteLabelProductionOrderLine_Status DEFAULT (N'Open'),
        Notes               NVARCHAR(MAX)   NULL,

        CONSTRAINT PK_WhiteLabelProductionOrderLine PRIMARY KEY CLUSTERED (LineID),
        CONSTRAINT CK_WhiteLabelProductionOrderLine_Quantity CHECK (Quantity > 0),
        CONSTRAINT CK_WhiteLabelProductionOrderLine_Status CHECK (
            LineStatus IN (N'Open', N'In Production', N'Labeling', N'Complete', N'Cancelled')
        ),
        CONSTRAINT FK_WhiteLabelProductionOrderLine_Order FOREIGN KEY (WLPOID)
            REFERENCES dbo.WhiteLabelProductionOrder (WLPOID) ON DELETE CASCADE,
        CONSTRAINT FK_WhiteLabelProductionOrderLine_Template FOREIGN KEY (TemplateID)
            REFERENCES dbo.LabelTemplate (TemplateID)
    );

    CREATE NONCLUSTERED INDEX IX_WhiteLabelProductionOrderLine_WLPOID
        ON dbo.WhiteLabelProductionOrderLine (WLPOID, LineNumber);
END;
GO
