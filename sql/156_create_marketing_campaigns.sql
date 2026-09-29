/*
  NutraAxis Operations — Marketing Campaign Studio (SEO Ops S1b: generation, claims check, compliance + editorial gates)

  Creates:
    dbo.MktCampaign     — a post, series, or email campaign generated from an accepted topic
    dbo.MktAsset        — one channel/sequence piece of a campaign, with claims score, gate states, UTM target and posting record
    dbo.MktAssetReview  — submission and review history (compliance, editorial) per asset

  Seeds settings (review.*, campaign.*) and prompts campaign.generate, campaign.claims_check, campaign.revise_asset.

  Reverse (in order):
    DROP TABLE dbo.MktAssetReview; DROP TABLE dbo.MktAsset; DROP TABLE dbo.MktCampaign;
    DELETE FROM dbo.MktPrompt WHERE PromptKey IN (N'campaign.generate', N'campaign.claims_check', N'campaign.revise_asset');
    DELETE FROM dbo.MktSetting WHERE SettingKey LIKE N'campaign.%' OR SettingKey LIKE N'review.%';
*/

IF OBJECT_ID(N'dbo.MktCampaign', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktCampaign (
        CampaignID       INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktCampaign PRIMARY KEY,
        TopicID          INT             NULL CONSTRAINT FK_MktCampaign_Topic REFERENCES dbo.MktTopic (TopicID) ON DELETE SET NULL,
        Name             NVARCHAR(200)   NOT NULL,
        Slug             NVARCHAR(80)    NOT NULL,
        Format           NVARCHAR(20)    NOT NULL,
        Channels         NVARCHAR(200)   NOT NULL,
        Audience         NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktCampaign_Audience DEFAULT (N'both'),
        PartCount        INT             NOT NULL CONSTRAINT DF_MktCampaign_PartCount DEFAULT (1),
        CadenceDays      INT             NULL,
        CtaUrl           NVARCHAR(1000)  NOT NULL,
        CtaText          NVARCHAR(200)   NULL,
        Brief            NVARCHAR(2000)  NULL,
        Status           NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktCampaign_Status DEFAULT (N'draft'),
        GeneratedAt      DATETIME2(0)    NULL,
        GenerationLogID  INT             NULL,
        PromptVersion    INT             NULL,
        CreatedBy        INT             NULL,
        CreatedAt        DATETIME2(0)    NOT NULL CONSTRAINT DF_MktCampaign_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedBy        INT             NULL,
        UpdatedAt        DATETIME2(0)    NOT NULL CONSTRAINT DF_MktCampaign_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_MktCampaign_Slug UNIQUE (Slug),
        CONSTRAINT CK_MktCampaign_Format CHECK (Format IN (N'post', N'series', N'email')),
        CONSTRAINT CK_MktCampaign_Audience CHECK (Audience IN (N'both', N'practitioner', N'consumer')),
        CONSTRAINT CK_MktCampaign_Status CHECK (Status IN (N'draft', N'active', N'archived'))
    );

    CREATE INDEX IX_MktCampaign_Topic ON dbo.MktCampaign (TopicID);
    CREATE INDEX IX_MktCampaign_Status ON dbo.MktCampaign (Status, UpdatedAt DESC);
END
GO

IF OBJECT_ID(N'dbo.MktAsset', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktAsset (
        AssetID               INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktAsset PRIMARY KEY,
        CampaignID            INT             NOT NULL CONSTRAINT FK_MktAsset_Campaign REFERENCES dbo.MktCampaign (CampaignID) ON DELETE CASCADE,
        Channel               NVARCHAR(30)    NOT NULL,
        SequenceNo            INT             NOT NULL CONSTRAINT DF_MktAsset_SequenceNo DEFAULT (1),
        Title                 NVARCHAR(300)   NULL,
        Subject               NVARCHAR(300)   NULL,
        SubjectVariantsJson   NVARCHAR(2000)  NULL,
        PreviewText           NVARCHAR(300)   NULL,
        Body                  NVARCHAR(MAX)   NOT NULL,
        Hashtags              NVARCHAR(500)   NULL,
        CtaText               NVARCHAR(200)   NULL,
        MediaNotes            NVARCHAR(1000)  NULL,
        ClaimIdsJson          NVARCHAR(500)   NULL,
        ContentVersion        INT             NOT NULL CONSTRAINT DF_MktAsset_ContentVersion DEFAULT (1),
        ClaimsScore           DECIMAL(4, 1)   NULL,
        ClaimsCheckJson       NVARCHAR(MAX)   NULL,
        ClaimsCheckedVersion  INT             NULL,
        ClaimsCheckedAt       DATETIME2(0)    NULL,
        NeedsCompliance       BIT             NULL,
        Status                NVARCHAR(30)    NOT NULL CONSTRAINT DF_MktAsset_Status DEFAULT (N'draft'),
        ComplianceStatus      NVARCHAR(30)    NULL,
        ComplianceBy          INT             NULL,
        ComplianceAt          DATETIME2(0)    NULL,
        EditorialStatus       NVARCHAR(30)    NULL,
        EditorialBy           INT             NULL,
        EditorialAt           DATETIME2(0)    NULL,
        SubmittedBy           INT             NULL,
        SubmittedAt           DATETIME2(0)    NULL,
        ScheduledAt           DATETIME2(0)    NULL,
        ExternalPostUrl       NVARCHAR(1000)  NULL,
        ExternalPostID        NVARCHAR(200)   NULL,
        PostedAt              DATETIME2(0)    NULL,
        CreatedBy             INT             NULL,
        CreatedAt             DATETIME2(0)    NOT NULL CONSTRAINT DF_MktAsset_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedBy             INT             NULL,
        UpdatedAt             DATETIME2(0)    NOT NULL CONSTRAINT DF_MktAsset_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_MktAsset_Status CHECK (Status IN (
            N'draft', N'in_review', N'changes_requested', N'approved', N'scheduled', N'posted', N'archived'
        )),
        CONSTRAINT CK_MktAsset_ComplianceStatus CHECK (ComplianceStatus IS NULL OR ComplianceStatus IN (
            N'not_required', N'pending', N'approved', N'changes_requested'
        )),
        CONSTRAINT CK_MktAsset_EditorialStatus CHECK (EditorialStatus IS NULL OR EditorialStatus IN (
            N'pending', N'approved', N'changes_requested'
        ))
    );

    CREATE INDEX IX_MktAsset_Campaign ON dbo.MktAsset (CampaignID, SequenceNo, Channel);
    CREATE INDEX IX_MktAsset_Status ON dbo.MktAsset (Status, ComplianceStatus, EditorialStatus);
    CREATE INDEX IX_MktAsset_Scheduled ON dbo.MktAsset (ScheduledAt) WHERE ScheduledAt IS NOT NULL;
END
GO

IF OBJECT_ID(N'dbo.MktAssetReview', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktAssetReview (
        ReviewID        INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktAssetReview PRIMARY KEY,
        AssetID         INT             NOT NULL CONSTRAINT FK_MktAssetReview_Asset REFERENCES dbo.MktAsset (AssetID) ON DELETE CASCADE,
        Gate            NVARCHAR(20)    NOT NULL,
        Decision        NVARCHAR(30)    NOT NULL,
        Note            NVARCHAR(2000)  NULL,
        ContentVersion  INT             NULL,
        ClaimsScore     DECIMAL(4, 1)   NULL,
        UserID          INT             NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktAssetReview_CreatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_MktAssetReview_Gate CHECK (Gate IN (N'submit', N'compliance', N'editorial', N'system'))
    );

    CREATE INDEX IX_MktAssetReview_Asset ON dbo.MktAssetReview (AssetID, ReviewID DESC);
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'review.compliance_reviewers', N'',
     N'Portal logins (emails) of compliance reviewers, one per line. They clear any asset that makes or could make a claim before editorial approval; they also need Marketing read + update access.'),
    (N'review.compliance_mode', N'claims',
     N'claims = compliance review when the check finds any health, product, ingredient, efficacy or condition statement or a flag term; all = every asset.'),
    (N'campaign.default_cta_url', N'https://nutraaxislabs.com', N'Default call-to-action URL for new campaigns (UTM parameters are added per asset).'),
    (N'campaign.channels',
     N'linkedin|LinkedIn|social|3000|Professional tone; 120-220 words; up to 3 hashtags.' + NCHAR(10)
     + N'facebook|Facebook|social|2000|Conversational; 60-150 words; up to 2 hashtags.' + NCHAR(10)
     + N'instagram|Instagram caption|social|2200|Hook in the first line; 80-150 words; up to 8 hashtags; describe the visual in media_notes.' + NCHAR(10)
     + N'x|X|social|280|Under 260 characters including [LINK]; no product benefit claims; one evidence point.' + NCHAR(10)
     + N'email|Email|email|0|Plain text, short paragraphs, 150-300 words; one clear call to action.',
     N'One channel per line: key|label|medium (social or email)|max characters (0 = no limit)|writing guidance.'),
    (N'campaign.rules',
     N'Never state or imply that a product or ingredient diagnoses, treats, cures, mitigates or prevents a disease.' + NCHAR(10)
     + N'Product and ingredient benefits may only use the approved claims provided, without strengthening them.' + NCHAR(10)
     + N'Report study findings as findings attributed to their source (e.g. "a 2026 trial reported ..."), never as promised individual results.' + NCHAR(10)
     + N'Do not recommend supplements for children; pediatric topics are educational and defer to the treating practitioner.' + NCHAR(10)
     + N'No guarantees, "clinically proven", "miracle", or "no side effects".' + NCHAR(10)
     + N'Do not compare NutraAxis products with competitors or claim superiority; competitor names appear only as neutral news.' + NCHAR(10)
     + N'Never advise starting, stopping, or replacing prescription medication; GLP-1 content supports, never replaces, prescribed care.' + NCHAR(10)
     + N'Consumer content encourages readers to talk with their healthcare practitioner.' + NCHAR(10)
     + N'When an asset makes a product benefit claim marked with a dagger (‡), end it with the DSHEA disclaimer (not required on X, where product claims are not allowed).',
     N'Compliance rules, one per line — injected into generation, revision and the claims check.')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'campaign.generate')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'campaign.generate', 1, N'anthropic', NULL,
    N'You write marketing content for {{brand_name}}, a practitioner-channel nutraceutical brand.
Voice: {{brand_voice}}
Audience: {{audience}}

COMPLIANCE RULES (non-negotiable):
{{rules}}

APPROVED CLAIMS — the only product or ingredient benefit statements you may make (id | product | wording; ‡ = needs the disclaimer). Quote or closely paraphrase; never strengthen them, merge them into stronger claims, or apply them to another product. List the id of every claim you use in claim_ids_used:
{{approved_claims}}

DSHEA disclaimer text: {{disclaimer}}

Anything not supported by an approved claim or by the evidence items you are given must be left out. Do not invent statistics, studies, quotes or URLs.',
    N'FORMAT: {{format_instructions}}

CHANNELS (key | label | guidance):
{{channels}}

CALL TO ACTION: {{cta_text}}. Write the literal token [LINK] exactly once in each asset where the link belongs.

TOPIC: {{topic_title}}
Summary: {{topic_summary}}
Why it matters: {{topic_why}}
ANGLE (our point of view — every asset must serve it): {{angle}}
AVOID: {{avoid}}
Additional brief: {{brief}}

EVIDENCE ITEMS (cite by source name and year; use only facts stated here):
{{evidence}}

Return ONLY a JSON object, no prose:
{"assets": [{"sequence": 1, "channel": "linkedin", "title": "short internal title", "subject": null, "subject_variants": [], "preview_text": null, "body": "...", "hashtags": [], "cta_text": "...", "media_notes": "visual suggestion", "claim_ids_used": []}]}',
    0.60, 12000, 1,
    N'Seed prompt (S1b). Generates every asset of a campaign in one call; each asset is then claims-checked.'
);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'campaign.claims_check')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'campaign.claims_check', 1, N'anthropic', NULL,
    N'You are a dietary supplement marketing compliance reviewer applying FDA DSHEA structure/function rules and FTC substantiation standards. You review ONE marketing asset for {{brand_name}}.

