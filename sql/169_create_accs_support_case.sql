/*
  NutraAxis Operations — ACCS Order and Account Support
  Support cases, comment thread, event log, Stage resource ledger,
  and AccsOrderAccountSupport role permission.
*/

IF COL_LENGTH(N'dbo.Role', N'AccsOrderAccountSupport') IS NULL
    ALTER TABLE dbo.Role ADD AccsOrderAccountSupport NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_AccsOrderAccountSupport_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_AccsOrderAccountSupport_CRUD
    CHECK (AccsOrderAccountSupport IS NULL OR AccsOrderAccountSupport IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

UPDATE dbo.Role
SET AccsOrderAccountSupport = N'CRUD'
WHERE RoleName IN (N'Admin', N'Management User')
  AND (AccsOrderAccountSupport IS NULL OR AccsOrderAccountSupport = N'');
GO

IF OBJECT_ID(N'dbo.AccsSupportCase', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AccsSupportCase (
        CaseID                  INT             NOT NULL IDENTITY(1,1),
        TicketRef               NVARCHAR(100)   NULL,
        Subject                 NVARCHAR(255)   NULL,
        Status                  NVARCHAR(30)    NOT NULL
            CONSTRAINT DF_AccsSupportCase_Status DEFAULT (N'open'),
        ProdCompanyId           INT             NULL,
        ProdCompanyName         NVARCHAR(255)   NULL,
        ProdAdminEmail          NVARCHAR(255)   NULL,
        ProdAdminCustomerId     INT             NULL,
        ProdSharedCatalogId     INT             NULL,
        ProdOrderId             NVARCHAR(50)    NULL,
        StageCompanyId          INT             NULL,
        StageCompanyName        NVARCHAR(255)   NULL,
        StageCustomerId         INT             NULL,
        StageSharedCatalogId    INT             NULL,
        StageCustomerGroupId    INT             NULL,
        StageCartId             NVARCHAR(50)    NULL,
        CloneSummaryJson        NVARCHAR(MAX)   NULL,
        CreatedByUserID         INT             NULL,
        CreatedAt               DATETIME2(0)    NOT NULL
            CONSTRAINT DF_AccsSupportCase_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt               DATETIME2(0)    NOT NULL
            CONSTRAINT DF_AccsSupportCase_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        ClosedAt                DATETIME2(0)    NULL,
        PurgedAt                DATETIME2(0)    NULL,
        CONSTRAINT PK_AccsSupportCase PRIMARY KEY CLUSTERED (CaseID),
        CONSTRAINT CK_AccsSupportCase_Status CHECK (
            Status IN (
                N'open',
                N'clone_started',
                N'cloned',
                N'clone_failed',
                N'cart_ready',
                N'closed',
                N'purge_started',
                N'purged',
                N'purge_failed'
            )
        )
    );

    CREATE INDEX IX_AccsSupportCase_Status_CreatedAt
        ON dbo.AccsSupportCase (Status, CreatedAt DESC);

    CREATE INDEX IX_AccsSupportCase_ProdCompanyId
        ON dbo.AccsSupportCase (ProdCompanyId);
END
GO

IF OBJECT_ID(N'dbo.AccsSupportCaseComment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AccsSupportCaseComment (
        CommentID               INT             NOT NULL IDENTITY(1,1),
        CaseID                  INT             NOT NULL,
        Body                    NVARCHAR(MAX)   NOT NULL,
        CreatedByUserID         INT             NULL,
        CreatedAt               DATETIME2(0)    NOT NULL
            CONSTRAINT DF_AccsSupportCaseComment_CreatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT PK_AccsSupportCaseComment PRIMARY KEY CLUSTERED (CommentID),
        CONSTRAINT FK_AccsSupportCaseComment_Case
            FOREIGN KEY (CaseID) REFERENCES dbo.AccsSupportCase (CaseID)
    );

    CREATE INDEX IX_AccsSupportCaseComment_CaseID_CreatedAt
        ON dbo.AccsSupportCaseComment (CaseID, CreatedAt ASC);
END
GO

IF OBJECT_ID(N'dbo.AccsSupportCaseEvent', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AccsSupportCaseEvent (
        EventID                 INT             NOT NULL IDENTITY(1,1),
        CaseID                  INT             NOT NULL,
        EventType               NVARCHAR(50)    NOT NULL,
        Message                 NVARCHAR(1000)  NULL,
        ProcessRunId            NVARCHAR(100)   NULL,
        PayloadJson             NVARCHAR(MAX)   NULL,
        CreatedByUserID         INT             NULL,
        CreatedAt               DATETIME2(0)    NOT NULL
            CONSTRAINT DF_AccsSupportCaseEvent_CreatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT PK_AccsSupportCaseEvent PRIMARY KEY CLUSTERED (EventID),
        CONSTRAINT FK_AccsSupportCaseEvent_Case
            FOREIGN KEY (CaseID) REFERENCES dbo.AccsSupportCase (CaseID)
    );

    CREATE INDEX IX_AccsSupportCaseEvent_CaseID_CreatedAt
        ON dbo.AccsSupportCaseEvent (CaseID, CreatedAt ASC);
END
GO

IF OBJECT_ID(N'dbo.AccsSupportCaseResource', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AccsSupportCaseResource (
        ResourceID              INT             NOT NULL IDENTITY(1,1),
        CaseID                  INT             NOT NULL,
        Action                  NVARCHAR(20)    NOT NULL,
        ResourceType            NVARCHAR(50)    NOT NULL,
        StageEntityId           NVARCHAR(100)   NULL,
        StageEntityName         NVARCHAR(255)   NULL,
        ProdSourceId            NVARCHAR(100)   NULL,
        PayloadJson             NVARCHAR(MAX)   NULL,
        CreatedByUserID         INT             NULL,
        CreatedAt               DATETIME2(0)    NOT NULL
            CONSTRAINT DF_AccsSupportCaseResource_CreatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT PK_AccsSupportCaseResource PRIMARY KEY CLUSTERED (ResourceID),
        CONSTRAINT FK_AccsSupportCaseResource_Case
            FOREIGN KEY (CaseID) REFERENCES dbo.AccsSupportCase (CaseID),
        CONSTRAINT CK_AccsSupportCaseResource_Action CHECK (
            Action IN (N'created', N'deleted')
        )
    );

    CREATE INDEX IX_AccsSupportCaseResource_CaseID_CreatedAt
        ON dbo.AccsSupportCaseResource (CaseID, CreatedAt ASC);
END
GO
