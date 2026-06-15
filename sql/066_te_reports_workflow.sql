/*
  Travel & Expense reports, approval workflow, roles, and attachments.
*/

IF COL_LENGTH('dbo.Role', 'TEManagement') IS NULL
    ALTER TABLE dbo.Role ADD TEManagement NVARCHAR(10) NULL;
GO

IF COL_LENGTH('dbo.Role', 'TEApproval') IS NULL
    ALTER TABLE dbo.Role ADD TEApproval NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_TEManagement_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_TEManagement_CRUD
    CHECK (TEManagement IS NULL OR TEManagement IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

IF OBJECT_ID(N'dbo.CK_Role_TEApproval_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_TEApproval_CRUD
    CHECK (TEApproval IS NULL OR TEApproval IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

IF COL_LENGTH('dbo.[User]', 'IsTEApprover') IS NULL
    ALTER TABLE dbo.[User] ADD IsTEApprover BIT NOT NULL
        CONSTRAINT DF_User_IsTEApprover DEFAULT (0);
GO

IF COL_LENGTH('dbo.[User]', 'IsPOProcessor') IS NULL
    ALTER TABLE dbo.[User] ADD IsPOProcessor BIT NOT NULL
        CONSTRAINT DF_User_IsPOProcessor DEFAULT (0);
GO

IF OBJECT_ID(N'dbo.TEReport', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TEReport (
        ReportID               INT             NOT NULL IDENTITY(1,1),
        ReportNumber           NVARCHAR(50)    NOT NULL,
        ReportStatus           NVARCHAR(50)    NOT NULL,
        EmployeeUserID         INT             NOT NULL,
        PeriodStart            DATE            NULL,
        PeriodEnd              DATE            NULL,
        MileageRate            DECIMAL(10, 4)  NOT NULL CONSTRAINT DF_TEReport_MileageRate DEFAULT (0.70),
        TotalReimbursementDue  DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEReport_Total DEFAULT (0),
        BusinessPurpose        NVARCHAR(MAX)   NULL,
        CertificationAccepted  BIT             NOT NULL CONSTRAINT DF_TEReport_Cert DEFAULT (0),
        EmployeeSignedDate     DATE            NULL,
        SubmittedAt            DATETIME2(0)    NULL,
        ApprovedAt             DATETIME2(0)    NULL,
        ApprovedTotalDue       DECIMAL(18, 2)  NULL,
        CreateDate             DATETIME2(0)    NOT NULL CONSTRAINT DF_TEReport_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate           DATETIME2(0)    NOT NULL CONSTRAINT DF_TEReport_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        CreatedByUser          INT             NULL,
        ModifiedByUser         INT             NULL,

        CONSTRAINT PK_TEReport PRIMARY KEY CLUSTERED (ReportID),
        CONSTRAINT UQ_TEReport_ReportNumber UNIQUE (ReportNumber),
        CONSTRAINT FK_TEReport_EmployeeUser FOREIGN KEY (EmployeeUserID)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_TEReport_CreatedBy FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_TEReport_ModifiedBy FOREIGN KEY (ModifiedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_TEReport_ReportStatus CHECK (
            ReportStatus IN (
                N'Created',
                N'Submitted for Approval',
                N'Rejected',
                N'Approved',
                N'Sent Back for Comment',
                N'Viewed by Approver'
            )
        )
    );

    CREATE NONCLUSTERED INDEX IX_TEReport_EmployeeUserID
        ON dbo.TEReport (EmployeeUserID, CreateDate DESC);

    CREATE NONCLUSTERED INDEX IX_TEReport_ReportStatus
        ON dbo.TEReport (ReportStatus, SubmittedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.TEExpenseLine', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TEExpenseLine (
        LineID                 INT             NOT NULL IDENTITY(1,1),
        ReportID               INT             NOT NULL,
        SortOrder              INT             NOT NULL CONSTRAINT DF_TEExpenseLine_Sort DEFAULT (0),
        LineDate               DATE            NULL,
        Description            NVARCHAR(500)   NULL,
        AmountAir              DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Air DEFAULT (0),
        AmountHotel            DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Hotel DEFAULT (0),
        AmountHomeOffice       DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_HomeOffice DEFAULT (0),
        AmountCell             DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Cell DEFAULT (0),
        AmountRentalCarFuel    DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Rental DEFAULT (0),
        AmountTaxi             DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Taxi DEFAULT (0),
        AmountParkingTolls     DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Parking DEFAULT (0),
        AmountMileage          DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Mileage DEFAULT (0),
        AmountEntertainment    DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Entertainment DEFAULT (0),
        AmountTravelMeals      DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Meals DEFAULT (0),
        AmountShippingPostage  DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Shipping DEFAULT (0),
        AmountOfficeSupplies   DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Supplies DEFAULT (0),
        AmountMisc             DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Misc DEFAULT (0),
        LineTotal              DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEExpenseLine_Total DEFAULT (0),

        CONSTRAINT PK_TEExpenseLine PRIMARY KEY CLUSTERED (LineID),
        CONSTRAINT FK_TEExpenseLine_Report FOREIGN KEY (ReportID)
            REFERENCES dbo.TEReport (ReportID) ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX IX_TEExpenseLine_ReportID
        ON dbo.TEExpenseLine (ReportID, SortOrder);
END;
GO

IF OBJECT_ID(N'dbo.TEMileageLine', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TEMileageLine (
        LineID           INT             NOT NULL IDENTITY(1,1),
        ReportID         INT             NOT NULL,
        SortOrder        INT             NOT NULL CONSTRAINT DF_TEMileageLine_Sort DEFAULT (0),
        LineDate         DATE            NULL,
        FromLocation     NVARCHAR(200)   NULL,
        ToLocation       NVARCHAR(200)   NULL,
        BusinessPurpose  NVARCHAR(500)   NULL,
        Miles            DECIMAL(10, 2)  NOT NULL CONSTRAINT DF_TEMileageLine_Miles DEFAULT (0),

        CONSTRAINT PK_TEMileageLine PRIMARY KEY CLUSTERED (LineID),
        CONSTRAINT FK_TEMileageLine_Report FOREIGN KEY (ReportID)
            REFERENCES dbo.TEReport (ReportID) ON DELETE CASCADE
    );
END;
GO

IF OBJECT_ID(N'dbo.TEEntertainmentLine', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TEEntertainmentLine (
        LineID              INT             NOT NULL IDENTITY(1,1),
        ReportID            INT             NOT NULL,
        SortOrder           INT             NOT NULL CONSTRAINT DF_TEEntertainmentLine_Sort DEFAULT (0),
        LineDate            DATE            NULL,
        PersonsEntertained  NVARCHAR(500)   NULL,
        Place               NVARCHAR(200)   NULL,
        NaturePurpose       NVARCHAR(500)   NULL,
        Amount              DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEEntertainmentLine_Amount DEFAULT (0),

        CONSTRAINT PK_TEEntertainmentLine PRIMARY KEY CLUSTERED (LineID),
        CONSTRAINT FK_TEEntertainmentLine_Report FOREIGN KEY (ReportID)
            REFERENCES dbo.TEReport (ReportID) ON DELETE CASCADE
    );
END;
GO

IF OBJECT_ID(N'dbo.TEMiscLine', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TEMiscLine (
        LineID          INT             NOT NULL IDENTITY(1,1),
        ReportID        INT             NOT NULL,
        SortOrder       INT             NOT NULL CONSTRAINT DF_TEMiscLine_Sort DEFAULT (0),
        LineDate        DATE            NULL,
        Description     NVARCHAR(500)   NULL,
        NaturePurpose   NVARCHAR(500)   NULL,
        Amount          DECIMAL(18, 2)  NOT NULL CONSTRAINT DF_TEMiscLine_Amount DEFAULT (0),

        CONSTRAINT PK_TEMiscLine PRIMARY KEY CLUSTERED (LineID),
        CONSTRAINT FK_TEMiscLine_Report FOREIGN KEY (ReportID)
            REFERENCES dbo.TEReport (ReportID) ON DELETE CASCADE
    );
END;
GO

IF OBJECT_ID(N'dbo.TEAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TEAttachment (
        AttachmentID     INT             NOT NULL IDENTITY(1,1),
        ReportID         INT             NOT NULL,
        FileName         NVARCHAR(260)   NOT NULL,
        ContentType      NVARCHAR(120)   NOT NULL,
        FileSizeBytes    INT             NOT NULL,
        FileData         VARBINARY(MAX)  NOT NULL,
        AttachmentKind   NVARCHAR(50)    NOT NULL CONSTRAINT DF_TEAttachment_Kind DEFAULT (N'Receipt'),
        UploadedByUser   INT             NULL,
        UploadedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_TEAttachment_UploadedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_TEAttachment PRIMARY KEY CLUSTERED (AttachmentID),
        CONSTRAINT FK_TEAttachment_Report FOREIGN KEY (ReportID)
            REFERENCES dbo.TEReport (ReportID) ON DELETE CASCADE,
        CONSTRAINT FK_TEAttachment_User FOREIGN KEY (UploadedByUser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_TEAttachment_ReportID
        ON dbo.TEAttachment (ReportID, UploadedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.TEApprovalLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TEApprovalLog (
        ApprovalID        INT             NOT NULL IDENTITY(1,1),
        ReportID          INT             NOT NULL,
        ApproverName      NVARCHAR(200)   NOT NULL,
        ApproverResult    NVARCHAR(100)   NOT NULL,
        ApproverComments  NVARCHAR(MAX)   NULL,
        LogDate           DATETIME2(0)    NOT NULL CONSTRAINT DF_TEApprovalLog_LogDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_TEApprovalLog PRIMARY KEY CLUSTERED (ApprovalID),
        CONSTRAINT FK_TEApprovalLog_Report FOREIGN KEY (ReportID)
            REFERENCES dbo.TEReport (ReportID) ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX IX_TEApprovalLog_ReportID
        ON dbo.TEApprovalLog (ReportID, LogDate DESC);
END;
GO

IF OBJECT_ID(N'dbo.TEApprovalToken', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TEApprovalToken (
        TokenID      INT             NOT NULL IDENTITY(1,1),
        ReportID     INT             NOT NULL,
        UserID       INT             NOT NULL,
        TokenHash    CHAR(64)        NOT NULL,
        ExpiresAt    DATETIME2(0)    NOT NULL,
        UsedAt       DATETIME2(0)    NULL,
        CreatedAt    DATETIME2(0)    NOT NULL CONSTRAINT DF_TEApprovalToken_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_TEApprovalToken PRIMARY KEY CLUSTERED (TokenID),
        CONSTRAINT FK_TEApprovalToken_Report FOREIGN KEY (ReportID)
            REFERENCES dbo.TEReport (ReportID),
        CONSTRAINT FK_TEApprovalToken_User FOREIGN KEY (UserID)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_TEApprovalToken_ReportID
        ON dbo.TEApprovalToken (ReportID, ExpiresAt DESC);

    CREATE NONCLUSTERED INDEX IX_TEApprovalToken_Hash
        ON dbo.TEApprovalToken (TokenHash)
        WHERE UsedAt IS NULL;
END;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'te-viewed-by-approver')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (N'te-viewed-by-approver', 1, N'T&E report viewed by approver without final action.');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'te-approval-request')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (N'te-approval-request', 1, N'T&E report submitted for approval (watchers).');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'te-status-update')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (N'te-status-update', 1, N'T&E report approval status changed.');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.Role WHERE RoleName = N'T&E Approver')
    INSERT INTO dbo.Role (
        RoleName, RoleDesc, RoleCreateDate,
        POManagement, POApproval, TEManagement, TEApproval,
        InventoryReporting, SalesReporting, InventoryForecasting,
        LabelingOperations, OperationsDashboard, LegalAgreements,
        ProductCatalog, LinksIndex, Support, Accounting,
        UserAdmin, RoleAdmin
    )
    VALUES (
        N'T&E Approver', N'Review and approve travel and expense reports.', SYSUTCDATETIME(),
        N'R', NULL, N'R', N'RU',
        NULL, NULL, NULL,
        NULL, N'R', NULL,
        NULL, NULL, NULL, NULL,
        NULL, NULL
    );
GO

IF NOT EXISTS (SELECT 1 FROM dbo.Role WHERE RoleName = N'PO Processor')
    INSERT INTO dbo.Role (
        RoleName, RoleDesc, RoleCreateDate,
        POManagement, POApproval, TEManagement, TEApproval,
        InventoryReporting, SalesReporting, InventoryForecasting,
        LabelingOperations, OperationsDashboard, LegalAgreements,
        ProductCatalog, LinksIndex, Support, Accounting,
        UserAdmin, RoleAdmin
    )
    VALUES (
        N'PO Processor', N'Receives approved T&E reports for payment processing.', SYSUTCDATETIME(),
        N'R', NULL, N'R', NULL,
        NULL, NULL, NULL,
        NULL, N'R', NULL,
        NULL, NULL, NULL, NULL,
        NULL, NULL
    );
GO

UPDATE dbo.Role
SET TEManagement = N'CRUD', TEApproval = N'RU'
WHERE RoleName = N'Admin'
  AND (TEManagement IS NULL OR TEApproval IS NULL);
GO
