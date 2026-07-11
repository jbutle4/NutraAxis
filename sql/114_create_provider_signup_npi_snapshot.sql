/*
  NutraAxis Operations — NPPES registry snapshots for provider signup NPI validation
*/

IF OBJECT_ID(N'dbo.ProviderSignupNpiSnapshot', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ProviderSignupNpiSnapshot (
        SnapshotID                  INT             NOT NULL IDENTITY(1,1),
        ApplicationID               INT             NOT NULL,
        NpiNumber                   NVARCHAR(10)    NOT NULL,
        FetchedAt                   DATETIME2(0)    NOT NULL CONSTRAINT DF_ProviderSignupNpiSnapshot_FetchedAt DEFAULT (SYSUTCDATETIME()),
        ValidationOk                BIT             NOT NULL CONSTRAINT DF_ProviderSignupNpiSnapshot_ValidationOk DEFAULT (0),
        ValidationStatus            NVARCHAR(30)    NOT NULL,
        ValidationSummary           NVARCHAR(1000)  NULL,
        RawJson                     NVARCHAR(MAX)   NULL,

        EnumerationType             NVARCHAR(10)    NULL,
        RegistryStatus              NVARCHAR(30)    NULL,
        ProviderName                NVARCHAR(255)   NULL,
        FirstName                   NVARCHAR(100)   NULL,
        MiddleName                  NVARCHAR(100)   NULL,
        LastName                    NVARCHAR(100)   NULL,
        Credential                  NVARCHAR(50)    NULL,
        OrganizationName            NVARCHAR(255)   NULL,
        AuthorizedOfficialFirstName NVARCHAR(100)   NULL,
        AuthorizedOfficialLastName  NVARCHAR(100)   NULL,
        AuthorizedOfficialTitle     NVARCHAR(100)   NULL,
        AuthorizedOfficialPhone     NVARCHAR(30)    NULL,
        CertificationDate           DATE            NULL,
        EnumerationDate             DATE            NULL,
        LastUpdatedEpoch            BIGINT          NULL,

        NameMatchStatus             NVARCHAR(20)    NULL,
        AddressMatchStatus          NVARCHAR(20)    NULL,
        LicenseMatchStatus          NVARCHAR(20)    NULL,
        ComparisonSummary           NVARCHAR(1000)  NULL,

        CONSTRAINT PK_ProviderSignupNpiSnapshot PRIMARY KEY CLUSTERED (SnapshotID),
        CONSTRAINT FK_ProviderSignupNpiSnapshot_Application FOREIGN KEY (ApplicationID)
            REFERENCES dbo.ProviderSignupApplication (ApplicationID) ON DELETE CASCADE,
        CONSTRAINT CK_ProviderSignupNpiSnapshot_ValidationStatus CHECK (
            ValidationStatus IN (
                N'Validated',
                N'Invalid',
                N'NotFound',
                N'Inactive',
                N'Error'
            )
        ),
        CONSTRAINT CK_ProviderSignupNpiSnapshot_MatchStatus CHECK (
            (NameMatchStatus IS NULL OR NameMatchStatus IN (N'Match', N'Partial', N'Mismatch', N'Unavailable'))
            AND (AddressMatchStatus IS NULL OR AddressMatchStatus IN (N'Match', N'Partial', N'Mismatch', N'Unavailable'))
            AND (LicenseMatchStatus IS NULL OR LicenseMatchStatus IN (N'OnFile', N'NotOnFile', N'Unavailable'))
        )
    );

    CREATE NONCLUSTERED INDEX IX_ProviderSignupNpiSnapshot_ApplicationID
        ON dbo.ProviderSignupNpiSnapshot (ApplicationID, FetchedAt DESC);

    CREATE NONCLUSTERED INDEX IX_ProviderSignupNpiSnapshot_NpiNumber
        ON dbo.ProviderSignupNpiSnapshot (NpiNumber, FetchedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.ProviderSignupNpiAddress', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ProviderSignupNpiAddress (
        AddressID           INT             NOT NULL IDENTITY(1,1),
        SnapshotID          INT             NOT NULL,
        AddressPurpose      NVARCHAR(20)    NOT NULL,
        AddressType         NVARCHAR(10)    NULL,
        Address1            NVARCHAR(255)   NULL,
        Address2            NVARCHAR(255)   NULL,
        City                NVARCHAR(100)   NULL,
        StateCode           NVARCHAR(10)    NULL,
        PostalCode          NVARCHAR(20)    NULL,
        CountryCode         NVARCHAR(5)     NULL,
        TelephoneNumber     NVARCHAR(30)    NULL,
        FaxNumber           NVARCHAR(30)    NULL,

        CONSTRAINT PK_ProviderSignupNpiAddress PRIMARY KEY CLUSTERED (AddressID),
        CONSTRAINT FK_ProviderSignupNpiAddress_Snapshot FOREIGN KEY (SnapshotID)
            REFERENCES dbo.ProviderSignupNpiSnapshot (SnapshotID) ON DELETE CASCADE,
        CONSTRAINT CK_ProviderSignupNpiAddress_Purpose CHECK (
            AddressPurpose IN (N'MAILING', N'LOCATION', N'OTHER')
        )
    );

    CREATE NONCLUSTERED INDEX IX_ProviderSignupNpiAddress_SnapshotID
        ON dbo.ProviderSignupNpiAddress (SnapshotID, AddressPurpose);
END;
GO

IF OBJECT_ID(N'dbo.ProviderSignupNpiTaxonomy', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ProviderSignupNpiTaxonomy (
        TaxonomyID          INT             NOT NULL IDENTITY(1,1),
        SnapshotID          INT             NOT NULL,
        TaxonomyCode        NVARCHAR(20)    NULL,
        TaxonomyDescription NVARCHAR(255)   NULL,
        LicenseNumber       NVARCHAR(50)    NULL,
        LicenseStateCode    NVARCHAR(10)    NULL,
        IsPrimary           BIT             NOT NULL CONSTRAINT DF_ProviderSignupNpiTaxonomy_IsPrimary DEFAULT (0),
        TaxonomyGroup       NVARCHAR(255)   NULL,

        CONSTRAINT PK_ProviderSignupNpiTaxonomy PRIMARY KEY CLUSTERED (TaxonomyID),
        CONSTRAINT FK_ProviderSignupNpiTaxonomy_Snapshot FOREIGN KEY (SnapshotID)
            REFERENCES dbo.ProviderSignupNpiSnapshot (SnapshotID) ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX IX_ProviderSignupNpiTaxonomy_SnapshotID
        ON dbo.ProviderSignupNpiTaxonomy (SnapshotID, IsPrimary DESC);
END;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'LatestNpiSnapshotID') IS NULL
BEGIN
    ALTER TABLE dbo.ProviderSignupApplication
        ADD LatestNpiSnapshotID INT NULL;

    ALTER TABLE dbo.ProviderSignupApplication
        ADD CONSTRAINT FK_ProviderSignupApplication_LatestNpiSnapshot
            FOREIGN KEY (LatestNpiSnapshotID)
            REFERENCES dbo.ProviderSignupNpiSnapshot (SnapshotID);
END;
GO

IF OBJECT_ID(N'dbo.CK_ProviderSignupReviewLog_Action', N'C') IS NOT NULL
    ALTER TABLE dbo.ProviderSignupReviewLog DROP CONSTRAINT CK_ProviderSignupReviewLog_Action;
GO

ALTER TABLE dbo.ProviderSignupReviewLog
ADD CONSTRAINT CK_ProviderSignupReviewLog_Action CHECK (
    ReviewAction IN (
        N'Submitted',
        N'Comment',
        N'Returned',
        N'Approved',
        N'Rejected',
        N'Updated',
        N'Activated',
        N'NpiValidated',
        N'BankingValidated',
        N'Provisioned',
        N'ProvisionFailed'
    )
);
GO