APPROVED CLAIMS (id | product | wording; ‡ = needs the disclaimer):
{{approved_claims}}

HOUSE RULES:
{{rules}}

Find every health, product, ingredient, efficacy or condition statement in the asset and classify it:
- approved: matches an approved claim''s wording or meaning without strengthening it
- evidence: a factual, attributed report of a study or news item that implies no product benefit
- unapproved: a benefit or structure/function statement not covered by an approved claim, or a strengthened version of one
- disease: states or implies that a product or ingredient diagnoses, treats, cures, mitigates or prevents a disease

Also report as issues: competitor comparisons or superiority, supplements recommended to children, guarantees, advice to change prescription medication, and a missing DSHEA disclaimer when a ‡ product claim is made (not required on X).

Score 0-10: 10 = no issues; 8-9 = minor wording concerns only; 5-7 = at least one unapproved statement or a missing disclaimer; 0-4 = any disease claim, child-directed supplement recommendation, guarantee, or medication advice.

Be concise: list at most 12 statements (the riskiest first), quote at most 25 words of each, keep each note under 20 words, and skip neutral statements that make no health, product or study claim.

Return ONLY a JSON object, no prose:
{"score": 10, "statements": [{"text": "exact phrase from the asset", "class": "approved|evidence|unapproved|disease", "claim_id": null, "note": ""}], "issues": [], "references_efficacy": false, "references_condition": false, "disclaimer_ok": true, "summary": "one sentence"}',
    N'Channel: {{channel}}
Title: {{title}}
Subject: {{subject}}
Preview: {{preview_text}}

Body:
{{body}}',
    0.00, 4000, 1,
    N'Seed prompt (S1b). Runs on every generated or edited asset; the score gates submission (claims.min_score).'
);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.MktPrompt WHERE PromptKey = N'campaign.revise_asset')
INSERT INTO dbo.MktPrompt (PromptKey, Version, Provider, Model, SystemPrompt, UserTemplate, Temperature, MaxTokens, IsActive, Notes)
VALUES (
    N'campaign.revise_asset', 1, N'anthropic', NULL,
    N'You revise marketing content for {{brand_name}}, a practitioner-channel nutraceutical brand.
Voice: {{brand_voice}}
Audience: {{audience}}

COMPLIANCE RULES (non-negotiable):
{{rules}}

APPROVED CLAIMS — the only product or ingredient benefit statements allowed (id | product | wording; ‡ = needs the disclaimer):
{{approved_claims}}

DSHEA disclaimer text: {{disclaimer}}

Do not invent statistics, studies, quotes or URLs.',
    N'Revise this {{channel}} asset ({{channel_guidance}}). Keep its angle and structure unless the instruction says otherwise, and keep the [LINK] token exactly once.

INSTRUCTION / REVIEWER NOTES:
{{instruction}}

CLAIMS CHECK FINDINGS TO FIX:
{{findings}}

CURRENT ASSET
Title: {{title}}
Subject: {{subject}}
Preview: {{preview_text}}
Body:
{{body}}

Return ONLY a JSON object, no prose:
{"title": "", "subject": null, "subject_variants": [], "preview_text": null, "body": "", "hashtags": [], "claim_ids_used": []}',
    0.40, 3000, 1,
    N'Seed prompt (S1b). One-asset revision from reviewer notes and claims-check findings.'
);
GO
