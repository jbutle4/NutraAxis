/*
  NutraAxis Operations — Marketing Content Pipeline + thin Task Engine (SEO Ops S1b)

  Creates:
    dbo.MktContent         — a long-form web piece moving idea → brief → draft → compliance_review → editorial → approved → published
    dbo.MktContentVersion  — immutable versions of the draft (AI draft, AI revision, manual edit) with their claims check
    dbo.MktContentReview   — brief approval, submission, compliance, editorial, publish and system history
    dbo.MktTask            — marketing to-dos: manual tasks plus tasks the pipeline, campaigns and calendar open and close

  Seeds settings (content.types, tasks.sla_days) and prompts content.brief, content.draft, content.revise.

  Reverse (in order):
    DROP TABLE dbo.MktTask; DROP TABLE dbo.MktContentReview; DROP TABLE dbo.MktContentVersion; DROP TABLE dbo.MktContent;
    DELETE FROM dbo.MktPrompt WHERE PromptKey LIKE N'content.%';
    DELETE FROM dbo.MktSetting WHERE SettingKey LIKE N'content.%' OR SettingKey LIKE N'tasks.%';
*/

IF OBJECT_ID(N'dbo.MktContent', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktContent (
        ContentID           INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktContent PRIMARY KEY,
        Title               NVARCHAR(300)   NOT NULL,
        ContentType         NVARCHAR(30)    NOT NULL CONSTRAINT DF_MktContent_Type DEFAULT (N'article'),
        Stage               NVARCHAR(30)    NOT NULL CONSTRAINT DF_MktContent_Stage DEFAULT (N'idea'),
        TopicID             INT             NULL CONSTRAINT FK_MktContent_Topic REFERENCES dbo.MktTopic (TopicID) ON DELETE SET NULL,
        ProductID           INT             NULL CONSTRAINT FK_MktContent_Product REFERENCES dbo.MktProduct (ProductID) ON DELETE SET NULL,
        KeywordID           INT             NULL CONSTRAINT FK_MktContent_Keyword REFERENCES dbo.MktKeyword (KeywordID) ON DELETE SET NULL,
        PrimaryKeyword      NVARCHAR(200)   NULL,
        SecondaryKeywords   NVARCHAR(1000)  NULL,
        Audience            NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktContent_Audience DEFAULT (N'both'),
        TargetWords         INT             NULL,
        TargetUrl           NVARCHAR(1000)  NULL,
        Notes               NVARCHAR(2000)  NULL,
        BriefJson           NVARCHAR(MAX)   NULL,
        BriefText           NVARCHAR(MAX)   NULL,
        BriefBy             INT             NULL,
        BriefAt             DATETIME2(0)    NULL,
        BriefLogID          INT             NULL,
        BriefApprovedBy     INT             NULL,
        BriefApprovedAt     DATETIME2(0)    NULL,
        CurrentVersionID    INT             NULL,
        SubmittedVersionID  INT             NULL,
        SubmittedBy         INT             NULL,
        SubmittedAt         DATETIME2(0)    NULL,
        ComplianceStatus    NVARCHAR(30)    NULL,
        ComplianceBy        INT             NULL,
        ComplianceAt        DATETIME2(0)    NULL,
        EditorialStatus     NVARCHAR(30)    NULL,
        EditorialBy         INT             NULL,
        EditorialAt         DATETIME2(0)    NULL,
        PublishedUrl        NVARCHAR(1000)  NULL,
        PublishedAt         DATETIME2(0)    NULL,
        PublishedBy         INT             NULL,
        OwnerUserID         INT             NULL,
        DueDate             DATE            NULL,
        CreatedBy           INT             NULL,
        CreatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktContent_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedBy           INT             NULL,
        UpdatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktContent_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_MktContent_Stage CHECK (Stage IN (
            N'idea', N'brief', N'draft', N'compliance_review', N'editorial', N'approved', N'published', N'monitoring', N'archived'
        )),
        CONSTRAINT CK_MktContent_Audience CHECK (Audience IN (N'both', N'practitioner', N'consumer')),
        CONSTRAINT CK_MktContent_ComplianceStatus CHECK (ComplianceStatus IS NULL OR ComplianceStatus IN (
            N'not_required', N'pending', N'approved', N'changes_requested'
        )),
        CONSTRAINT CK_MktContent_EditorialStatus CHECK (EditorialStatus IS NULL OR EditorialStatus IN (
            N'pending', N'approved', N'changes_requested'
        ))
    );

    CREATE INDEX IX_MktContent_Stage ON dbo.MktContent (Stage, UpdatedAt DESC);
    CREATE INDEX IX_MktContent_Topic ON dbo.MktContent (TopicID) WHERE TopicID IS NOT NULL;
END
GO

IF OBJECT_ID(N'dbo.MktContentVersion', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktContentVersion (
        VersionID         INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktContentVersion PRIMARY KEY,
        ContentID         INT             NOT NULL CONSTRAINT FK_MktContentVersion_Content REFERENCES dbo.MktContent (ContentID) ON DELETE CASCADE,
        VersionNo         INT             NOT NULL,
        Title             NVARCHAR(300)   NOT NULL,
        MetaTitle         NVARCHAR(200)   NULL,
        MetaDescription   NVARCHAR(400)   NULL,
        Body              NVARCHAR(MAX)   NOT NULL,
        WordCount         INT             NOT NULL CONSTRAINT DF_MktContentVersion_Words DEFAULT (0),
        Source            NVARCHAR(20)    NOT NULL,
        Note              NVARCHAR(500)   NULL,
        ClaimIdsJson      NVARCHAR(500)   NULL,
        ClaimsScore       DECIMAL(4, 1)   NULL,
        ClaimsCheckJson   NVARCHAR(MAX)   NULL,
        NeedsCompliance   BIT             NULL,
        ClaimsCheckedAt   DATETIME2(0)    NULL,
        GenerationLogID   INT             NULL,
        CreatedBy         INT             NULL,
        CreatedAt         DATETIME2(0)    NOT NULL CONSTRAINT DF_MktContentVersion_CreatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_MktContentVersion UNIQUE (ContentID, VersionNo),
        CONSTRAINT CK_MktContentVersion_Source CHECK (Source IN (N'ai_draft', N'ai_revision', N'manual'))
    );
END
GO

IF OBJECT_ID(N'dbo.MktContentReview', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktContentReview (
        ReviewID    INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktContentReview PRIMARY KEY,
        ContentID   INT             NOT NULL CONSTRAINT FK_MktContentReview_Content REFERENCES dbo.MktContent (ContentID) ON DELETE CASCADE,
        VersionID   INT             NULL,
        Gate        NVARCHAR(20)    NOT NULL,
        Decision    NVARCHAR(30)    NOT NULL,
        Note        NVARCHAR(2000)  NULL,
        UserID      INT             NULL,
        CreatedAt   DATETIME2(0)    NOT NULL CONSTRAINT DF_MktContentReview_CreatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_MktContentReview_Gate CHECK (Gate IN (N'brief', N'submit', N'compliance', N'editorial', N'publish', N'system'))
    );

    CREATE INDEX IX_MktContentReview_Content ON dbo.MktContentReview (ContentID, ReviewID DESC);
END
GO

IF OBJECT_ID(N'dbo.MktTask', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktTask (
        TaskID          INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktTask PRIMARY KEY,
        Title           NVARCHAR(300)   NOT NULL,
        Detail          NVARCHAR(2000)  NULL,
        TaskType        NVARCHAR(40)    NOT NULL CONSTRAINT DF_MktTask_Type DEFAULT (N'manual'),
        AssigneeRole    NVARCHAR(20)    NULL,
        AssigneeUserID  INT             NULL,
        RefType         NVARCHAR(20)    NULL,
        RefID           INT             NULL,
        Href            NVARCHAR(500)   NULL,
        DueDate         DATE            NULL,
        Priority        NVARCHAR(10)    NOT NULL CONSTRAINT DF_MktTask_Priority DEFAULT (N'normal'),
        Status          NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktTask_Status DEFAULT (N'open'),
        AutoKey         NVARCHAR(120)   NULL,
        CreatedBy       INT             NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktTask_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktTask_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        CompletedBy     INT             NULL,
        CompletedAt     DATETIME2(0)    NULL,
        CompletionNote  NVARCHAR(500)   NULL,
        CONSTRAINT CK_MktTask_Status CHECK (Status IN (N'open', N'done', N'cancelled')),
        CONSTRAINT CK_MktTask_Priority CHECK (Priority IN (N'normal', N'high')),
        CONSTRAINT CK_MktTask_Role CHECK (AssigneeRole IS NULL OR AssigneeRole IN (N'writer', N'compliance', N'editorial', N'coordinator'))
    );

    CREATE UNIQUE INDEX UX_MktTask_OpenAutoKey ON dbo.MktTask (AutoKey) WHERE Status = N'open' AND AutoKey IS NOT NULL;
    CREATE INDEX IX_MktTask_Open ON dbo.MktTask (Status, DueDate);
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'content.types', N'article|Article|1200|Evidence explainer for the topic: what the research shows, what it means in practice, where it is limited.
guide|Practitioner guide|1600|Practical guide for clinicians: how to think about the question, what to discuss with patients, what the evidence supports.
product_page|Product page|500|Product detail page copy built only from the product''s approved claims, formula and suggested use.
faq|FAQ page|700|Question-and-answer page targeting search questions around the keyword; short, direct answers.',
     N'Long-form content types, one per line: key|label|default word count|guidance for the brief and draft.'),
    (N'tasks.sla_days', N'brief|2
compliance|2
editorial|2
publish|3
schedule|2',
     N'Days allowed for automatic tasks, one per line: brief (approve a brief), compliance, editorial, publish (put approved content live and record the URL), schedule (put approved campaign assets on the calendar).')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'content.brief')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'content.brief', 1, N'anthropic', NULL,
    N'You are the content strategist for {{brand_name}}, a practitioner-channel nutraceutical brand. You write briefs for long-form web content that a writer (or a drafting model) will follow.
Voice: {{brand_voice}}
Audience: {{audience}}

COMPLIANCE RULES (non-negotiable):
{{rules}}

APPROVED CLAIMS — the only product or ingredient benefit statements the piece may make (id | product | wording; ‡ = needs the disclaimer):
{{approved_claims}}

Plan only what the evidence items and approved claims support. Do not invent statistics, studies, quotes or URLs. Search intent and structure should serve the reader first; keywords should appear naturally, never stuffed.',
    N'CONTENT TYPE: {{content_type}} — {{type_guidance}}
TARGET LENGTH: about {{target_words}} words
PRIMARY KEYWORD: {{primary_keyword}}
SECONDARY KEYWORDS: {{secondary_keywords}}

TOPIC: {{topic_title}}
Summary: {{topic_summary}}
Why it matters: {{topic_why}}
ANGLE: {{angle}}
AVOID: {{avoid}}
PRODUCT: {{product}}
EDITOR NOTES: {{notes}}

EVIDENCE ITEMS (cite by source name and year; use only facts stated here):
{{evidence}}

Keep it tight: 5-8 outline sections with 2-4 points each, every point under 25 words; at most 8 evidence lines, 5 key takeaways, 5 FAQs and 5 avoid lines.

Return ONLY a JSON object, no prose:
{"working_title": "", "search_intent": "what the searcher wants", "reader": "who reads this and what they need", "angle": "", "key_takeaways": [""], "outline": [{"heading": "H2 text", "points": ["what this section covers, which evidence it cites"]}], "evidence_to_cite": ["Source (year): the fact to use"], "claim_ids": [], "faqs": [{"question": "", "answer_notes": ""}], "internal_links": ["page on our site worth linking"], "cta": "", "meta_title": "under 60 characters", "meta_description": "under 155 characters", "avoid": [""]}',
    0.40, 8000, 1,
    N'Seed prompt (S1b). Content Pipeline brief from topic, keyword, evidence and approved claims.'
);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'content.draft')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'content.draft', 1, N'anthropic', NULL,
    N'You write long-form web content for {{brand_name}}, a practitioner-channel nutraceutical brand.
