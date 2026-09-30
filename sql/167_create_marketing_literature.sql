-- Literature & Intelligence: evidence library, flyer references, claim links, regulatory watch (idempotent)

IF OBJECT_ID('dbo.MktLitSource', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktLitSource (
        SourceID        INT IDENTITY(1,1) PRIMARY KEY,
        ItemID          BIGINT          NOT NULL CONSTRAINT FK_MktLitSource_Item REFERENCES dbo.MktHarvestedItem (ItemID),
        AddedFrom       NVARCHAR(10)    NOT NULL,
        LevelOverride   NVARCHAR(12)    NULL,
        StudyJson       NVARCHAR(MAX)   NULL,
        Takeaway        NVARCHAR(1000)  NULL,
        Limitations     NVARCHAR(1000)  NULL,
        Status          NVARCHAR(10)    NOT NULL CONSTRAINT DF_MktLitSource_Status DEFAULT (N'active'),
        CreatedBy       INT             NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktLitSource_Created DEFAULT (SYSUTCDATETIME()),
        UpdatedBy       INT             NULL,
        UpdatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktLitSource_Updated DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_MktLitSource_AddedFrom CHECK (AddedFrom IN (N'feed', N'lookup', N'flyer')),
        CONSTRAINT CK_MktLitSource_Status CHECK (Status IN (N'active', N'retired')),
        CONSTRAINT CK_MktLitSource_Level CHECK (LevelOverride IS NULL OR LevelOverride IN (N'meta', N'rct', N'clinical', N'registered', N'preclinical', N'review', N'other'))
    );
    CREATE UNIQUE INDEX UX_MktLitSource_Item ON dbo.MktLitSource (ItemID);
END
GO

IF OBJECT_ID('dbo.MktLitFlyerRef', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktLitFlyerRef (
        FlyerRefID      INT IDENTITY(1,1) PRIMARY KEY,
        ProductID       INT             NOT NULL CONSTRAINT FK_MktLitFlyerRef_Product REFERENCES dbo.MktProduct (ProductID) ON DELETE CASCADE,
        RefNumber       INT             NOT NULL,
        RefText         NVARCHAR(1000)  NOT NULL,
        IsStudy         BIT             NOT NULL,
        MatchStatus     NVARCHAR(12)    NOT NULL CONSTRAINT DF_MktLitFlyerRef_Match DEFAULT (N'unmatched'),
        SourceID        INT             NULL CONSTRAINT FK_MktLitFlyerRef_Source REFERENCES dbo.MktLitSource (SourceID),
        CandidatesJson  NVARCHAR(MAX)   NULL,
        CheckedAt       DATETIME2(0)    NULL,
        UpdatedBy       INT             NULL,
        UpdatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktLitFlyerRef_Updated DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_MktLitFlyerRef_Match CHECK (MatchStatus IN (N'unmatched', N'candidates', N'no_match', N'matched', N'other'))
    );
    CREATE UNIQUE INDEX UX_MktLitFlyerRef_Number ON dbo.MktLitFlyerRef (ProductID, RefNumber);
END
GO

IF OBJECT_ID('dbo.MktLitClaimSource', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktLitClaimSource (
        ClaimID         INT             NOT NULL CONSTRAINT FK_MktLitClaimSource_Claim REFERENCES dbo.MktClaim (ClaimID) ON DELETE CASCADE,
        SourceID        INT             NOT NULL CONSTRAINT FK_MktLitClaimSource_Source REFERENCES dbo.MktLitSource (SourceID) ON DELETE CASCADE,
        CreatedBy       INT             NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktLitClaimSource_Created DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT PK_MktLitClaimSource PRIMARY KEY (ClaimID, SourceID)
    );
END
GO

IF OBJECT_ID('dbo.MktLitDismissed', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktLitDismissed (
        ItemID          BIGINT          NOT NULL CONSTRAINT PK_MktLitDismissed PRIMARY KEY CONSTRAINT FK_MktLitDismissed_Item REFERENCES dbo.MktHarvestedItem (ItemID) ON DELETE CASCADE,
        CreatedBy       INT             NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktLitDismissed_Created DEFAULT (SYSUTCDATETIME())
    );
END
GO

IF OBJECT_ID('dbo.MktLitRegulatory', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktLitRegulatory (
        ItemID          BIGINT          NOT NULL CONSTRAINT PK_MktLitRegulatory PRIMARY KEY CONSTRAINT FK_MktLitRegulatory_Item REFERENCES dbo.MktHarvestedItem (ItemID) ON DELETE CASCADE,
        Status          NVARCHAR(10)    NOT NULL,
        Note            NVARCHAR(1000)  NULL,
        UpdatedBy       INT             NULL,
        UpdatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktLitRegulatory_Updated DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_MktLitRegulatory_Status CHECK (Status IN (N'reviewed', N'action', N'done'))
    );
END
GO
