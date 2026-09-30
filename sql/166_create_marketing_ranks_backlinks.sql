-- Rank Tracker and Backlinks & Outreach (idempotent)

IF COL_LENGTH('dbo.MktKeyword', 'TrackRank') IS NULL
BEGIN
    ALTER TABLE dbo.MktKeyword ADD TrackRank BIT NOT NULL CONSTRAINT DF_MktKeyword_TrackRank DEFAULT (0);
    EXEC(N'UPDATE dbo.MktKeyword SET TrackRank = 1 WHERE Status = N''active'' AND Purpose IN (N''seo'', N''both'')');
END
GO

IF OBJECT_ID('dbo.MktRankImport', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktRankImport (
        ImportID        INT IDENTITY(1,1) PRIMARY KEY,
        Source          NVARCHAR(40)    NOT NULL,
        ResultDate      DATE            NOT NULL,
        Keywords        INT             NOT NULL CONSTRAINT DF_MktRankImport_Keywords DEFAULT (0),
        OursRanked      INT             NOT NULL CONSTRAINT DF_MktRankImport_Ours DEFAULT (0),
        CompetitorRows  INT             NOT NULL CONSTRAINT DF_MktRankImport_Comp DEFAULT (0),
        Skipped         INT             NOT NULL CONSTRAINT DF_MktRankImport_Skipped DEFAULT (0),
        CreatedBy       INT             NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktRankImport_Created DEFAULT (SYSUTCDATETIME())
    );
END
GO

IF OBJECT_ID('dbo.MktRankResult', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktRankResult (
        ResultID        INT IDENTITY(1,1) PRIMARY KEY,
        ImportID        INT             NOT NULL CONSTRAINT FK_MktRankResult_Import REFERENCES dbo.MktRankImport (ImportID) ON DELETE CASCADE,
        KeywordID       INT             NOT NULL CONSTRAINT FK_MktRankResult_Keyword REFERENCES dbo.MktKeyword (KeywordID) ON DELETE CASCADE,
        ResultDate      DATE            NOT NULL,
        IsOurs          BIT             NOT NULL,
        Domain          NVARCHAR(253)   NOT NULL,
        CompetitorName  NVARCHAR(150)   NULL,
        Position        DECIMAL(5, 1)   NULL,
        Url             NVARCHAR(1000)  NULL
    );
    CREATE INDEX IX_MktRankResult_Keyword ON dbo.MktRankResult (KeywordID, ResultDate) INCLUDE (IsOurs, Position, CompetitorName);
END
GO

-- One row per tracked keyword per day: Search Console (average position across our pages, impression-weighted)
-- or an imported result for our domain. Position NULL = checked by an import and not found.
CREATE OR ALTER VIEW dbo.MktRankObservation AS
SELECT k.KeywordID, g.MetricDate AS ObsDate, CAST(N'gsc' AS NVARCHAR(10)) AS Source,
       CAST(SUM(g.Position * g.Impressions) / NULLIF(SUM(g.Impressions), 0) AS DECIMAL(6, 2)) AS Position,
       SUM(g.Impressions) AS Impressions, SUM(g.Clicks) AS Clicks
FROM dbo.MktKeyword k
INNER JOIN dbo.MktGscDaily g ON g.Grain = N'query' AND LOWER(g.Query) = LOWER(k.Keyword)
WHERE k.TrackRank = 1
GROUP BY k.KeywordID, g.MetricDate
HAVING SUM(g.Impressions) > 0
UNION ALL
SELECT r.KeywordID, r.ResultDate, CAST(N'import' AS NVARCHAR(10)), CAST(r.Position AS DECIMAL(6, 2)), NULL, NULL
FROM dbo.MktRankResult r
INNER JOIN dbo.MktKeyword k ON k.KeywordID = r.KeywordID AND k.TrackRank = 1
WHERE r.IsOurs = 1;
GO

IF OBJECT_ID('dbo.MktBacklinkImport', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktBacklinkImport (
        ImportID        INT IDENTITY(1,1) PRIMARY KEY,
        Source          NVARCHAR(40)    NOT NULL,
        Kind            NVARCHAR(10)    NOT NULL,
        RowsRead        INT             NOT NULL CONSTRAINT DF_MktBacklinkImport_Rows DEFAULT (0),
        NewCount        INT             NOT NULL CONSTRAINT DF_MktBacklinkImport_New DEFAULT (0),
        LostCount       INT             NOT NULL CONSTRAINT DF_MktBacklinkImport_Lost DEFAULT (0),
        WonCount        INT             NOT NULL CONSTRAINT DF_MktBacklinkImport_Won DEFAULT (0),
        Skipped         INT             NOT NULL CONSTRAINT DF_MktBacklinkImport_Skipped DEFAULT (0),
        CreatedBy       INT             NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktBacklinkImport_Created DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_MktBacklinkImport_Kind CHECK (Kind IN (N'links', N'gap'))
    );
END
GO

IF OBJECT_ID('dbo.MktBacklink', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktBacklink (
        BacklinkID      INT IDENTITY(1,1) PRIMARY KEY,
        LinkHash        BINARY(32)      NOT NULL,
        SourceUrl       NVARCHAR(1000)  NOT NULL,
        SourceDomain    NVARCHAR(253)   NOT NULL,
        TargetUrl       NVARCHAR(1000)  NOT NULL,
        Anchor          NVARCHAR(500)   NULL,
        Followed        BIT             NULL,
        Authority       INT             NULL,
        SpamScore       INT             NULL,
        FirstSeen       DATE            NULL,
        LastSeen        DATE            NULL,
        LostAt          DATE            NULL,
        Status          NVARCHAR(10)    NOT NULL CONSTRAINT DF_MktBacklink_Status DEFAULT (N'active'),
        LastImportID    INT             NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktBacklink_Created DEFAULT (SYSUTCDATETIME()),
        UpdatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktBacklink_Updated DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_MktBacklink_Status CHECK (Status IN (N'active', N'lost'))
    );
    CREATE UNIQUE INDEX UX_MktBacklink_Hash ON dbo.MktBacklink (LinkHash);
    CREATE INDEX IX_MktBacklink_Domain ON dbo.MktBacklink (SourceDomain, Status);
END
GO

IF OBJECT_ID('dbo.MktBacklinkGap', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktBacklinkGap (
        Domain          NVARCHAR(253)   NOT NULL PRIMARY KEY,
        Authority       INT             NULL,
        SpamScore       INT             NULL,
        CompetitorCount INT             NOT NULL CONSTRAINT DF_MktBacklinkGap_Count DEFAULT (0),
        Competitors     NVARCHAR(500)   NULL,
        Earned          BIT             NOT NULL CONSTRAINT DF_MktBacklinkGap_Earned DEFAULT (0),
        Dismissed       BIT             NOT NULL CONSTRAINT DF_MktBacklinkGap_Dismissed DEFAULT (0),
        FirstSeen       DATE            NOT NULL,
        LastSeen        DATE            NOT NULL,
        LastImportID    INT             NULL
    );
END
GO

IF OBJECT_ID('dbo.MktBacklinkDisavow', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktBacklinkDisavow (
        Domain          NVARCHAR(253)   NOT NULL PRIMARY KEY,
        Reason          NVARCHAR(300)   NULL,
        CreatedBy       INT             NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktBacklinkDisavow_Created DEFAULT (SYSUTCDATETIME())
    );
END
GO

IF OBJECT_ID('dbo.MktProspect', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktProspect (
        ProspectID          INT IDENTITY(1,1) PRIMARY KEY,
        Domain              NVARCHAR(253)   NOT NULL,
        Opportunity         NVARCHAR(300)   NOT NULL,
        Origin              NVARCHAR(10)    NOT NULL CONSTRAINT DF_MktProspect_Origin DEFAULT (N'manual'),
        TargetUrl           NVARCHAR(1000)  NULL,
        ContactName         NVARCHAR(150)   NULL,
        ContactRole         NVARCHAR(150)   NULL,
        ContactEmail        NVARCHAR(320)   NULL,
        OwnerUserID         INT             NULL,
        Status              NVARCHAR(12)    NOT NULL CONSTRAINT DF_MktProspect_Status DEFAULT (N'to_contact'),
        NextFollowUp        DATE            NULL,
        Angle               NVARCHAR(1000)  NULL,
        PitchSubject        NVARCHAR(300)   NULL,
        PitchBody           NVARCHAR(MAX)   NULL,
        PitchClaimsScore    DECIMAL(4, 1)   NULL,
        PitchCheckJson      NVARCHAR(MAX)   NULL,
        PitchDraftedAt      DATETIME2(0)    NULL,
        WonUrl              NVARCHAR(1000)  NULL,
        WonAt               DATE            NULL,
        CreatedBy           INT             NULL,
        CreatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktProspect_Created DEFAULT (SYSUTCDATETIME()),
        UpdatedBy           INT             NULL,
        UpdatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktProspect_Updated DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_MktProspect_Status CHECK (Status IN (N'to_contact', N'contacted', N'replied', N'won', N'declined')),
        CONSTRAINT CK_MktProspect_Origin CHECK (Origin IN (N'gap', N'lost', N'manual'))
    );
    CREATE UNIQUE INDEX UX_MktProspect_Domain ON dbo.MktProspect (Domain);
END
GO

IF OBJECT_ID('dbo.MktProspectEvent', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktProspectEvent (
        EventID         INT IDENTITY(1,1) PRIMARY KEY,
        ProspectID      INT             NOT NULL CONSTRAINT FK_MktProspectEvent_Prospect REFERENCES dbo.MktProspect (ProspectID) ON DELETE CASCADE,
        EventType       NVARCHAR(12)    NOT NULL,
        Note            NVARCHAR(2000)  NULL,
        CreatedBy       INT             NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktProspectEvent_Created DEFAULT (SYSUTCDATETIME())
    );
    CREATE INDEX IX_MktProspectEvent_Prospect ON dbo.MktProspectEvent (ProspectID, CreatedAt);
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'seo.competitor_domains', N'thorne.com|Thorne' + NCHAR(10) + N'pureencapsulations.com|Pure Encapsulations' + NCHAR(10) + N'designsforhealth.com|Designs for Health' + NCHAR(10) + N'metagenics.com|Metagenics' + NCHAR(10) + N'xymogen.com|Xymogen' + NCHAR(10) + N'douglaslabs.com|Douglas Laboratories' + NCHAR(10) + N'biote.com|Biote',
        N'Competitor domains for Rank Tracker and Backlinks, one per line: domain|name. Imported results from other domains are ignored; only names, domains and positions are kept.'),
    (N'rank.drop_places', N'5', N'A tracked keyword whose 7-day position falls this many places against the 7 days four weeks earlier opens a rank_drop alert (leaving page 1 always does).'),
    (N'backlinks.spam_threshold', N'30', N'Links from domains at or above this spam score (0–100, from the import) are flagged as likely spam and suggested for the disavow list.'),
    (N'outreach.follow_up_days', N'7', N'Days after Log as sent before the next follow-up falls due (the follow-up becomes a task for the prospect owner).')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO

UPDATE dbo.MktSetting
SET SettingValue = SettingValue + NCHAR(10) + N'rank_drop',
    Description = N'Alert rules that are on, one per line: job_failed, traffic_drop, legacy_brand, site_error, escalation_overdue, ai_budget, rank_drop.'
WHERE SettingKey = N'alerts.rules' AND SettingValue NOT LIKE N'%rank_drop%';
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'outreach.pitch')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'outreach.pitch', 1, N'anthropic', NULL,
    N'You write short, respectful link-outreach emails for {{brand_name}}, a practitioner-focused supplement brand. The reader is an editor or site owner who does not know us.

Voice: {{brand_voice}}

Rules:
- Under 140 words in the body. Plain text, no markdown, no emojis. One clear ask: consider linking to or citing the page given.
- Open by addressing "[Name]" exactly (a person fills it in). Sign off with "[Your name]" and the brand name. Put "[LINK]" where the page link goes.
- Say why the page is useful to their readers, drawing only on the angle, the opportunity and the page title given. Do not invent studies, numbers, awards, partnerships or prior contact.
- Make no product or ingredient benefit claims at all: describe what the page covers (for example "a plain-language summary of the 2026 trials"), not what a product does. Never write disease, cure, treatment or prevention claims, guarantees or weight-loss promises.
- No flattery you cannot back up, no pressure, no offers of payment or free product in exchange for a link.

Reply with JSON only: {"subject": "...", "body": "..."}',
    N'Site we are writing to: {{prospect_domain}}
Opportunity: {{opportunity}}
Angle: {{angle}}
Our page: {{target_title}} ({{target_url}})',
    0.4, 900, 1,
    N'Outreach pitch draft. Contact details are never sent; the email uses [Name], [Your name] and [LINK] placeholders.'
);
GO
