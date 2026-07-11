/*
  NutraAxis Operations — Provider signup applications (public draft/submit + ops review)
*/

IF COL_LENGTH(N'dbo.Role', N'ProviderAccountReview') IS NULL
    ALTER TABLE dbo.Role ADD ProviderAccountReview NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_ProviderAccountReview_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_ProviderAccountReview_CRUD
    CHECK (ProviderAccountReview IS NULL OR ProviderAccountReview IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

UPDATE dbo.Role
SET ProviderAccountReview = N'RUD'
WHERE RoleName = N'Admin'
  AND (ProviderAccountReview IS NULL OR ProviderAccountReview = N'');
GO

IF OBJECT_ID(N'dbo.ProviderSignupApplication', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ProviderSignupApplication (
        ApplicationID               INT             NOT NULL IDENTITY(1,1),
        AccessToken                 NVARCHAR(64)    NOT NULL,
        Status                      NVARCHAR(30)    NOT NULL CONSTRAINT DF_ProviderSignupApplication_Status DEFAULT (N'Draft'),
        ProviderEmail               NVARCHAR(255)   NOT NULL,

        CompanyName                 NVARCHAR(255)   NULL,
        CompanyLegalName            NVARCHAR(255)   NULL,
        CompanyEmail                NVARCHAR(255)   NULL,
        CompanyPhone                NVARCHAR(30)    NULL,
        StreetAddress               NVARCHAR(255)   NULL,
        City                        NVARCHAR(100)   NULL,
        StateCode                   NVARCHAR(10)    NULL,
        PostalCode                  NVARCHAR(20)    NULL,
        CountryCode                 NVARCHAR(5)     NOT NULL CONSTRAINT DF_ProviderSignupApplication_Country DEFAULT (N'US'),

        AdminFirstName              NVARCHAR(100)   NULL,
        AdminLastName               NVARCHAR(100)   NULL,
        AdminEmail                  NVARCHAR(255)   NULL,
        AdminPhone                  NVARCHAR(30)    NULL,

        NpiNumber                   NVARCHAR(10)    NULL,
        TaxIdType                   NVARCHAR(10)    NULL,
        TaxIdEncrypted              NVARCHAR(1000)  NULL,
        AchRoutingNumber            NVARCHAR(9)     NULL,
        AchAccountNumberEncrypted   NVARCHAR(1000)  NULL,
        AchAccountType              NVARCHAR(20)    NULL,

        NpiValidatedAt              DATETIME2(0)    NULL,
        NpiValidationStatus         NVARCHAR(30)    NULL,
        NpiValidationSummary        NVARCHAR(1000)  NULL,
        BankingValidationStatus     NVARCHAR(30)    NULL,
        BankingValidationSummary    NVARCHAR(1000)  NULL,

        AccsCompanyId               INT             NULL,
        AccsCustomerId              INT             NULL,
        AccsClinicId                NVARCHAR(50)    NULL,
        ProvisionedAt               DATETIME2(0)    NULL,
        LastProvisionError          NVARCHAR(500)   NULL,

        SubmittedAt                 DATETIME2(0)    NULL,
        LastSavedAt                 DATETIME2(0)    NOT NULL CONSTRAINT DF_ProviderSignupApplication_LastSavedAt DEFAULT (SYSUTCDATETIME()),
        CreatedAt                   DATETIME2(0)    NOT NULL CONSTRAINT DF_ProviderSignupApplication_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_ProviderSignupApplication PRIMARY KEY CLUSTERED (ApplicationID),
        CONSTRAINT UQ_ProviderSignupApplication_AccessToken UNIQUE (AccessToken),
        CONSTRAINT CK_ProviderSignupApplication_Status CHECK (
            Status IN (
                N'Draft',
                N'Submitted',
                N'Returned',
                N'Pending Validation',
                N'Approved',
                N'Provisioned',
                N'Rejected'
            )
        ),
        CONSTRAINT CK_ProviderSignupApplication_TaxIdType CHECK (
            TaxIdType IS NULL OR TaxIdType IN (N'SSN', N'EIN')
        ),
        CONSTRAINT CK_ProviderSignupApplication_AchAccountType CHECK (
            AchAccountType IS NULL OR AchAccountType IN (N'Checking', N'Savings')
        )
    );

    CREATE NONCLUSTERED INDEX IX_ProviderSignupApplication_Status
        ON dbo.ProviderSignupApplication (Status, SubmittedAt DESC);

    CREATE NONCLUSTERED INDEX IX_ProviderSignupApplication_ProviderEmail
        ON dbo.ProviderSignupApplication (ProviderEmail);
END;
GO

IF OBJECT_ID(N'dbo.ProviderSignupAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ProviderSignupAttachment (
        AttachmentID        INT             NOT NULL IDENTITY(1,1),
        ApplicationID       INT             NOT NULL,
        FileName            NVARCHAR(255)   NOT NULL,
        ContentType         NVARCHAR(100)   NOT NULL,
        FileSizeBytes       INT             NOT NULL,
        FileData            VARBINARY(MAX)  NOT NULL,
        AttachmentKind      NVARCHAR(30)    NOT NULL CONSTRAINT DF_ProviderSignupAttachment_Kind DEFAULT (N'ResellerCertificate'),
        UploadDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_ProviderSignupAttachment_UploadDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_ProviderSignupAttachment PRIMARY KEY CLUSTERED (AttachmentID),
        CONSTRAINT CK_ProviderSignupAttachment_FileSizeBytes CHECK (FileSizeBytes >= 0),
        CONSTRAINT CK_ProviderSignupAttachment_Kind CHECK (
            AttachmentKind IN (N'ResellerCertificate', N'Other')
        ),
        CONSTRAINT FK_ProviderSignupAttachment_Application FOREIGN KEY (ApplicationID)
            REFERENCES dbo.ProviderSignupApplication (ApplicationID) ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX IX_ProviderSignupAttachment_ApplicationID
        ON dbo.ProviderSignupAttachment (ApplicationID, UploadDate DESC);
END;
GO

IF OBJECT_ID(N'dbo.ProviderSignupReviewLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ProviderSignupReviewLog (
        ReviewLogID         INT             NOT NULL IDENTITY(1,1),
        ApplicationID       INT             NOT NULL,
        ReviewerUserID      INT             NULL,
        ReviewAction        NVARCHAR(30)    NOT NULL,
        Comments            NVARCHAR(4000)  NULL,
        LogDate             DATETIME2(0)    NOT NULL CONSTRAINT DF_ProviderSignupReviewLog_LogDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_ProviderSignupReviewLog PRIMARY KEY CLUSTERED (ReviewLogID),
        CONSTRAINT FK_ProviderSignupReviewLog_Application FOREIGN KEY (ApplicationID)
            REFERENCES dbo.ProviderSignupApplication (ApplicationID) ON DELETE CASCADE,
        CONSTRAINT FK_ProviderSignupReviewLog_Reviewer FOREIGN KEY (ReviewerUserID)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_ProviderSignupReviewLog_Action CHECK (
            ReviewAction IN (
                N'Submitted',
                N'Comment',
                N'Returned',
                N'Reopened',
                N'Approved',
                N'Rejected',
                N'Updated',
                N'Activated',
                N'NpiValidated',
                N'BankingValidated',
                N'Provisioned',
                N'ProvisionFailed'
            )
        )
    );

    CREATE NONCLUSTERED INDEX IX_ProviderSignupReviewLog_ApplicationID
        ON dbo.ProviderSignupReviewLog (ApplicationID, LogDate DESC);
END;
GO