Voice: {{brand_voice}}
Audience: {{audience}}

COMPLIANCE RULES (non-negotiable):
{{rules}}

APPROVED CLAIMS — the only product or ingredient benefit statements you may make (id | product | wording; ‡ = needs the disclaimer). Quote or closely paraphrase; never strengthen them or apply them to another product:
{{approved_claims}}

DSHEA disclaimer text (end the piece with it, in italics, when you use any ‡ claim): {{disclaimer}}

Writing rules: follow the brief''s outline; attribute every finding to its source by name and year in the sentence; use only numbers that appear in the evidence; say where evidence is limited or observational; never invent statistics, studies, quotes or URLs; no links unless a URL is given. Markdown only: ## and ### headings, paragraphs, - bullet lists, **bold**, *italic*. No H1 (the title is separate), no tables, no images.',
    N'Write the {{content_type}} (about {{target_words}} words) from this brief.

BRIEF:
{{brief}}

EVIDENCE ITEMS:
{{evidence}}

Reply in exactly this format and nothing else:
TITLE: the page title
META_TITLE: under 60 characters
META_DESCRIPTION: under 155 characters
CLAIM_IDS: comma-separated ids of approved claims used, or none
---
the article body in markdown',
    0.50, 10000, 1,
    N'Seed prompt (S1b). Drafts the article from the approved brief; the draft is then claims-checked.'
);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'content.revise')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'content.revise', 1, N'anthropic', NULL,
    N'You revise long-form web content for {{brand_name}}, a practitioner-channel nutraceutical brand.
