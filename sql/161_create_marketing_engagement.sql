-- S3 Engagement loop: per-asset metrics (manual / import until GHL), Response Inbox with triage and escalation,
-- asset → campaign → topic → interest scores, interest weights, and the Monday digest.
-- Re-runnable: creates only what is missing.

IF COL_LENGTH('dbo.MktInterest', 'RelevanceWeight') IS NULL
BEGIN
    ALTER TABLE dbo.MktInterest ADD
        RelevanceWeight     DECIMAL(4, 2)   NOT NULL CONSTRAINT DF_MktInterest_Weight DEFAULT (1.00),
        PerformanceScore    DECIMAL(5, 1)   NULL,
        SuggestedPriority   TINYINT         NULL,
        PerformanceAt       DATETIME2(0)    NULL;
END
GO

IF OBJECT_ID('dbo.MktAssetMetric', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktAssetMetric (
        MetricID            INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_MktAssetMetric PRIMARY KEY,
        AssetID             INT             NOT NULL CONSTRAINT FK_MktAssetMetric_Asset REFERENCES dbo.MktAsset (AssetID),
        AsOfDate            DATE            NOT NULL,
        Source              NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktAssetMetric_Source DEFAULT (N'manual'),
        Impressions         INT             NULL,
        Reach               INT             NULL,
        Likes               INT             NULL,
        Comments            INT             NULL,
        Shares              INT             NULL,
        Saves               INT             NULL,
        LinkClicks          INT             NULL,
        VideoViews          INT             NULL,
        EmailSent           INT             NULL,
        EmailDelivered      INT             NULL,
        EmailOpens          INT             NULL,
        EmailClicks         INT             NULL,
        EmailReplies        INT             NULL,
        EmailUnsubscribes   INT             NULL,
        EmailBounces        INT             NULL,
        Leads               INT             NULL,
        Note                NVARCHAR(500)   NULL,
        EnteredBy           INT             NULL,
        EnteredAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktAssetMetric_At DEFAULT (SYSUTCDATETIME()),
        UpdatedBy           INT             NULL,
        UpdatedAt           DATETIME2(0)    NULL,
        CONSTRAINT UQ_MktAssetMetric UNIQUE (AssetID, AsOfDate, Source),
        CONSTRAINT CK_MktAssetMetric_Source CHECK (Source IN (N'manual', N'import', N'ghl', N'platform'))
    );
END
GO

IF OBJECT_ID('dbo.MktResponse', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktResponse (
        ResponseID          INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_MktResponse PRIMARY KEY,
        AssetID             INT             NULL CONSTRAINT FK_MktResponse_Asset REFERENCES dbo.MktAsset (AssetID),
        CampaignID          INT             NULL CONSTRAINT FK_MktResponse_Campaign REFERENCES dbo.MktCampaign (CampaignID),
        Channel             NVARCHAR(30)    NOT NULL,
        Kind                NVARCHAR(20)    NOT NULL,
        ExternalUrl         NVARCHAR(1000)  NULL,
        ReceivedAt          DATETIME2(0)    NOT NULL,
        BodyText            NVARCHAR(4000)  NOT NULL,
        RedactionCount      INT             NOT NULL CONSTRAINT DF_MktResponse_Redacted DEFAULT (0),
        Triage              NVARCHAR(20)    NULL,
        TriageSource        NVARCHAR(10)    NULL,
        TriageConfidence    DECIMAL(4, 3)   NULL,
        Sentiment           NVARCHAR(10)    NULL,
        TriageReason        NVARCHAR(1000)  NULL,
        SuggestedAction     NVARCHAR(1000)  NULL,
        TriagedAt           DATETIME2(0)    NULL,
        TriagedBy           INT             NULL,
        TriageError         NVARCHAR(500)   NULL,
        TriageAttempts      INT             NOT NULL CONSTRAINT DF_MktResponse_Attempts DEFAULT (0),
        Status              NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktResponse_Status DEFAULT (N'new'),
        EscalatedAt         DATETIME2(0)    NULL,
        EscalationDueAt     DATETIME2(0)    NULL,
        EscalationNotifiedAt DATETIME2(0)   NULL,
        ComplianceNote      NVARCHAR(2000)  NULL,
        ComplianceBy        INT             NULL,
        ComplianceAt        DATETIME2(0)    NULL,
        ReplyNote           NVARCHAR(2000)  NULL,
        RepliedBy           INT             NULL,
        RepliedAt           DATETIME2(0)    NULL,
        ClosedBy            INT             NULL,
        ClosedAt            DATETIME2(0)    NULL,
        CreatedBy           INT             NULL,
        CreatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktResponse_At DEFAULT (SYSUTCDATETIME()),
        UpdatedAt           DATETIME2(0)    NULL,
        CONSTRAINT CK_MktResponse_Kind CHECK (Kind IN (N'comment', N'reply', N'dm', N'email_reply', N'mention', N'review', N'other')),
        CONSTRAINT CK_MktResponse_Triage CHECK (Triage IS NULL OR Triage IN (N'question', N'praise', N'complaint', N'claims_risk', N'adverse_event', N'spam', N'other')),
        CONSTRAINT CK_MktResponse_TriageSource CHECK (TriageSource IS NULL OR TriageSource IN (N'ai', N'rule', N'manual')),
        CONSTRAINT CK_MktResponse_Sentiment CHECK (Sentiment IS NULL OR Sentiment IN (N'positive', N'neutral', N'negative')),
        CONSTRAINT CK_MktResponse_Status CHECK (Status IN (N'new', N'open', N'escalated', N'replied', N'closed'))
    );
    CREATE INDEX IX_MktResponse_Status ON dbo.MktResponse (Status, ReceivedAt);
    CREATE INDEX IX_MktResponse_Asset ON dbo.MktResponse (AssetID) WHERE AssetID IS NOT NULL;
END
GO

IF OBJECT_ID('dbo.MktEngagementScore', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktEngagementScore (
        ScoreID             INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_MktEngagementScore PRIMARY KEY,
        ScoreDate           DATE            NOT NULL,
        Level               NVARCHAR(10)    NOT NULL,
        RefID               INT             NOT NULL,
        Score               DECIMAL(5, 1)   NOT NULL,
        Points              DECIMAL(12, 2)  NOT NULL,
        Confidence          NVARCHAR(10)    NOT NULL,
        ChildCount          INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Children DEFAULT (0),
        Sessions            INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Sessions DEFAULT (0),
        EngagedSessions     INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Engaged DEFAULT (0),
        KeyEvents           INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Key DEFAULT (0),
        Transactions        INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Tx DEFAULT (0),
        Revenue             DECIMAL(14, 2)  NOT NULL CONSTRAINT DF_MktEngagementScore_Rev DEFAULT (0),
        Impressions         INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Impr DEFAULT (0),
        Interactions        INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Inter DEFAULT (0),
        EmailOpens          INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Opens DEFAULT (0),
        EmailClicks         INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Clicks DEFAULT (0),
        Leads               INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Leads DEFAULT (0),
        SearchClicks        INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Search DEFAULT (0),
        Responses           INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Resp DEFAULT (0),
        PositiveResponses   INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Pos DEFAULT (0),
        RiskResponses       INT             NOT NULL CONSTRAINT DF_MktEngagementScore_Risk DEFAULT (0),
        ComputedAt          DATETIME2(0)    NOT NULL CONSTRAINT DF_MktEngagementScore_At DEFAULT (SYSUTCDATETIME()),
        ProcessLogID        INT             NULL,
        CONSTRAINT UQ_MktEngagementScore UNIQUE (Level, RefID, ScoreDate),
        CONSTRAINT CK_MktEngagementScore_Level CHECK (Level IN (N'asset', N'content', N'campaign', N'topic', N'interest')),
        CONSTRAINT CK_MktEngagementScore_Conf CHECK (Confidence IN (N'low', N'medium', N'high'))
    );
    CREATE INDEX IX_MktEngagementScore_Date ON dbo.MktEngagementScore (ScoreDate, Level);
END
GO

IF OBJECT_ID('dbo.MktDigest', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktDigest (
        DigestID            INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_MktDigest PRIMARY KEY,
        PeriodStart         DATE            NOT NULL,
        PeriodEnd           DATE            NOT NULL,
        Status              NVARCHAR(20)    NOT NULL,
        Narrative           NVARCHAR(MAX)   NULL,
        FactsJson           NVARCHAR(MAX)   NULL,
        RecommendationsJson NVARCHAR(MAX)   NULL,
        DroppedJson         NVARCHAR(MAX)   NULL,
        TaskCount           INT             NOT NULL CONSTRAINT DF_MktDigest_Tasks DEFAULT (0),
        Model               NVARCHAR(100)   NULL,
        PromptVersion       INT             NULL,
        CostUsd             DECIMAL(12, 6)  NULL,
        ProcessLogID        INT             NULL,
        CreatedBy           INT             NULL,
        CreatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktDigest_At DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_MktDigest_Period UNIQUE (PeriodEnd),
        CONSTRAINT CK_MktDigest_Status CHECK (Status IN (N'ready', N'sparse'))
    );
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'engagement.weights', N'session|1' + NCHAR(10) + N'engaged_session|2' + NCHAR(10) + N'key_event|10' + NCHAR(10) + N'transaction|25' + NCHAR(10) + N'lead|15' + NCHAR(10) + N'like|0.5' + NCHAR(10) + N'comment|1' + NCHAR(10) + N'share|2' + NCHAR(10) + N'save|1' + NCHAR(10) + N'link_click|0.5' + NCHAR(10) + N'email_open|0.2' + NCHAR(10) + N'email_click|1' + NCHAR(10) + N'email_reply|3' + NCHAR(10) + N'search_click|1' + NCHAR(10) + N'positive_response|2', N'Engagement points per unit, one per line: metric|points. An asset''s score is 100 × points ÷ (points + engagement.half_points).'),
    (N'engagement.half_points', N'40', N'Points at which an asset scores 50 (the score curve flattens above this).'),
    (N'engagement.mature_days', N'7', N'Scores for assets posted fewer days ago than this are marked low confidence.'),
    (N'engagement.min_assets_for_weight', N'3', N'An interest needs this many medium / high confidence asset scores before its weight or priority moves.'),
    (N'engagement.weight_range', N'0.2', N'Most an interest''s relevance weight can move from 1.00 (0 turns weighting off). The weight multiplies harvested-item relevance when scoring.'),
    (N'engagement.escalation_hours', N'24', N'Claims-risk and possible adverse-event responses must be reviewed by compliance within this many hours.'),
    (N'engagement.adverse_terms', N'side effect' + NCHAR(10) + N'side-effect' + NCHAR(10) + N'reaction' + NCHAR(10) + N'allergic' + NCHAR(10) + N'rash' + NCHAR(10) + N'hives' + NCHAR(10) + N'nausea' + NCHAR(10) + N'vomit' + NCHAR(10) + N'dizzy' + NCHAR(10) + N'palpitations' + NCHAR(10) + N'hospital' + NCHAR(10) + N'emergency room' + NCHAR(10) + N'made me sick' + NCHAR(10) + N'got sick', N'Words that force a response to "possible adverse event" regardless of the AI label, one per line.'),
    (N'engagement.digest_max_tasks', N'5', N'Most tasks the Monday digest opens.')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO

UPDATE dbo.MktSetting SET SettingValue = SettingValue + NCHAR(10) + N'reply|1'
WHERE SettingKey = N'tasks.sla_days' AND SettingValue NOT LIKE N'%reply|%';
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'engagement.response_triage')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'engagement.response_triage', 1, N'anthropic', N'claude-haiku-4-5',
    N'You triage public responses (comments, replies, DMs, email replies) to social posts and emails from {{brand_name}}, a practitioner-channel dietary supplement brand. Names, handles, emails and phone numbers have already been replaced with [name], [handle], [email], [phone].

Pick exactly one label:
- adverse_event: the person describes a possible side effect, reaction, illness, injury or hospital visit after using a product (even if unsure it was the product). When in doubt between this and anything else, choose adverse_event.
- claims_risk: any reply from us would risk a disease or unapproved health claim — the person asks whether a product treats, cures, prevents or replaces medication for a condition (diabetes, depression, cancer, obesity, etc.), mentions drug interactions or prescription drugs (including GLP-1 medications), pregnancy, children, dosing beyond the label, or states a testimonial that a product cured or treated a condition.
- complaint: unhappy about a product, order, shipping, price, or experience, with no health effect described.
- question: a general question (availability, where to buy, ingredients, practitioner program, how to take it per label) that needs a reply and carries no claims risk.
- praise: positive feedback with no health claim.
- spam: promotions, bots, unrelated links.
- other: none of the above.

Reply with JSON only:
{"label": "...", "confidence": 0.0-1.0, "sentiment": "positive|neutral|negative", "reason": "one sentence", "suggested_action": "one sentence for the person handling it — never draft a health claim"}',
    N'CHANNEL: {{channel}} ({{kind}})
IN RESPONSE TO: {{context}}

RESPONSE TEXT:
{{text}}',
    0.0, 400, 1, N'S3 — one call per response; Haiku.'
);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'engagement.weekly_digest')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'engagement.weekly_digest', 1, N'anthropic', NULL,
    N'You write the Monday marketing digest for {{brand_name}}, a practitioner-channel dietary supplement brand with a new website and small audience. Numbers are small; say so plainly and never overstate a trend from a handful of visits. Low-confidence scores are early reads, not conclusions.

