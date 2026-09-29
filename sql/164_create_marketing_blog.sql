/*
  NutraAxis Operations — Marketing blog (nutraaxislabs.com/our-blog)

  Creates:
    dbo.MktBlogPost — the published copy of an approved content piece, read by api/public/blog.php for the /our-blog page.
                      One row per piece; publishing again after an approved revision overwrites it. BodyHtml is rendered
                      from the approved version's Markdown at publish time, so later edits never reach the site until
                      they are approved and published again.

  Seeds settings blog.page_url and blog.author, and adds a "blog" line to content.types.

  Reverse (in order):
    DROP TABLE dbo.MktBlogPost;
    DELETE FROM dbo.MktSetting WHERE SettingKey LIKE N'blog.%';
    -- then remove the blog| line from content.types in Marketing Admin → Settings
*/

IF OBJECT_ID(N'dbo.MktBlogPost', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktBlogPost (
        BlogPostID        INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktBlogPost PRIMARY KEY,
        ContentID         INT             NOT NULL CONSTRAINT FK_MktBlogPost_Content REFERENCES dbo.MktContent (ContentID),
        VersionID         INT             NOT NULL CONSTRAINT FK_MktBlogPost_Version REFERENCES dbo.MktContentVersion (VersionID),
        Slug              NVARCHAR(160)   NOT NULL,
        Title             NVARCHAR(300)   NOT NULL,
        MetaTitle         NVARCHAR(200)   NULL,
        MetaDescription   NVARCHAR(400)   NULL,
        Excerpt           NVARCHAR(600)   NULL,
        BodyHtml          NVARCHAR(MAX)   NOT NULL,
        WordCount         INT             NOT NULL CONSTRAINT DF_MktBlogPost_Words DEFAULT (0),
        AuthorName        NVARCHAR(150)   NULL,
        HeroImageUrl      NVARCHAR(1000)  NULL,
        HeroImageAlt      NVARCHAR(300)   NULL,
        Status            NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktBlogPost_Status DEFAULT (N'published'),
        FirstPublishedAt  DATETIME2(0)    NOT NULL CONSTRAINT DF_MktBlogPost_FirstPublished DEFAULT (SYSUTCDATETIME()),
        PublishedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_MktBlogPost_Published DEFAULT (SYSUTCDATETIME()),
        PublishedBy       INT             NULL,
        UnpublishedAt     DATETIME2(0)    NULL,
        UnpublishedBy     INT             NULL,
        CreatedAt         DATETIME2(0)    NOT NULL CONSTRAINT DF_MktBlogPost_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt         DATETIME2(0)    NOT NULL CONSTRAINT DF_MktBlogPost_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_MktBlogPost_Content UNIQUE (ContentID),
        CONSTRAINT UQ_MktBlogPost_Slug UNIQUE (Slug),
        CONSTRAINT CK_MktBlogPost_Status CHECK (Status IN (N'published', N'unpublished')),
        CONSTRAINT CK_MktBlogPost_Slug CHECK (Slug NOT LIKE N'%[^a-z0-9-]%' AND LEN(Slug) >= 3)
    );

    CREATE INDEX IX_MktBlogPost_Live ON dbo.MktBlogPost (Status, FirstPublishedAt DESC) INCLUDE (Slug, Title, Excerpt);
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'blog.page_url', N'https://nutraaxislabs.com/our-blog',
     N'Public address of the blog page in Adobe DA. A published post''s live URL is this plus ?post=<slug>.'),
    (N'blog.author', N'NutraAxis Team',
     N'Default byline shown on blog posts; can be changed per post when publishing.')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO

UPDATE dbo.MktSetting
SET SettingValue = SettingValue + NCHAR(10)
    + N'blog|Blog post|900|Post for the NutraAxis blog: one clear takeaway from the research in plain language, short sections with ## headings, where the evidence is limited, and a practical close that points readers to their practitioner.'
WHERE SettingKey = N'content.types'
  AND (NCHAR(10) + REPLACE(SettingValue, NCHAR(13), N'')) NOT LIKE N'%' + NCHAR(10) + N'blog|%';
GO