Voice: {{brand_voice}}
Audience: {{audience}}

COMPLIANCE RULES (non-negotiable):
{{rules}}

APPROVED CLAIMS (id | product | wording; ‡ = needs the disclaimer):
{{approved_claims}}

DSHEA disclaimer text (keep it at the end, in italics, whenever a ‡ claim is used): {{disclaimer}}

Change only what the instruction and the findings require; keep everything else, including structure and attributions. Never add statistics, studies, quotes or URLs that are not already in the piece. Markdown only: ## and ### headings, paragraphs, - bullet lists, **bold**, *italic*.',
    N'INSTRUCTION: {{instruction}}

CLAIMS-CHECK FINDINGS TO FIX:
{{findings}}

CURRENT VERSION
TITLE: {{title}}
META_TITLE: {{meta_title}}
META_DESCRIPTION: {{meta_description}}
---
{{body}}

Reply in exactly this format and nothing else:
TITLE: the page title
META_TITLE: under 60 characters
META_DESCRIPTION: under 155 characters
CLAIM_IDS: comma-separated ids of approved claims used, or none
---
the full revised article body in markdown',
    0.40, 10000, 1,
    N'Seed prompt (S1b). Revises a draft from an instruction and its claims-check findings; the new version is re-checked.'
);
GO