Use ONLY the facts provided. Every recommendation must cite at least one item from the facts by its type and id (asset, campaign, topic, interest, content, page, query). Do not invent numbers, items or ids.

Recommendation types:
- double_down: a topic, campaign or interest outperforming the others — make more like it.
- follow_up_series: a campaign or topic that earned engagement and deserves a next series.
- new_campaign: an accepted topic with no campaign yet.
- retire_interest: an interest that consistently scores low (or produces nothing) — consider pausing it.
- add_source: an interest or emerging topic that needs better sources.
- fix_page: a page with SEO issues that already gets visits or impressions.
- map_keyword: a search query with impressions that no page targets.
- reply_backlog: responses waiting for replies or compliance.
- other: anything else clearly supported by the facts.

Give at most {{max_tasks}} recommendations, most valuable first; fewer is fine when data is thin. Each is a task a person can finish in a week.

Reply with JSON only:
{"narrative": "Markdown, 120-250 words: what happened this week, what is working, what is not, with numbers", "recommendations": [{"type": "...", "title": "imperative, under 90 characters", "detail": "2-3 sentences: what to do and why, citing the numbers", "role": "coordinator|writer|editorial|compliance", "refs": [{"type": "asset|campaign|topic|interest|content|page|query", "id": "id from the facts"}]}]}',
    N'WEEK: {{period_start}} to {{period_end}}

FACTS (JSON):
{{facts}}',
    0.3, 3000, 1, N'S3 — weekly; recommendations are validated against the facts before tasks open.'
);
GO
