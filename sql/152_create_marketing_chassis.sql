/*
  NutraAxis Operations — Marketing & Research chassis (SEO Ops S0)

  Creates:
    dbo.MktSetting   — key/value settings (brand voice, audiences, AI defaults, harvest limits)
    dbo.MktPrompt    — versioned AI prompt library (Prompt Lab)
    dbo.MktApiUsage  — one row per external API / AI call (tokens, units, cost)

  Job runs use the existing dbo.ProcessExecutionLog (codes research-*, engagement-*, seo-*).
  Operator writes use the existing dbo.AuditChangeLog.

  Reverse:
    DROP TABLE dbo.MktApiUsage; DROP TABLE dbo.MktPrompt; DROP TABLE dbo.MktSetting;
*/

IF OBJECT_ID(N'dbo.MktSetting', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktSetting (
        SettingKey    NVARCHAR(100)  NOT NULL CONSTRAINT PK_MktSetting PRIMARY KEY,
        SettingValue  NVARCHAR(MAX)  NULL,
        Description   NVARCHAR(500)  NULL,
        UpdatedAt     DATETIME2(0)   NOT NULL CONSTRAINT DF_MktSetting_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedBy     INT            NULL
    );
END
GO

IF OBJECT_ID(N'dbo.MktPrompt', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktPrompt (
        PromptID      INT            NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktPrompt PRIMARY KEY,
        PromptKey     NVARCHAR(100)  NOT NULL,
        Version       INT            NOT NULL,
        Provider      NVARCHAR(20)   NOT NULL CONSTRAINT DF_MktPrompt_Provider DEFAULT (N'anthropic'),
        Model         NVARCHAR(100)  NULL,
        SystemPrompt  NVARCHAR(MAX)  NULL,
        UserTemplate  NVARCHAR(MAX)  NOT NULL,
        Temperature   DECIMAL(3, 2)  NULL,
        MaxTokens     INT            NOT NULL CONSTRAINT DF_MktPrompt_MaxTokens DEFAULT (2000),
        IsActive      BIT            NOT NULL CONSTRAINT DF_MktPrompt_IsActive DEFAULT (0),
        Notes         NVARCHAR(1000) NULL,
        CreatedAt     DATETIME2(0)   NOT NULL CONSTRAINT DF_MktPrompt_CreatedAt DEFAULT (SYSUTCDATETIME()),
        CreatedBy     INT            NULL,
        CONSTRAINT UQ_MktPrompt_KeyVersion UNIQUE (PromptKey, Version),
        CONSTRAINT CK_MktPrompt_Provider CHECK (Provider IN (N'anthropic', N'openai'))
    );

    CREATE UNIQUE INDEX UX_MktPrompt_Active
        ON dbo.MktPrompt (PromptKey)
        WHERE IsActive = 1;
END
GO

IF OBJECT_ID(N'dbo.MktApiUsage', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktApiUsage (
        UsageID        BIGINT          NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktApiUsage PRIMARY KEY,
        CreatedAt      DATETIME2(0)    NOT NULL CONSTRAINT DF_MktApiUsage_CreatedAt DEFAULT (SYSUTCDATETIME()),
        Provider       NVARCHAR(30)    NOT NULL,
        Operation      NVARCHAR(100)   NOT NULL,
        Mode           NVARCHAR(20)    NOT NULL,
        PromptKey      NVARCHAR(100)   NULL,
        PromptVersion  INT             NULL,
        Model          NVARCHAR(100)   NULL,
        InputTokens    INT             NULL,
        OutputTokens   INT             NULL,
        Units          INT             NULL,
        CostUsd        DECIMAL(12, 6)  NULL,
        Ok             BIT             NOT NULL CONSTRAINT DF_MktApiUsage_Ok DEFAULT (1),
        ErrorMessage   NVARCHAR(1000)  NULL,
        ProcessLogID   INT             NULL,
        UserID         INT             NULL,
        RefType        NVARCHAR(40)    NULL,
        RefID          BIGINT          NULL,
        CONSTRAINT CK_MktApiUsage_Mode CHECK (Mode IN (N'realtime', N'batch', N'agent', N'api'))
    );

    CREATE INDEX IX_MktApiUsage_CreatedAt ON dbo.MktApiUsage (CreatedAt DESC) INCLUDE (Provider, Operation, CostUsd);
    CREATE INDEX IX_MktApiUsage_Ref ON dbo.MktApiUsage (RefType, RefID);
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'brand.name',               N'NutraAxis Labs', N'Brand name used in generated copy.'),
    (N'brand.site_url',           N'https://nutraaxislabs.com', N'Base URL for CTA links and UTM tagging.'),
    (N'brand.voice',              N'Evidence-led, practitioner-respectful, plain English. No hype, no disease claims, no guarantees.', N'Brand voice instructions injected into generation prompts.'),
    (N'brand.audiences',          N'practitioner|Healthcare practitioners and clinic owners' + NCHAR(10) + N'consumer|Health-conscious adults buying through a practitioner', N'One audience per line: key|description.'),
    (N'brand.terms',              N'NutraAxis' + NCHAR(10) + N'NutraAxis Labs', N'Brand terms, one per line.'),
    (N'brand.competitors',        N'', N'Competitor domains, one per line.'),
    (N'ai.default_provider',      N'anthropic', N'anthropic or openai — used when a prompt does not specify.'),
    (N'ai.anthropic.model',       N'claude-sonnet-4-5', N'Default Anthropic model id.'),
    (N'ai.openai.model',          N'gpt-5-mini', N'Default OpenAI model id.'),
    (N'ai.pricing',               N'{"claude-sonnet-4-5":{"in":3,"out":15},"claude-haiku-4-5":{"in":1,"out":5},"gpt-5":{"in":1.25,"out":10},"gpt-5-mini":{"in":0.25,"out":2}}', N'USD per 1M tokens by model (batch runs are billed at 50%).'),
    (N'ai.monthly_budget_usd',    N'150', N'Jobs stop submitting AI work once month-to-date AI cost reaches this amount.'),
    (N'harvest.user_agent',       N'NutraAxisResearchBot/1.0 (+https://nutraaxislabs.com)', N'User-Agent header for feed fetches and crawls.'),
    (N'harvest.auto_pause_failures', N'5', N'Consecutive failures before a source is auto-paused.'),
    (N'harvest.max_items_per_run', N'50', N'Maximum new items stored per source per run.'),
    (N'research.relevance_threshold', N'0.6', N'Items scoring below this (0–1) for every interest are discarded.')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO
