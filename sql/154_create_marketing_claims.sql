/*
  NutraAxis Operations — Marketing Claims Matrix (SEO Ops S1b: approved claims library + claims gate inputs)

  Creates:
    dbo.MktProduct  — product master for marketing (pillar, formula, intended use, flyer references)
    dbo.MktClaim    — approved / draft claim wording per product (or general), with evidence tier and approval

  Seeds settings:
    claims.flag_terms  — words/phrases that flag an asset for medical review
    claims.disclaimer  — DSHEA disclaimer appended to claims-bearing copy
    claims.min_score   — minimum claims-check score before an asset may leave draft

  Reverse (in order):
    DROP TABLE dbo.MktClaim; DROP TABLE dbo.MktProduct;
    DELETE FROM dbo.MktSetting WHERE SettingKey IN (N'claims.flag_terms', N'claims.disclaimer', N'claims.min_score');
*/

IF OBJECT_ID(N'dbo.MktProduct', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktProduct (
        ProductID        INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktProduct PRIMARY KEY,
        Name             NVARCHAR(100)   NOT NULL,
        TherapeuticArea  NVARCHAR(100)   NULL,
        Headline         NVARCHAR(300)   NULL,
        Summary          NVARCHAR(2000)  NULL,
        Formula          NVARCHAR(2000)  NULL,
        SuggestedUse     NVARCHAR(1000)  NULL,
        IntendedUse      NVARCHAR(2000)  NULL,
        ReferenceList    NVARCHAR(MAX)   NULL,
        SourceDocument   NVARCHAR(200)   NULL,
        Notes            NVARCHAR(2000)  NULL,
        Status           NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktProduct_Status DEFAULT (N'active'),
        CreatedAt        DATETIME2(0)    NOT NULL CONSTRAINT DF_MktProduct_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt        DATETIME2(0)    NOT NULL CONSTRAINT DF_MktProduct_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedBy        INT             NULL,
        CONSTRAINT UQ_MktProduct_Name UNIQUE (Name),
        CONSTRAINT CK_MktProduct_Status CHECK (Status IN (N'active', N'paused', N'retired'))
    );
END
GO

IF OBJECT_ID(N'dbo.MktClaim', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktClaim (
        ClaimID             INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktClaim PRIMARY KEY,
        ProductID           INT             NULL CONSTRAINT FK_MktClaim_Product REFERENCES dbo.MktProduct (ProductID),
        ClaimText           NVARCHAR(1000)  NOT NULL,
        ClaimType           NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktClaim_Type DEFAULT (N'benefit'),
        Ingredient          NVARCHAR(150)   NULL,
        EvidenceTier        NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktClaim_Evidence DEFAULT (N'clinical'),
        ReferenceNumbers    NVARCHAR(100)   NULL,
        Audience            NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktClaim_Audience DEFAULT (N'both'),
        RequiresDisclaimer  BIT             NOT NULL CONSTRAINT DF_MktClaim_Disclaimer DEFAULT (1),
        Status              NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktClaim_Status DEFAULT (N'draft'),
        SortOrder           INT             NOT NULL CONSTRAINT DF_MktClaim_Sort DEFAULT (100),
        SourceDocument      NVARCHAR(200)   NULL,
        Notes               NVARCHAR(1000)  NULL,
        ApprovedBy          INT             NULL,
        ApprovedAt          DATETIME2(0)    NULL,
        CreatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktClaim_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktClaim_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedBy           INT             NULL,
        CONSTRAINT CK_MktClaim_Type CHECK (ClaimType IN (N'headline', N'benefit', N'mechanism', N'ingredient', N'general')),
        CONSTRAINT CK_MktClaim_Evidence CHECK (EvidenceTier IN (N'clinical', N'mechanistic', N'traditional', N'label')),
        CONSTRAINT CK_MktClaim_Audience CHECK (Audience IN (N'practitioner', N'consumer', N'both')),
        CONSTRAINT CK_MktClaim_Status CHECK (Status IN (N'draft', N'approved', N'retired'))
    );

    CREATE INDEX IX_MktClaim_Product ON dbo.MktClaim (ProductID, Status, SortOrder);
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'claims.flag_terms',
     N'cure' + NCHAR(10) + N'cures' + NCHAR(10) + N'treat' + NCHAR(10) + N'treats' + NCHAR(10) + N'treatment for' + NCHAR(10) + N'prevent' + NCHAR(10) + N'prevents'
     + NCHAR(10) + N'diagnose' + NCHAR(10) + N'heal' + NCHAR(10) + N'reverse' + NCHAR(10) + N'reverses' + NCHAR(10) + N'disease' + NCHAR(10) + N'clinically proven'
     + NCHAR(10) + N'guaranteed' + NCHAR(10) + N'miracle' + NCHAR(10) + N'FDA approved' + NCHAR(10) + N'no side effects' + NCHAR(10) + N'better than' + NCHAR(10) + N'superior to'
     + NCHAR(10) + N'burn fat' + NCHAR(10) + N'fat burner' + NCHAR(10) + N'lose weight fast' + NCHAR(10) + N'diabetes' + NCHAR(10) + N'cancer' + NCHAR(10) + N'Alzheimer'
     + NCHAR(10) + N'depression' + NCHAR(10) + N'anxiety disorder' + NCHAR(10) + N'insomnia' + NCHAR(10) + N'hypertension' + NCHAR(10) + N'arthritis' + NCHAR(10) + N'obesity'
     + NCHAR(10) + N'infertility' + NCHAR(10) + N'PCOS' + NCHAR(10) + N'anemia',
     N'Words/phrases that flag an asset for medical review (disease, cure/treat/prevent, superiority, guarantees). One per line.'),
    (N'claims.disclaimer',
     N'These statements have not been evaluated by the Food and Drug Administration. This product is not intended to diagnose, treat, cure, or prevent any disease.',
     N'DSHEA disclaimer appended to claims-bearing copy.'),
    (N'claims.min_score', N'7', N'Minimum claims-check score (0–10) before an asset may leave draft.')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO
