/*
  NutraAxis Operations — Marketing Page Inventory + Google Search Console / GA4 ingest (SEO Ops S2)

  Creates:
    dbo.MktPage         — one row per site URL (sitemaps, published content, manual adds) with its latest crawl snapshot
    dbo.MktPageCrawl    — every crawl of a page: status, metadata, counts, and which fields changed since the prior crawl
    dbo.MktPageKeyword  — keyword → page map (manual, from the Content Pipeline, or accepted from Search Console queries)
    dbo.MktGscDaily     — Search Console clicks / impressions / position by day at site, page and page × query grain
    dbo.MktGa4Daily     — GA4 sessions / users / engagement / key events by day at channel, landing page and UTM grain;
                          UTM rows carry the Campaign Studio AssetID / CampaignID they resolve to (utm_content = asset id)

  Seeds pages.*, analytics.*, gsc.*, ga4.* and seo.* settings.

  Reverse (in order):
    DROP TABLE dbo.MktGa4Daily; DROP TABLE dbo.MktGscDaily; DROP TABLE dbo.MktPageKeyword;
    DROP TABLE dbo.MktPageCrawl; DROP TABLE dbo.MktPage;
    DELETE FROM dbo.MktSetting WHERE SettingKey LIKE N'pages.%' OR SettingKey LIKE N'analytics.%'
      OR SettingKey LIKE N'gsc.%' OR SettingKey LIKE N'ga4.%' OR SettingKey LIKE N'seo.%';
*/

IF OBJECT_ID(N'dbo.MktPage', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktPage (
        PageID              INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktPage PRIMARY KEY,
        Url                 NVARCHAR(1000)  NOT NULL,
        PageKey             NVARCHAR(1000)  NOT NULL,
        PageKeyHash         BINARY(32)      NOT NULL,
        Path                NVARCHAR(1000)  NOT NULL,
        PageType            NVARCHAR(30)    NOT NULL CONSTRAINT DF_MktPage_Type DEFAULT (N'page'),
        Source              NVARCHAR(30)    NOT NULL CONSTRAINT DF_MktPage_Source DEFAULT (N'sitemap'),
        Status              NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktPage_Status DEFAULT (N'active'),
        InSitemap           BIT             NOT NULL CONSTRAINT DF_MktPage_InSitemap DEFAULT (0),
        ContentID           INT             NULL CONSTRAINT FK_MktPage_Content REFERENCES dbo.MktContent (ContentID) ON DELETE SET NULL,
        LastSeenInSitemapAt DATETIME2(0)    NULL,
        LastCrawledAt       DATETIME2(0)    NULL,
        LastStatusCode      INT             NULL,
        FinalUrl            NVARCHAR(1000)  NULL,
        Title               NVARCHAR(500)   NULL,
        MetaDescription     NVARCHAR(1000)  NULL,
        H1                  NVARCHAR(500)   NULL,
        H1Count             INT             NULL,
        Canonical           NVARCHAR(1000)  NULL,
        Robots              NVARCHAR(200)   NULL,
        WordCount           INT             NULL,
        InternalLinks       INT             NULL,
        ExternalLinks       INT             NULL,
        HasStructuredData   BIT             NULL,
        ContentHash         BINARY(32)      NULL,
        Issues              NVARCHAR(1000)  NULL,
        LastChangedAt       DATETIME2(0)    NULL,
        ConsecutiveErrors   INT             NOT NULL CONSTRAINT DF_MktPage_Errors DEFAULT (0),
        Notes               NVARCHAR(1000)  NULL,
        CreatedBy           INT             NULL,
        CreatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktPage_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktPage_UpdatedAt DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_MktPage_Key UNIQUE (PageKeyHash),
        CONSTRAINT CK_MktPage_Status CHECK (Status IN (N'active', N'excluded', N'gone')),
        CONSTRAINT CK_MktPage_Source CHECK (Source IN (N'sitemap', N'content', N'manual', N'search'))
    );
    CREATE INDEX IX_MktPage_Status ON dbo.MktPage (Status, PageType);
    CREATE INDEX IX_MktPage_Content ON dbo.MktPage (ContentID) WHERE ContentID IS NOT NULL;
END
GO

IF OBJECT_ID(N'dbo.MktPageCrawl', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktPageCrawl (
        CrawlID             BIGINT          NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktPageCrawl PRIMARY KEY,
        PageID              INT             NOT NULL CONSTRAINT FK_MktPageCrawl_Page REFERENCES dbo.MktPage (PageID) ON DELETE CASCADE,
        CrawledAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktPageCrawl_At DEFAULT (SYSUTCDATETIME()),
        StatusCode          INT             NULL,
        FinalUrl            NVARCHAR(1000)  NULL,
        ResponseMs          INT             NULL,
        Title               NVARCHAR(500)   NULL,
        MetaDescription     NVARCHAR(1000)  NULL,
        H1                  NVARCHAR(500)   NULL,
        H1Count             INT             NULL,
        Canonical           NVARCHAR(1000)  NULL,
        Robots              NVARCHAR(200)   NULL,
        WordCount           INT             NULL,
        InternalLinks       INT             NULL,
        ExternalLinks       INT             NULL,
        HasStructuredData   BIT             NULL,
        ContentHash         BINARY(32)      NULL,
        Issues              NVARCHAR(1000)  NULL,
        Changes             NVARCHAR(500)   NULL,
        ErrorMessage        NVARCHAR(1000)  NULL,
        ProcessLogID        INT             NULL
    );
    CREATE INDEX IX_MktPageCrawl_Page ON dbo.MktPageCrawl (PageID, CrawledAt DESC);
END
GO

IF OBJECT_ID(N'dbo.MktPageKeyword', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktPageKeyword (
        PageKeywordID       INT             NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktPageKeyword PRIMARY KEY,
        PageID              INT             NOT NULL CONSTRAINT FK_MktPageKeyword_Page REFERENCES dbo.MktPage (PageID) ON DELETE CASCADE,
        KeywordID           INT             NULL CONSTRAINT FK_MktPageKeyword_Keyword REFERENCES dbo.MktKeyword (KeywordID) ON DELETE SET NULL,
        Keyword             NVARCHAR(200)   NOT NULL,
        Role                NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktPageKeyword_Role DEFAULT (N'primary'),
        Source              NVARCHAR(20)    NOT NULL CONSTRAINT DF_MktPageKeyword_Source DEFAULT (N'manual'),
        CreatedBy           INT             NULL,
        CreatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_MktPageKeyword_At DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_MktPageKeyword UNIQUE (PageID, Keyword),
        CONSTRAINT CK_MktPageKeyword_Role CHECK (Role IN (N'primary', N'secondary')),
        CONSTRAINT CK_MktPageKeyword_Source CHECK (Source IN (N'manual', N'content', N'search'))
    );
    CREATE INDEX IX_MktPageKeyword_Keyword ON dbo.MktPageKeyword (Keyword);
END
GO

IF OBJECT_ID(N'dbo.MktGscDaily', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktGscDaily (
        GscDailyID          BIGINT          NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktGscDaily PRIMARY KEY,
        MetricDate          DATE            NOT NULL,
        Grain               NVARCHAR(10)    NOT NULL,
        Page                NVARCHAR(1000)  NOT NULL CONSTRAINT DF_MktGscDaily_Page DEFAULT (N''),
        Query               NVARCHAR(400)   NOT NULL CONSTRAINT DF_MktGscDaily_Query DEFAULT (N''),
        DimHash             BINARY(32)      NOT NULL,
        PageID              INT             NULL,
        Clicks              INT             NOT NULL,
        Impressions         INT             NOT NULL,
        Position            DECIMAL(9, 2)   NULL,
        IngestedAt          DATETIME2(0)    NOT NULL CONSTRAINT DF_MktGscDaily_At DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_MktGscDaily UNIQUE (Grain, MetricDate, DimHash),
        CONSTRAINT CK_MktGscDaily_Grain CHECK (Grain IN (N'site', N'page', N'query'))
    );
    CREATE INDEX IX_MktGscDaily_Page ON dbo.MktGscDaily (PageID, Grain, MetricDate) WHERE PageID IS NOT NULL;
END
GO

IF OBJECT_ID(N'dbo.MktGa4Daily', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MktGa4Daily (
        Ga4DailyID          BIGINT          NOT NULL IDENTITY(1, 1) CONSTRAINT PK_MktGa4Daily PRIMARY KEY,
        MetricDate          DATE            NOT NULL,
        Grain               NVARCHAR(10)    NOT NULL,
        Dim1                NVARCHAR(500)   NOT NULL CONSTRAINT DF_MktGa4Daily_Dim1 DEFAULT (N''),
        Dim2                NVARCHAR(300)   NOT NULL CONSTRAINT DF_MktGa4Daily_Dim2 DEFAULT (N''),
        Dim3                NVARCHAR(300)   NOT NULL CONSTRAINT DF_MktGa4Daily_Dim3 DEFAULT (N''),
        Dim4                NVARCHAR(300)   NOT NULL CONSTRAINT DF_MktGa4Daily_Dim4 DEFAULT (N''),
        DimHash             BINARY(32)      NOT NULL,
        PageID              INT             NULL,
        AssetID             INT             NULL,
        CampaignID          INT             NULL,
        Sessions            INT             NOT NULL,
        Users               INT             NOT NULL,
        EngagedSessions     INT             NOT NULL,
        EngagementSeconds   BIGINT          NOT NULL,
        KeyEvents           DECIMAL(12, 2)  NOT NULL,
        Transactions        INT             NOT NULL,
        Revenue             DECIMAL(14, 2)  NOT NULL,
        IngestedAt          DATETIME2(0)    NOT NULL CONSTRAINT DF_MktGa4Daily_At DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_MktGa4Daily UNIQUE (Grain, MetricDate, DimHash),
        CONSTRAINT CK_MktGa4Daily_Grain CHECK (Grain IN (N'channel', N'landing', N'utm'))
    );
    CREATE INDEX IX_MktGa4Daily_Asset ON dbo.MktGa4Daily (AssetID, MetricDate) WHERE AssetID IS NOT NULL;
    CREATE INDEX IX_MktGa4Daily_Page ON dbo.MktGa4Daily (PageID, MetricDate) WHERE PageID IS NOT NULL;
END
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'pages.site_url', N'https://www.nutraaxislabs.com', N'Public site root. The crawler reads robots.txt here for sitemaps and only crawls this host.'),
    (N'pages.exclude_patterns', N'/customer' + NCHAR(10) + N'/checkout' + NCHAR(10) + N'/cart' + NCHAR(10) + N'/mini-cart' + NCHAR(10) + N'/empty-cart' + NCHAR(10) + N'/b2b/' + NCHAR(10) + N'/sales/' + NCHAR(10) + N'/wishlist' + NCHAR(10) + N'/search' + NCHAR(10) + N'/quick-order' + NCHAR(10) + N'/store-switcher' + NCHAR(10) + N'/order-status' + NCHAR(10) + N'/order-details' + NCHAR(10) + N'/create-return' + NCHAR(10) + N'/return-details' + NCHAR(10) + N'/widgets/' + NCHAR(10) + N'/nav' + NCHAR(10) + N'/footer' + NCHAR(10) + N'/test' + NCHAR(10) + N'/products/default', N'Sitemap URLs to skip (account, cart, checkout, fragments), one path prefix per line; start a line with ^ for a regular expression.'),
    (N'pages.type_rules', N'home|^/$' + NCHAR(10) + N'product|^/products/' + NCHAR(10) + N'category|^/(metabolic-and-weight-health|hormone-and-reproductive-health|mood-stress-sleep|healthy-aging-longevity|digestive-gut-health|pain-inflammation-physical-comfort|apparel|categories)$' + NCHAR(10) + N'policy|(policy|terms-and-conditions)$' + NCHAR(10) + N'landing|landing$', N'Page type by path, one per line: type|regular expression (first match wins; published Content Pipeline pages are type content).'),
    (N'pages.crawl_delay_ms', N'1000', N'Delay between page fetches during a crawl.'),
    (N'analytics.allowed_host', N'nutraaxislabs.com', N'Search Console and GA4 ingest refuse any property that is not on this domain.'),
    (N'gsc.backfill_days', N'90', N'Days of Search Console history loaded on the first run.'),
    (N'gsc.refresh_days', N'5', N'Each nightly run reloads this many recent days (Search Console finalizes data 2–3 days late).'),
    (N'ga4.backfill_days', N'90', N'Days of GA4 history loaded on the first run.'),
    (N'ga4.refresh_days', N'4', N'Each nightly run reloads this many recent days (GA4 finalizes data within 48 hours).'),
    (N'seo.title_max', N'60', N'Page titles longer than this are flagged.'),
    (N'seo.meta_min', N'70', N'Meta descriptions shorter than this are flagged.'),
    (N'seo.meta_max', N'160', N'Meta descriptions longer than this are flagged.'),
    (N'seo.thin_words', N'250', N'Content and category pages with fewer words than this are flagged as thin.')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO
