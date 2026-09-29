/*
  NutraAxis Operations — Marketing Topic Synthesis (SEO Ops S1b: batch scoring, clustering, Topic Board)

  Alters:
    dbo.MktHarvestedItem — AI scoring columns (max relevance, primary interest, evidence type, tags, study facts)

  Creates:
    dbo.MktItemScore   — relevance (0–1) of each scored item to each interest
    dbo.MktAiBatch     — provider batch jobs (Anthropic Message Batches) and their cost
    dbo.MktTopic       — candidate / accepted topics produced by clustering, with trend signals and the human angle
    dbo.MktTopicItem   — items grouped under a topic (IsEvidence marks items people chose as supporting evidence)
    dbo.MktTopicClaim  — approved claims linked to an accepted topic

  Seeds settings (research.score_* / research.cluster_*) and prompts research.score_item, research.cluster_topics.

  Reverse (in order):
    DROP TABLE dbo.MktTopicClaim; DROP TABLE dbo.MktTopicItem; DROP TABLE dbo.MktTopic;
    DROP TABLE dbo.MktAiBatch; DROP TABLE dbo.MktItemScore;
    ALTER TABLE dbo.MktHarvestedItem DROP COLUMN RelevanceMax, PrimaryInterestID, TherapeuticArea, EvidenceType,
      AiSummary, TagsJson, StudyJson, ScoredAt, ScoreBatchID;   (drop IX_MktHarvestedItem_Scored first)
    DELETE FROM dbo.MktPrompt WHERE PromptKey IN (N'research.score_item', N'research.cluster_topics');
    DELETE FROM dbo.MktSetting WHERE SettingKey LIKE N'research.score[_]%' OR SettingKey LIKE N'research.cluster[_]%';
*/

IF COL_LENGTH(N'dbo.MktHarvestedItem', N'RelevanceMax') IS NULL
BEGIN
    ALTER TABLE dbo.MktHarvestedItem ADD
        RelevanceMax       DECIMAL(4, 3)   NULL,
        PrimaryInterestID  INT             NULL,
        TherapeuticArea    NVARCHAR(100)   NULL,
        EvidenceType       NVARCHAR(30)    NULL,
        AiSummary          NVARCHAR(1000)  NULL,
        TagsJson           NVARCHAR(MAX)   NULL,
        StudyJson          NVARCHAR(MAX)   NULL,
        ScoredAt           DATETIME2(0)    NULL,
        ScoreBatchID       INT             NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_MktHarvestedItem_Scored' AND object_id = OBJECT_ID(N'dbo.MktHarvestedItem'))
    CREATE INDEX IX_MktHarvestedItem_Scored
        ON dbo.MktHarvestedItem (Status, RelevanceMax DESC)
        INCLUDE (PrimaryInterestID, EvidenceType, FetchedAt, ScoreBatchID);
GO

IF OBJECT_ID(N'dbo.MktItemScore', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktItemScore (
        ItemID      BIGINT         NOT NULL CONSTRAINT FK_MktItemScore_Item REFERENCES dbo.MktHarvestedItem (ItemID) ON DELETE CASCADE,
        InterestID  INT            NOT NULL CONSTRAINT FK_MktItemScore_Interest REFERENCES dbo.MktInterest (InterestID) ON DELETE CASCADE,
        Relevance   DECIMAL(4, 3)  NOT NULL,
        CONSTRAINT PK_MktItemScore PRIMARY KEY (ItemID, InterestID)
    );

    CREATE INDEX IX_MktItemScore_Interest ON dbo.MktItemScore (InterestID, Relevance DESC);
END
GO

IF OBJECT_ID(N'dbo.MktAiBatch', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktAiBatch (
        BatchID          INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktAiBatch PRIMARY KEY,
        Provider         NVARCHAR(20)    NOT NULL,
        ExternalBatchID  NVARCHAR(100)   NULL,
        Purpose          NVARCHAR(50)    NOT NULL,
        PromptKey        NVARCHAR(100)   NULL,
        PromptVersion    INT             NULL,
        Model            NVARCHAR(100)   NULL,
        Status           NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktAiBatch_Status DEFAULT (N'submitted'),
        ExternalStatus   NVARCHAR(30)    NULL,
        ItemCount        INT             NOT NULL CONSTRAINT DF_MktAiBatch_ItemCount DEFAULT (0),
        SucceededCount   INT             NULL,
        ErroredCount     INT             NULL,
        InputTokens      BIGINT          NULL,
        OutputTokens     BIGINT          NULL,
        CostUsd          DECIMAL(12, 6)  NULL,
        SubmittedAt      DATETIME2(0)    NOT NULL CONSTRAINT DF_MktAiBatch_SubmittedAt DEFAULT (SYSUTCDATETIME()),
        CollectedAt      DATETIME2(0)    NULL,
        ErrorMessage     NVARCHAR(1000)  NULL,
        ProcessLogID     INT             NULL,
        CONSTRAINT CK_MktAiBatch_Status CHECK (Status IN (N'submitted', N'collected', N'failed'))
    );

    CREATE INDEX IX_MktAiBatch_Status ON dbo.MktAiBatch (Status, SubmittedAt DESC);
END
GO

IF OBJECT_ID(N'dbo.MktTopic', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktTopic (
        TopicID                INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktTopic PRIMARY KEY,
        Title                  NVARCHAR(300)   NOT NULL,
        Summary                NVARCHAR(2000)  NULL,
        WhyItMatters           NVARCHAR(1000)  NULL,
        TherapeuticArea        NVARCHAR(100)   NULL,
        InterestID             INT             NULL CONSTRAINT FK_MktTopic_Interest REFERENCES dbo.MktInterest (InterestID) ON DELETE SET NULL,
        Status                 NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktTopic_Status DEFAULT (N'proposed'),
        MergedIntoTopicID      INT             NULL,
        Angle                  NVARCHAR(2000)  NULL,
        Audience               NVARCHAR(20)    NULL,
        AvoidNotes             NVARCHAR(1000)  NULL,
        DecisionNote           NVARCHAR(500)   NULL,
        IsEmerging             BIT             NOT NULL CONSTRAINT DF_MktTopic_IsEmerging DEFAULT (0),
        SuggestedInterestName  NVARCHAR(200)   NULL,
        SuggestedTermsJson     NVARCHAR(2000)  NULL,
        ItemCount              INT             NOT NULL CONSTRAINT DF_MktTopic_ItemCount DEFAULT (0),
        Items7d                INT             NOT NULL CONSTRAINT DF_MktTopic_Items7d DEFAULT (0),
        ItemsPrior7d           INT             NOT NULL CONSTRAINT DF_MktTopic_ItemsPrior7d DEFAULT (0),
        SourceDiversity        INT             NOT NULL CONSTRAINT DF_MktTopic_SourceDiversity DEFAULT (0),
        EvidenceCount          INT             NOT NULL CONSTRAINT DF_MktTopic_EvidenceCount DEFAULT (0),
        TrendScore             DECIMAL(8, 2)   NOT NULL CONSTRAINT DF_MktTopic_TrendScore DEFAULT (0),
        FirstItemAt            DATETIME2(0)    NULL,
        LastItemAt             DATETIME2(0)    NULL,
        ClusterLogID           INT             NULL,
        PromptVersion          INT             NULL,
        CreatedAt              DATETIME2(0)    NOT NULL CONSTRAINT DF_MktTopic_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt              DATETIME2(0)    NOT NULL CONSTRAINT DF_MktTopic_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedBy              INT             NULL,
        DecidedBy              INT             NULL,
        DecidedAt              DATETIME2(0)    NULL,
        CONSTRAINT CK_MktTopic_Status CHECK (Status IN (N'proposed', N'accepted', N'rejected', N'parked', N'merged')),
        CONSTRAINT CK_MktTopic_Audience CHECK (Audience IS NULL OR Audience IN (N'both', N'practitioner', N'consumer'))
    );

    CREATE INDEX IX_MktTopic_Status ON dbo.MktTopic (Status, TrendScore DESC);
    CREATE INDEX IX_MktTopic_Interest ON dbo.MktTopic (InterestID, Status);
END
GO

IF OBJECT_ID(N'dbo.MktTopicItem', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktTopicItem (
        TopicID     INT           NOT NULL CONSTRAINT FK_MktTopicItem_Topic REFERENCES dbo.MktTopic (TopicID) ON DELETE CASCADE,
        ItemID      BIGINT        NOT NULL CONSTRAINT FK_MktTopicItem_Item REFERENCES dbo.MktHarvestedItem (ItemID) ON DELETE CASCADE,
        IsEvidence  BIT           NOT NULL CONSTRAINT DF_MktTopicItem_IsEvidence DEFAULT (0),
        AddedAt     DATETIME2(0)  NOT NULL CONSTRAINT DF_MktTopicItem_AddedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT PK_MktTopicItem PRIMARY KEY (TopicID, ItemID)
    );

    CREATE INDEX IX_MktTopicItem_Item ON dbo.MktTopicItem (ItemID);
END
GO

IF OBJECT_ID(N'dbo.MktTopicClaim', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktTopicClaim (
        TopicID   INT           NOT NULL CONSTRAINT FK_MktTopicClaim_Topic REFERENCES dbo.MktTopic (TopicID) ON DELETE CASCADE,
        ClaimID   INT           NOT NULL CONSTRAINT FK_MktTopicClaim_Claim REFERENCES dbo.MktClaim (ClaimID) ON DELETE CASCADE,
        AddedAt   DATETIME2(0)  NOT NULL CONSTRAINT DF_MktTopicClaim_AddedAt DEFAULT (SYSUTCDATETIME()),
        AddedBy   INT           NULL,
        CONSTRAINT PK_MktTopicClaim PRIMARY KEY (TopicID, ClaimID)
    );
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'research.score_batch_min_items', N'25', N'Submit a scoring batch once this many items are waiting (or the oldest has waited 12 hours).'),
    (N'research.score_batch_max_items', N'1000', N'Maximum items per scoring batch.'),
    (N'research.score_text_chars', N'1800', N'Characters of item text sent for scoring (summary first, then body).'),
    (N'research.cluster_lookback_days', N'21', N'Clustering considers scored items fetched within this many days.'),
    (N'research.cluster_max_items', N'250', N'Maximum scored items sent to one clustering run (highest relevance first).')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'research.score_item')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'research.score_item', 1, N'anthropic', N'claude-haiku-4-5',
    N'You triage harvested content for {{brand_name}}, a practitioner-channel nutraceutical brand. For each item you score how useful it is as source material for content on each watched interest, tag it, and extract study facts. Use only what the item text says; never add facts from memory.

WATCHED INTERESTS (id | name | scope):
{{interests}}

THERAPEUTIC AREAS (use these exact names):
{{therapeutic_areas}}

PRODUCTS (name | area | formula):
{{products}}

COMPETITORS (tag evidence_type "competitor" when the item is about one of them):
{{competitors}}

EVIDENCE TYPES:
- peer_reviewed: journal article, abstract, preprint, or registered clinical trial
- regulatory: FDA, FTC, NIH, state or other agency action, rule or guidance
- news: trade or general press, press release, conference coverage
- competitor: a competitor company''s own news, product or content
- opinion: blog, forum, social post, podcast

SCORING (0.0 to 1.0 per interest):
- 0.8–1.0: directly about the interest''s scope or a product ingredient, with substance a practitioner would value
- 0.5–0.7: clearly related, useful background
- 0.2–0.4: tangential mention
- Omit interests below 0.2. Animal-only, in-vitro-only or off-topic items score low everywhere.
- products: list only products whose ingredients or intended use the item directly concerns.
- study: only for peer_reviewed human research; otherwise null. Use null for any fact the text does not state.

Return ONLY a JSON object, no prose:
{"scores": {"<interest id>": 0.0}, "therapeutic_areas": ["..."], "products": ["..."], "evidence_type": "peer_reviewed|regulatory|news|competitor|opinion", "summary": "one neutral sentence on what the item reports", "keywords": ["up to 5 short search phrases"], "study": null or {"design": "", "population": "", "n": null, "intervention": "", "dose": "", "duration": "", "outcomes": "", "result": ""}}',
    N'Source: {{source}} ({{source_type}})
Published: {{published}}
Title: {{title}}
URL: {{url}}

Text:
{{text}}',
    0.00, 700, 1,
    N'Seed prompt (S1b). Runs through the Anthropic Message Batches API (50% price); the system prompt is cached across the batch.'
);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'research.cluster_topics')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'research.cluster_topics', 1, N'anthropic', NULL,
    N'You are the content strategist for {{brand_name}}, a practitioner-channel nutraceutical brand. You group recently harvested, already-scored items into candidate content topics that a marketing team can accept, reject or park. A topic is a specific, current story or question (for example "Berberine and GLP-1 tolerability in 2026 trials"), not a whole category ("Metabolic health").

Rules:
- Every new topic needs at least 2 items, ideally from different sources. Items that fit nowhere stay unassigned.
- Each item belongs to at most one topic.
- If items extend an OPEN TOPIC listed below, return that topic''s id in existing_topic_id instead of creating a near-duplicate.
- Titles and summaries are neutral and factual. Do not make claims about {{brand_name}} products and do not compare products to competitors.
- why_it_matters: one sentence on why practitioners would care now.
- Set emerging = true only when the theme is not covered by any watched interest; then suggest a new interest name and 3–6 include terms.
- Use interest ids and therapeutic area names exactly as listed.

Return ONLY a JSON object, no prose:
{"topics": [{"existing_topic_id": null, "title": "", "summary": "2-3 sentences", "why_it_matters": "", "interest_id": null, "therapeutic_area": "", "item_ids": [0], "emerging": false, "suggested_interest": null or {"name": "", "include_terms": [""]}}]}',
    N'WATCHED INTERESTS (id | name):
{{interests}}

THERAPEUTIC AREAS:
{{therapeutic_areas}}

OPEN TOPICS (id | status | interest | title):
{{open_topics}}

ITEMS (#id [evidence] [interests] title — summary (domain, date)):
{{items}}',
    0.30, 12000, 1,
    N'Seed prompt (S1b). Weekly or on-demand clustering of scored items into Topic Board candidates.'
);
GO
