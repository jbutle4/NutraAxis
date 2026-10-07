-- S4 Harden: site audits (crawler + OpenRush import) → fingerprinted issues with per-URL tracking, fix specs,
-- fixed → recrawl → verified / reopened, and alert rules (job failed, traffic drop, legacy brand, site errors,
-- overdue compliance escalations).
-- Re-runnable: creates only what is missing.

IF COL_LENGTH('dbo.MktPage', 'ImagesMissingAlt') IS NULL
BEGIN
    ALTER TABLE dbo.MktPage ADD
        ImagesMissingAlt    INT             NULL,
        LegacyTerms         NVARCHAR(400)   NULL;
END
GO

IF OBJECT_ID('dbo.MktAudit', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktAudit (
        AuditID             INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_MktAudit PRIMARY KEY,
        Source              NVARCHAR(20)    NOT NULL,
        Scope               NVARCHAR(20)    NOT NULL,
        StartedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktAudit_Started DEFAULT (SYSUTCDATETIME()),
        FinishedAt          DATETIME2(0)    NULL,
        PagesAudited        INT             NOT NULL CONSTRAINT DF_MktAudit_Pages DEFAULT (0),
        Findings            INT             NOT NULL CONSTRAINT DF_MktAudit_Findings DEFAULT (0),
        NewIssues           INT             NOT NULL CONSTRAINT DF_MktAudit_New DEFAULT (0),
        NewUrls             INT             NOT NULL CONSTRAINT DF_MktAudit_NewUrls DEFAULT (0),
        ReopenedIssues      INT             NOT NULL CONSTRAINT DF_MktAudit_Reopened DEFAULT (0),
        ResolvedUrls        INT             NOT NULL CONSTRAINT DF_MktAudit_Resolved DEFAULT (0),
        VerifiedIssues      INT             NOT NULL CONSTRAINT DF_MktAudit_Verified DEFAULT (0),
        StillOpenIssues     INT             NOT NULL CONSTRAINT DF_MktAudit_Still DEFAULT (0),
        Skipped             INT             NOT NULL CONSTRAINT DF_MktAudit_Skipped DEFAULT (0),
        Score               DECIMAL(5, 1)   NULL,
        Summary             NVARCHAR(1000)  NULL,
        IssueID             INT             NULL,
        ProcessLogID        INT             NULL,
        CreatedBy           INT             NULL,
        CONSTRAINT CK_MktAudit_Source CHECK (Source IN (N'crawl', N'openrush')),
        CONSTRAINT CK_MktAudit_Scope CHECK (Scope IN (N'full', N'page', N'verify', N'sample'))
    );
END
GO

IF OBJECT_ID('dbo.MktIssue', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktIssue (
        IssueID             INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_MktIssue PRIMARY KEY,
        Fingerprint         BINARY(32)      NOT NULL,
        Code                NVARCHAR(60)    NOT NULL,
        Title               NVARCHAR(200)   NOT NULL,
        Category            NVARCHAR(20)    NOT NULL,
        Severity            NVARCHAR(10)    NOT NULL,
        Owner               NVARCHAR(20)    NOT NULL,
        Status              NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktIssue_Status DEFAULT (N'new'),
        OpenUrlCount        INT             NOT NULL CONSTRAINT DF_MktIssue_Open DEFAULT (0),
        Sources             NVARCHAR(100)   NULL,
        FirstSeenAt         DATETIME2(0)    NOT NULL CONSTRAINT DF_MktIssue_First DEFAULT (SYSUTCDATETIME()),
        LastSeenAt          DATETIME2(0)    NOT NULL CONSTRAINT DF_MktIssue_Last DEFAULT (SYSUTCDATETIME()),
        LastAuditID         INT             NULL,
        AssigneeUserID      INT             NULL,
        FixedAt             DATETIME2(0)    NULL,
        FixedBy             INT             NULL,
        FixNote             NVARCHAR(2000)  NULL,
        VerifiedAt          DATETIME2(0)    NULL,
        VerifyNote          NVARCHAR(1000)  NULL,
        ReopenedAt          DATETIME2(0)    NULL,
        ReopenCount         INT             NOT NULL CONSTRAINT DF_MktIssue_Reopens DEFAULT (0),
        IgnoredAt           DATETIME2(0)    NULL,
        IgnoredBy           INT             NULL,
        IgnoreReason        NVARCHAR(1000)  NULL,
        FixSpec             NVARCHAR(MAX)   NULL,
        FixSpecAt           DATETIME2(0)    NULL,
        FixSpecBy           INT             NULL,
        FixSpecModel        NVARCHAR(100)   NULL,
        FixSpecCostUsd      DECIMAL(12, 6)  NULL,
        FixSpecUrlCount     INT             NULL,
        CreatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktIssue_Created DEFAULT (SYSUTCDATETIME()),
        UpdatedAt           DATETIME2(0)    NULL,
        UpdatedBy           INT             NULL,
        CONSTRAINT UQ_MktIssue_Fingerprint UNIQUE (Fingerprint),
        CONSTRAINT CK_MktIssue_Status CHECK (Status IN (N'new', N'open', N'fixed', N'verified', N'ignored')),
        CONSTRAINT CK_MktIssue_Severity CHECK (Severity IN (N'high', N'medium', N'low')),
        CONSTRAINT CK_MktIssue_Owner CHECK (Owner IN (N'developer', N'content'))
    );
END
GO

IF OBJECT_ID('dbo.MktIssueUrl', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktIssueUrl (
        IssueUrlID          INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_MktIssueUrl PRIMARY KEY,
        IssueID             INT             NOT NULL CONSTRAINT FK_MktIssueUrl_Issue REFERENCES dbo.MktIssue (IssueID) ON DELETE CASCADE,
        PageID              INT             NULL,
        PageKey             NVARCHAR(1000)  NOT NULL,
        KeyHash             BINARY(32)      NOT NULL,
        Url                 NVARCHAR(1000)  NOT NULL,
        Status              NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktIssueUrl_Status DEFAULT (N'open'),
        Detail              NVARCHAR(500)   NULL,
        Sources             NVARCHAR(100)   NULL,
        FirstSeenAt         DATETIME2(0)    NOT NULL CONSTRAINT DF_MktIssueUrl_First DEFAULT (SYSUTCDATETIME()),
        LastSeenAt          DATETIME2(0)    NOT NULL CONSTRAINT DF_MktIssueUrl_Last DEFAULT (SYSUTCDATETIME()),
        ResolvedAt          DATETIME2(0)    NULL,
        LastAuditID         INT             NULL,
        CONSTRAINT UQ_MktIssueUrl UNIQUE (IssueID, KeyHash),
        CONSTRAINT CK_MktIssueUrl_Status CHECK (Status IN (N'open', N'resolved'))
    );
    CREATE INDEX IX_MktIssueUrl_Page ON dbo.MktIssueUrl (PageID, Status);
END
GO

IF OBJECT_ID('dbo.MktAlert', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktAlert (
        AlertID             INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_MktAlert PRIMARY KEY,
        RuleKey             NVARCHAR(40)    NOT NULL,
        Fingerprint         BINARY(32)      NOT NULL,
        Subject             NVARCHAR(300)   NOT NULL,
        Severity            NVARCHAR(10)    NOT NULL,
        Title               NVARCHAR(300)   NOT NULL,
        Detail              NVARCHAR(2000)  NULL,
        Href                NVARCHAR(1000)  NULL,
        Status              NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktAlert_Status DEFAULT (N'open'),
        FirstAt             DATETIME2(0)    NOT NULL CONSTRAINT DF_MktAlert_First DEFAULT (SYSUTCDATETIME()),
        LastAt              DATETIME2(0)    NOT NULL CONSTRAINT DF_MktAlert_Last DEFAULT (SYSUTCDATETIME()),
        NotifiedAt          DATETIME2(0)    NULL,
        NotifyError         NVARCHAR(500)   NULL,
        AcknowledgedAt      DATETIME2(0)    NULL,
        AcknowledgedBy      INT             NULL,
        ResolvedAt          DATETIME2(0)    NULL,
        ProcessLogID        INT             NULL,
        CONSTRAINT CK_MktAlert_Status CHECK (Status IN (N'open', N'acknowledged', N'resolved')),
        CONSTRAINT CK_MktAlert_Severity CHECK (Severity IN (N'high', N'medium', N'low'))
    );
    CREATE UNIQUE INDEX UX_MktAlert_Active ON dbo.MktAlert (Fingerprint) WHERE Status IN (N'open', N'acknowledged');
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'brand.legacy_terms', N'NutraSync' + NCHAR(10) + N'NutrAxis', N'Old or misspelled brand names that must not appear on live pages, one per line (whole words, any case). A hit opens a legacy_brand issue and an alert.'),
    (N'brand.legacy_allow_paths', N'/nutrasync-landing', N'Pages allowed to mention the legacy names (e.g. the migration landing page), one path prefix per line.'),
    (N'seo.title_min', N'30', N'Titles shorter than this many characters get a short_title issue.'),
    (N'issues.platform_notes', N'nutraaxislabs.com is an Adobe Commerce storefront on Adobe Edge Delivery Services (document-authored pages plus Commerce drop-in blocks for cart, checkout, account and PDP), served through Fastly. /nav and /footer are shared fragments loaded by every page. Page titles, meta descriptions and body copy are authored per page (page metadata block); templates, blocks, head.html, JSON-LD and robots/sitemap rules live in the storefront code repository. Changes publish through the Edge Delivery preview → publish flow.', N'Context given to the fix-spec AI so developer specs name the right place to change.'),
    (N'alerts.rules', N'job_failed' + NCHAR(10) + N'traffic_drop' + NCHAR(10) + N'legacy_brand' + NCHAR(10) + N'site_error' + NCHAR(10) + N'escalation_overdue', N'Alert rules that are on, one per line: job_failed, traffic_drop, legacy_brand, site_error, escalation_overdue.'),
    (N'alerts.traffic_drop_pct', N'30', N'traffic_drop fires when the last 7 days fall this many percent below the average of the 4 weeks before.'),
    (N'alerts.traffic_min_sessions', N'100', N'traffic_drop needs at least this many GA4 sessions per week in the baseline (otherwise the site is too small to judge).'),
    (N'alerts.traffic_min_clicks', N'50', N'traffic_drop needs at least this many Search Console clicks per week in the baseline.'),
    (N'alerts.recipients', N'', N'Email addresses for alerts, one per line. Blank = everyone with full Marketing access.')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'seo.fix_spec')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'seo.fix_spec', 1, N'anthropic', NULL,
    N'You write developer fix specs for technical SEO issues found on {{brand_name}}''s website. The reader is a web developer or site author who was not part of the audit.

SITE: {{platform_notes}}

Rules:
- Use only the facts given (issue, advice, affected URLs and what the crawler saw on each). Do not invent URLs, numbers or findings.
- Say whether the fix belongs in shared code (template, block, head, fragment, sitemap / robots rules) or in each page''s authored content, and why. When one template change fixes many URLs, say so and lead with it.
- Pages under /customer, /checkout, /cart, /order-*, /b2b and similar account or transactional paths are usually meant to stay out of search; if an issue on those pages is expected, say "likely intended — confirm and ignore" instead of prescribing a fix.
- Where new copy is needed (titles, meta descriptions, H1s, body text), you may suggest drafts, marked "Draft — needs editorial review". Keep them factual about the page; never write health, disease or efficacy claims (no "treats", "cures", "prevents", "boosts immunity", weight-loss promises); anything touching health goes through the Content Pipeline with compliance review.
- The fix is verified automatically: after it is marked fixed, the crawler re-fetches each URL''s raw HTML (no JavaScript) and checks the same rule. Say exactly what that check will look for, so the developer can confirm before marking it fixed. If the content is only added by client-side JavaScript, warn that the raw-HTML check will still fail.

Reply in Markdown with these sections:
## Summary
## Why it matters
## Where to fix
## Steps
## Affected URLs
(a table: URL | what the crawler saw | change needed)
## How it will be verified
## Effort
(S / M / L with one line of reasoning)',
    N'ISSUE: {{title}} ({{code}}) — severity {{severity}}, owner {{owner}}
ADVICE: {{advice}}
OPEN ON {{url_count}} URL(S){{truncated_note}}

AFFECTED URLS (JSON — url, page type, status, title, meta description, H1s, word count, canonical, robots, images missing alt, detail):
{{urls}}',
    0.2, 4000, 1, N'S4 — one call per issue, on demand.'
);
GO
