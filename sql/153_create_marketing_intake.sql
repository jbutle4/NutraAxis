/*
  NutraAxis Operations — Marketing & Research intake (SEO Ops S1a: Content Engine stages 1–2)

  Creates:
    dbo.MktKeyword          — shared keyword list (SEO keywords + interest terms; Purpose flag)
    dbo.MktKeywordMetric    — keyword volume / CPC history (trend signal)
    dbo.MktInterest         — watched topics
    dbo.MktInterestTerm     — include / exclude / hashtag / query terms per interest
    dbo.MktSource           — typed source registry with schedule and health
    dbo.MktInterestSource   — interest ↔ source link
    dbo.MktHarvestRun       — one row per source per harvest run
    dbo.MktHarvestedItem    — deduplicated item queue

  Reverse (in order):
    DROP TABLE dbo.MktHarvestedItem; DROP TABLE dbo.MktHarvestRun; DROP TABLE dbo.MktInterestSource;
    DROP TABLE dbo.MktSource; DROP TABLE dbo.MktInterestTerm; DROP TABLE dbo.MktInterest;
    DROP TABLE dbo.MktKeywordMetric; DROP TABLE dbo.MktKeyword;
*/

IF OBJECT_ID(N'dbo.MktKeyword', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktKeyword (
        KeywordID    INT            NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktKeyword PRIMARY KEY,
        Keyword      NVARCHAR(200)  NOT NULL,
        Purpose      NVARCHAR(20)   NOT NULL CONSTRAINT DF_MktKeyword_Purpose DEFAULT (N'seo'),
        Priority     TINYINT        NOT NULL CONSTRAINT DF_MktKeyword_Priority DEFAULT (3),
        Cluster      NVARCHAR(100)  NULL,
        Intent       NVARCHAR(20)   NULL,
        Volume       INT            NULL,
        Difficulty   INT            NULL,
        Notes        NVARCHAR(1000) NULL,
        Status       NVARCHAR(20)   NOT NULL CONSTRAINT DF_MktKeyword_Status DEFAULT (N'active'),
        CreatedAt    DATETIME2(0)   NOT NULL CONSTRAINT DF_MktKeyword_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt    DATETIME2(0)   NOT NULL CONSTRAINT DF_MktKeyword_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedBy    INT            NULL,
        CONSTRAINT UQ_MktKeyword_Keyword UNIQUE (Keyword),
        CONSTRAINT CK_MktKeyword_Purpose CHECK (Purpose IN (N'seo', N'interest', N'both')),
        CONSTRAINT CK_MktKeyword_Priority CHECK (Priority BETWEEN 1 AND 5),
        CONSTRAINT CK_MktKeyword_Status CHECK (Status IN (N'active', N'paused', N'retired'))
    );
END
GO

IF OBJECT_ID(N'dbo.MktKeywordMetric', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktKeywordMetric (
        MetricID     BIGINT         NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktKeywordMetric PRIMARY KEY,
        KeywordID    INT            NOT NULL CONSTRAINT FK_MktKeywordMetric_Keyword REFERENCES dbo.MktKeyword (KeywordID) ON DELETE CASCADE,
        CapturedOn   DATE           NOT NULL,
        Source       NVARCHAR(20)   NOT NULL,
        Volume       INT            NULL,
        Cpc          DECIMAL(10, 2) NULL,
        Competition  DECIMAL(5, 2)  NULL,
        Difficulty   INT            NULL,
        CONSTRAINT UQ_MktKeywordMetric UNIQUE (KeywordID, CapturedOn, Source)
    );
END
GO

IF OBJECT_ID(N'dbo.MktInterest', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktInterest (
        InterestID       INT            NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktInterest PRIMARY KEY,
        Name             NVARCHAR(150)  NOT NULL,
        Description      NVARCHAR(2000) NULL,
        TherapeuticArea  NVARCHAR(100)  NULL,
        ProductLine      NVARCHAR(100)  NULL,
        Audience         NVARCHAR(50)   NULL,
        Priority         TINYINT        NOT NULL CONSTRAINT DF_MktInterest_Priority DEFAULT (3),
        Status           NVARCHAR(20)   NOT NULL CONSTRAINT DF_MktInterest_Status DEFAULT (N'active'),
        AgentEnabled     BIT            NOT NULL CONSTRAINT DF_MktInterest_AgentEnabled DEFAULT (1),
        OwnerUserID      INT            NULL,
        LastAgentRunAt   DATETIME2(0)   NULL,
        CreatedAt        DATETIME2(0)   NOT NULL CONSTRAINT DF_MktInterest_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt        DATETIME2(0)   NOT NULL CONSTRAINT DF_MktInterest_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedBy        INT            NULL,
        CONSTRAINT UQ_MktInterest_Name UNIQUE (Name),
        CONSTRAINT CK_MktInterest_Priority CHECK (Priority BETWEEN 1 AND 5),
        CONSTRAINT CK_MktInterest_Status CHECK (Status IN (N'active', N'paused', N'retired'))
    );
END
GO

IF OBJECT_ID(N'dbo.MktInterestTerm', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktInterestTerm (
        TermID       INT            NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktInterestTerm PRIMARY KEY,
        InterestID   INT            NOT NULL CONSTRAINT FK_MktInterestTerm_Interest REFERENCES dbo.MktInterest (InterestID) ON DELETE CASCADE,
        TermType     NVARCHAR(20)   NOT NULL,
        Term         NVARCHAR(300)  NOT NULL,
        KeywordID    INT            NULL CONSTRAINT FK_MktInterestTerm_Keyword REFERENCES dbo.MktKeyword (KeywordID),
        CONSTRAINT CK_MktInterestTerm_Type CHECK (TermType IN (N'include', N'exclude', N'hashtag', N'query'))
    );

    CREATE INDEX IX_MktInterestTerm_Interest ON dbo.MktInterestTerm (InterestID, TermType);
END
GO

IF OBJECT_ID(N'dbo.MktSource', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktSource (
        SourceID             INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktSource PRIMARY KEY,
        Name                 NVARCHAR(150)   NOT NULL,
        SourceType           NVARCHAR(30)    NOT NULL,
        Url                  NVARCHAR(2000)  NULL,
        Query                NVARCHAR(1000)  NULL,
        ConfigJson           NVARCHAR(MAX)   NULL,
        Schedule             NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktSource_Schedule DEFAULT (N'daily'),
        Status               NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktSource_Status DEFAULT (N'active'),
        TosNotes             NVARCHAR(1000)  NULL,
        LastRunAt            DATETIME2(0)    NULL,
        LastSuccessAt        DATETIME2(0)    NULL,
        NextRunAt            DATETIME2(0)    NULL,
        LastError            NVARCHAR(1000)  NULL,
        ConsecutiveFailures  INT             NOT NULL CONSTRAINT DF_MktSource_Failures DEFAULT (0),
        ItemsTotal           INT             NOT NULL CONSTRAINT DF_MktSource_ItemsTotal DEFAULT (0),
        CreatedAt            DATETIME2(0)    NOT NULL CONSTRAINT DF_MktSource_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt            DATETIME2(0)    NOT NULL CONSTRAINT DF_MktSource_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedBy            INT             NULL,
        CONSTRAINT CK_MktSource_Type CHECK (SourceType IN (
            N'rss', N'google_news', N'pubmed', N'clinicaltrials', N'crawl', N'reddit', N'youtube'
        )),
        CONSTRAINT CK_MktSource_Schedule CHECK (Schedule IN (N'hourly', N'daily', N'weekly')),
        CONSTRAINT CK_MktSource_Status CHECK (Status IN (N'active', N'paused', N'auto_paused'))
    );

    CREATE INDEX IX_MktSource_Due ON dbo.MktSource (Status, NextRunAt);
END
GO

IF OBJECT_ID(N'dbo.MktInterestSource', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktInterestSource (
        InterestID  INT NOT NULL CONSTRAINT FK_MktInterestSource_Interest REFERENCES dbo.MktInterest (InterestID) ON DELETE CASCADE,
        SourceID    INT NOT NULL CONSTRAINT FK_MktInterestSource_Source REFERENCES dbo.MktSource (SourceID) ON DELETE CASCADE,
        CONSTRAINT PK_MktInterestSource PRIMARY KEY (InterestID, SourceID)
    );
END
GO

IF OBJECT_ID(N'dbo.MktHarvestRun', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktHarvestRun (
        HarvestRunID  BIGINT          NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktHarvestRun PRIMARY KEY,
        SourceID      INT             NULL CONSTRAINT FK_MktHarvestRun_Source REFERENCES dbo.MktSource (SourceID) ON DELETE SET NULL,
        InterestID    INT             NULL,
        RunType       NVARCHAR(30)    NOT NULL,
        ProcessLogID  INT             NULL,
        StartedAt     DATETIME2(0)    NOT NULL CONSTRAINT DF_MktHarvestRun_StartedAt DEFAULT (SYSUTCDATETIME()),
        FinishedAt    DATETIME2(0)    NULL,
        Status        NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktHarvestRun_Status DEFAULT (N'running'),
        Fetched       INT             NOT NULL CONSTRAINT DF_MktHarvestRun_Fetched DEFAULT (0),
        Inserted      INT             NOT NULL CONSTRAINT DF_MktHarvestRun_Inserted DEFAULT (0),
        Duplicates    INT             NOT NULL CONSTRAINT DF_MktHarvestRun_Duplicates DEFAULT (0),
        Rejected      INT             NOT NULL CONSTRAINT DF_MktHarvestRun_Rejected DEFAULT (0),
        ErrorMessage  NVARCHAR(1000)  NULL,
        CONSTRAINT CK_MktHarvestRun_Status CHECK (Status IN (N'running', N'success', N'failed', N'skipped'))
    );

    CREATE INDEX IX_MktHarvestRun_Source ON dbo.MktHarvestRun (SourceID, StartedAt DESC);
END
GO

IF OBJECT_ID(N'dbo.MktHarvestedItem', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktHarvestedItem (
        ItemID            BIGINT          NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktHarvestedItem PRIMARY KEY,
        SourceID          INT             NULL CONSTRAINT FK_MktHarvestedItem_Source REFERENCES dbo.MktSource (SourceID) ON DELETE SET NULL,
        HarvestRunID      BIGINT          NULL,
        InterestID        INT             NULL,
        SourceType        NVARCHAR(30)    NOT NULL,
        Url               NVARCHAR(2000)  NOT NULL,
        CanonicalUrl      NVARCHAR(2000)  NOT NULL,
        UrlHash           BINARY(32)      NOT NULL,
        ContentHash       BINARY(32)      NULL,
        SimHash           BIGINT          NULL,
        Domain            NVARCHAR(255)   NULL,
        Title             NVARCHAR(500)   NOT NULL,
        Summary           NVARCHAR(4000)  NULL,
        BodyText          NVARCHAR(MAX)   NULL,
        Author            NVARCHAR(300)   NULL,
        PublishedAt       DATETIME2(0)    NULL,
        FetchedAt         DATETIME2(0)    NOT NULL CONSTRAINT DF_MktHarvestedItem_FetchedAt DEFAULT (SYSUTCDATETIME()),
        Status            NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktHarvestedItem_Status DEFAULT (N'new'),
        MetadataJson      NVARCHAR(MAX)   NULL,
        PromptKey         NVARCHAR(100)   NULL,
        PromptVersion     INT             NULL,
        VerificationNote  NVARCHAR(500)   NULL,
        AddedByUserID     INT             NULL,
        CONSTRAINT UQ_MktHarvestedItem_UrlHash UNIQUE (UrlHash),
        CONSTRAINT CK_MktHarvestedItem_Status CHECK (Status IN (
            N'new', N'scored', N'discarded', N'clustered', N'promoted', N'rejected', N'ignored', N'duplicate'
        ))
    );

    CREATE INDEX IX_MktHarvestedItem_Status ON dbo.MktHarvestedItem (Status, FetchedAt DESC);
    CREATE INDEX IX_MktHarvestedItem_Source ON dbo.MktHarvestedItem (SourceID, FetchedAt DESC);
    CREATE INDEX IX_MktHarvestedItem_ContentHash ON dbo.MktHarvestedItem (ContentHash);
    CREATE INDEX IX_MktHarvestedItem_Domain ON dbo.MktHarvestedItem (Domain, SourceType);
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'taxonomy.therapeutic_areas', N'Metabolic and Weight Health' + NCHAR(10) + N'Hormone and Reproductive Health' + NCHAR(10) + N'Mood, Stress and Sleep' + NCHAR(10) + N'Healthy Aging and Longevity' + NCHAR(10) + N'Digestive and Gut Health' + NCHAR(10) + N'Pain, Immune Response and Physical Comfort' + NCHAR(10) + N'Cognitive Health' + NCHAR(10) + N'Cardiovascular Health', N'Therapeutic areas for interests, one per line.'),
    (N'taxonomy.product_lines',     N'', N'Product lines for interests, one per line.'),
    (N'harvest.crawl_delay_ms',     N'1500', N'Delay between page fetches on the same site during a crawl.'),
    (N'harvest.lookback_days',      N'30', N'Search-feed sources (PubMed, ClinicalTrials.gov) only fetch records newer than this.'),
    (N'research.agent_max_results', N'8', N'Maximum items the weekly AI research agent may return per interest.'),
    (N'semrush.monthly_unit_budget', N'20000', N'Keyword trend job stops once month-to-date Semrush units reach this.')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'research.discover')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'research.discover', 1, N'anthropic', NULL,
    N'You are a research analyst for {{brand_name}}, a practitioner-channel nutraceutical brand. You find recent, credible, publicly accessible sources on a watched topic. Prefer peer-reviewed studies, regulatory notices (FDA, FTC, NIH), reputable trade press, and professional associations. Never invent URLs: only return pages you actually opened with the web search tool.',
    N'Watched topic: {{interest_name}}
Description: {{interest_description}}
Audience: {{interest_audience}}
Include terms: {{include_terms}}
Exclude terms: {{exclude_terms}}
Search queries to consider: {{queries}}
Already known domains (find new ones where possible): {{known_domains}}

Find up to {{max_results}} items published in the last {{lookback_days}} days that the regular feeds are likely to miss.

Return ONLY a JSON array. Each element:
{"url": "...", "title": "...", "published_date": "YYYY-MM-DD or null", "summary": "2-3 sentences", "evidence_type": "peer_reviewed|regulatory|news|competitor|opinion", "quote": "a short exact phrase copied from the page", "why_relevant": "one sentence"}',
    0.20, 4000, 1,
    N'Seed prompt (S1a). Weekly per-interest discovery with web search; every URL is verified by the harvester before use.'
);
GO
