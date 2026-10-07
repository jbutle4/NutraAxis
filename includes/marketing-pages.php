<?php
require_once __DIR__ . '/marketing.php';

/**
 * Page Inventory: site URLs from sitemaps, published content, search and manual adds, with their latest crawl,
 * issues, keyword map, and Search Console / GA4 numbers. Crawling and ingest run in the Function App.
 */
const MKT_PAGE_TYPES = [
    'home'     => 'Home',
    'category' => 'Category',
    'product'  => 'Product',
    'content'  => 'Content',
    'landing'  => 'Landing',
    'policy'   => 'Policy',
    'page'     => 'Page',
];

const MKT_PAGE_STATUSES = [
    'active'   => 'Active',
    'excluded' => 'Excluded',
    'gone'     => 'Gone (404)',
];

const MKT_PAGE_SOURCES = [
    'sitemap' => 'Sitemap',
    'content' => 'Content Pipeline',
    'search'  => 'Found in search',
    'manual'  => 'Added by hand',
];

/** Issue code => [label, severity, what to do]. http_* and unreachable are added by mkt_page_issue_info(). */
const MKT_PAGE_ISSUES = [
    'noindex'             => ['Blocked from Google (noindex)', 'error', 'Remove the noindex robots tag unless the page should stay out of search.'],
    'missing_title'       => ['Missing title', 'error', 'Add a unique page title (50–60 characters) that names the page topic.'],
    'long_title'          => ['Title too long', 'warn', 'Google truncates titles past about 60 characters; lead with the main keyword.'],
    'duplicate_title'     => ['Duplicate title', 'warn', 'Another live page has the same title; make each title unique.'],
    'missing_meta'        => ['Missing meta description', 'warn', 'Add a 120–155 character description; Google shows it under the title.'],
    'short_meta'          => ['Meta description too short', 'warn', 'Expand the description to 120–155 characters with the benefit and a reason to click.'],
    'long_meta'           => ['Meta description too long', 'info', 'Descriptions past about 160 characters are cut off in results.'],
    'missing_h1'          => ['No H1 heading', 'warn', 'Give the page one H1 heading that states the topic (usually the page title).'],
    'multiple_h1'         => ['More than one H1', 'info', 'Use a single H1 and H2s for sections.'],
    'canonical_elsewhere' => ['Canonical points elsewhere', 'warn', 'The canonical tag names a different URL, so Google may index that one instead.'],
    'thin'                => ['Thin content', 'warn', 'Few words on the page; add explanatory copy so it can rank.'],
    'redirects'           => ['Redirects', 'info', 'The URL redirects; link to and list the final URL instead.'],
    'short_title'         => ['Title too short', 'info', 'Very short titles waste the space Google gives; aim for 50–60 characters with the main keyword.'],
    'duplicate_meta'      => ['Duplicate meta description', 'info', 'Another live page has the same description; write one per page.'],
    'missing_schema'      => ['No structured data', 'info', 'Add JSON-LD (Product, Organization, Article or BreadcrumbList as fits) so Google can show rich results.'],
    'missing_alt'         => ['Images without alt text', 'info', 'Describe each meaningful image in its alt text; leave decorative images alt="" on purpose.'],
    'legacy_brand'        => ['Legacy brand name', 'error', 'The page still shows an old or misspelled brand name; change it to the current brand.'],
    'or_low_content'      => ['Low content (OpenRush)', 'warn', 'OpenRush judged the page low on content; add explanatory copy.'],
];

function mkt_page_issue_info(string $code): array
{
    if (isset(MKT_PAGE_ISSUES[$code])) {
        [$label, $severity, $advice] = MKT_PAGE_ISSUES[$code];
        return ['code' => $code, 'label' => $label, 'severity' => $severity, 'advice' => $advice];
    }
    if (preg_match('/^http_(\d{3})$/', $code, $m)) {
        return ['code' => $code, 'label' => 'HTTP ' . $m[1], 'severity' => 'error', 'advice' => 'The page did not load (status ' . $m[1] . '); fix or remove it from the sitemap.'];
    }
    if ($code === 'unreachable') {
        return ['code' => $code, 'label' => 'Unreachable', 'severity' => 'error', 'advice' => 'The crawler could not reach the page (timeout or connection error).'];
    }
    if (str_starts_with($code, 'or_')) {
        return ['code' => $code, 'label' => ucfirst(str_replace('_', ' ', substr($code, 3))) . ' (OpenRush)', 'severity' => 'info', 'advice' => 'Reported by the OpenRush site audit.'];
    }

    return ['code' => $code, 'label' => $code, 'severity' => 'info', 'advice' => ''];
}

function mkt_page_issue_list(?string $issues): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $issues))));
}

function mkt_page_issue_badges(?string $issues): string
{
    $html = '';
    foreach (mkt_page_issue_list($issues) as $code) {
        $info = mkt_page_issue_info($code);
        $class = match ($info['severity']) {
            'error' => 'status-cancelled',
            'warn'  => 'status-submitted',
            default => 'status-draft',
        };
        $html .= '<span class="status-badge ' . $class . '" title="' . htmlspecialchars($info['advice']) . '">' . htmlspecialchars($info['label']) . '</span> ';
    }

    return $html;
}

/** Latest day with data in a daily table (Search Console lags 2–3 days, GA4 about 1). */
function mkt_analytics_latest_date(string $table): ?string
{
    if (!in_array($table, ['MktGscDaily', 'MktGa4Daily'], true)) {
        return null;
    }
    $value = db()->query("SELECT CONVERT(varchar(10), MAX(MetricDate), 23) FROM dbo.{$table}")->fetchColumn();

    return $value ? (string) $value : null;
}

/** [start, end] for the last $days days of data in a table, ending on its latest day. */
function mkt_analytics_window(string $table, int $days = 28, int $offsetWindows = 0): ?array
{
    $latest = mkt_analytics_latest_date($table);
    if ($latest === null) {
        return null;
    }
    $end = (new DateTimeImmutable($latest))->modify('-' . ($days * $offsetWindows) . ' days');

    return [$end->modify('-' . ($days - 1) . ' days')->format('Y-m-d'), $end->format('Y-m-d')];
}

function mkt_pages_list(array $filters = []): array
{
    $gsc = mkt_analytics_window('MktGscDaily') ?? ['1900-01-01', '1900-01-01'];
    $ga4 = mkt_analytics_window('MktGa4Daily') ?? ['1900-01-01', '1900-01-01'];
    $params = ['gs' => $gsc[0], 'ge' => $gsc[1], 'as' => $ga4[0], 'ae' => $ga4[1]];
    $where = [];

    $status = (string) ($filters['status'] ?? 'active');
    if ($status !== 'all' && isset(MKT_PAGE_STATUSES[$status])) {
        $where[] = 'p.Status = :status';
        $params['status'] = $status;
    }
    if (isset(MKT_PAGE_TYPES[$filters['type'] ?? ''])) {
        $where[] = 'p.PageType = :type';
        $params['type'] = $filters['type'];
    }
    if (($filters['issue'] ?? '') === 'any') {
        $where[] = "p.Issues IS NOT NULL AND p.Issues <> N''";
    } elseif (($filters['issue'] ?? '') !== '') {
        $where[] = "(N',' + p.Issues + N',') LIKE :issue";
        $params['issue'] = '%,' . $filters['issue'] . ',%';
    }
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(p.Path LIKE :q1 OR p.Title LIKE :q2)';
        $params['q1'] = $params['q2'] = '%' . $filters['q'] . '%';
    }

    $order = match ($filters['sort'] ?? '') {
        'clicks'   => 'Clicks DESC, Impressions DESC, p.Path',
        'sessions' => 'Sessions DESC, p.Path',
        'issues'   => 'LEN(COALESCE(p.Issues, N\'\')) DESC, p.Path',
        'crawled'  => 'p.LastCrawledAt DESC',
        default    => 'CASE p.PageType WHEN N\'home\' THEN 0 WHEN N\'category\' THEN 1 WHEN N\'product\' THEN 2 WHEN N\'content\' THEN 3 ELSE 4 END, p.Path',
    };

    $sql = <<<SQL
        SELECT p.PageID, p.Url, p.Path, p.PageType, p.Source, p.Status, p.InSitemap, p.ContentID, p.LastStatusCode,
               p.LastCrawledAt, p.LastChangedAt, p.Title, p.WordCount, p.Issues,
               COALESCE(g.Clicks, 0) AS Clicks, COALESCE(g.Impressions, 0) AS Impressions, g.Position,
               COALESCE(a.Sessions, 0) AS Sessions,
               (SELECT COUNT(*) FROM dbo.MktPageKeyword k WHERE k.PageID = p.PageID) AS Keywords
        FROM dbo.MktPage p
        OUTER APPLY (
            SELECT SUM(Clicks) AS Clicks, SUM(Impressions) AS Impressions,
                   CAST(SUM(Position * Impressions) / NULLIF(SUM(Impressions), 0) AS DECIMAL(9, 1)) AS Position
            FROM dbo.MktGscDaily WHERE PageID = p.PageID AND Grain = N'page' AND MetricDate BETWEEN :gs AND :ge
        ) g
        OUTER APPLY (
            SELECT SUM(Sessions) AS Sessions
            FROM dbo.MktGa4Daily WHERE PageID = p.PageID AND Grain = N'landing' AND MetricDate BETWEEN :as AND :ae
        ) a
    SQL;
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $stmt = db()->prepare($sql . ' ORDER BY ' . $order);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mkt_page_counts(): array
{
    $counts = ['active' => 0, 'excluded' => 0, 'gone' => 0, 'with_issues' => 0, 'errors' => 0];
    $rows = db()->query(<<<SQL
        SELECT Status, COUNT(*) AS N,
               SUM(CASE WHEN Issues IS NOT NULL AND Issues <> N'' THEN 1 ELSE 0 END) AS WithIssues,
               SUM(CASE WHEN LastStatusCode IS NOT NULL AND LastStatusCode <> 200 THEN 1 ELSE 0 END) AS Errors
        FROM dbo.MktPage GROUP BY Status
    SQL)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $counts[$row['Status']] = (int) $row['N'];
        if ($row['Status'] === 'active') {
            $counts['with_issues'] = (int) $row['WithIssues'];
            $counts['errors'] = (int) $row['Errors'];
        }
    }

    return $counts;
}

/** Active pages per issue code, most severe first. */
function mkt_page_issue_counts(): array
{
    $counts = [];
    foreach (db()->query("SELECT Issues FROM dbo.MktPage WHERE Status = N'active' AND Issues IS NOT NULL AND Issues <> N''")->fetchAll(PDO::FETCH_COLUMN) as $issues) {
        foreach (mkt_page_issue_list((string) $issues) as $code) {
            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }
    }
    $rank = ['error' => 0, 'warn' => 1, 'info' => 2];
    uksort($counts, static function (string $a, string $b) use ($counts, $rank): int {
        return [$rank[mkt_page_issue_info($a)['severity']], -$counts[$a]] <=> [$rank[mkt_page_issue_info($b)['severity']], -$counts[$b]];
    });

    return $counts;
}

function mkt_page_get(int $id): ?array
{
    $stmt = db()->prepare(<<<SQL
        SELECT p.*, CONVERT(varchar(19), p.LastCrawledAt, 120) AS LastCrawledAtText,
               c.Title AS ContentTitle, c.Stage AS ContentStage
        FROM dbo.MktPage p
        LEFT JOIN dbo.MktContent c ON c.ContentID = p.ContentID
        WHERE p.PageID = :id
    SQL);
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function mkt_page_crawls(int $pageId, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $stmt = db()->prepare("SELECT TOP ({$limit}) CrawlID, CrawledAt, StatusCode, FinalUrl, ResponseMs, Title, MetaDescription, H1, WordCount, Issues, Changes, ErrorMessage FROM dbo.MktPageCrawl WHERE PageID = :id ORDER BY CrawlID DESC");
    $stmt->execute(['id' => $pageId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Recent crawls where something changed (title, meta, H1, canonical, robots, status, or body text). */
function mkt_page_changes(int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $rows = db()->query(<<<SQL
        SELECT TOP ({$limit}) c.CrawlID, c.PageID, c.CrawledAt, c.Changes, c.StatusCode, c.Title, c.MetaDescription, c.H1,
               p.Path, p.Url, p.PageType
        FROM dbo.MktPageCrawl c
        INNER JOIN dbo.MktPage p ON p.PageID = c.PageID
        WHERE c.Changes IS NOT NULL
        ORDER BY c.CrawlID DESC
    SQL)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $previous = db()->prepare('SELECT TOP 1 StatusCode, Title, MetaDescription, H1 FROM dbo.MktPageCrawl WHERE PageID = :p AND CrawlID < :c ORDER BY CrawlID DESC');
    foreach ($rows as &$row) {
        $previous->execute(['p' => $row['PageID'], 'c' => $row['CrawlID']]);
        $row['Previous'] = $previous->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    return $rows;
}

function mkt_page_keywords(int $pageId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT k.PageKeywordID, k.Keyword, k.KeywordID, k.Role, k.Source, k.CreatedAt, u.UserName AS CreatedByName,
               (SELECT COUNT(*) FROM dbo.MktPageKeyword o
                INNER JOIN dbo.MktPage op ON op.PageID = o.PageID AND op.Status = N'active'
                WHERE o.Keyword = k.Keyword AND o.PageID <> k.PageID AND o.Role = N'primary') AS OtherPrimaryPages
        FROM dbo.MktPageKeyword k
        LEFT JOIN dbo.[User] u ON u.UserID = k.CreatedBy
        WHERE k.PageID = :id
        ORDER BY CASE k.Role WHEN N'primary' THEN 0 ELSE 1 END, k.Keyword
    SQL);
    $stmt->execute(['id' => $pageId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mkt_page_keyword_add(int $pageId, string $keyword, string $role, string $source = 'manual'): array
{
    $keyword = mb_strtolower(trim(preg_replace('/\s+/', ' ', $keyword)));
    if ($keyword === '' || mb_strlen($keyword) > 200) {
        return ['ok' => false, 'error' => 'Enter a keyword (up to 200 characters).'];
    }
    if (!in_array($role, ['primary', 'secondary'], true) || !in_array($source, ['manual', 'search'], true)) {
        return ['ok' => false, 'error' => 'Invalid keyword role.'];
    }
    if (mkt_page_get($pageId) === null) {
        return ['ok' => false, 'error' => 'Page not found.'];
    }
    $exists = db()->prepare('SELECT COUNT(*) FROM dbo.MktPageKeyword WHERE PageID = :p AND Keyword = :k');
    $exists->execute(['p' => $pageId, 'k' => $keyword]);
    if ((int) $exists->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'That keyword is already mapped to this page.'];
    }
    $keywordId = db()->prepare('SELECT TOP 1 KeywordID FROM dbo.MktKeyword WHERE LOWER(Keyword) = :k');
    $keywordId->execute(['k' => $keyword]);
    db()->prepare('INSERT INTO dbo.MktPageKeyword (PageID, KeywordID, Keyword, Role, Source, CreatedBy) VALUES (:p, :kid, :k, :r, :s, :u)')
        ->execute(['p' => $pageId, 'kid' => $keywordId->fetchColumn() ?: null, 'k' => $keyword, 'r' => $role, 's' => $source, 'u' => marketing_user_id()]);

    return ['ok' => true];
}

function mkt_page_keyword_remove(int $pageId, int $pageKeywordId): array
{
    $stmt = db()->prepare('DELETE FROM dbo.MktPageKeyword WHERE PageKeywordID = :id AND PageID = :p');
    $stmt->execute(['id' => $pageKeywordId, 'p' => $pageId]);

    return $stmt->rowCount() > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'Keyword not found on this page.'];
}

/** Every mapped keyword with its pages; keywords that are primary on more than one live page compete with themselves. */
function mkt_page_keyword_map(): array
{
    return db()->query(<<<SQL
        SELECT k.Keyword, k.Role, k.Source, p.PageID, p.Path, p.Title,
               SUM(CASE WHEN k.Role = N'primary' THEN 1 ELSE 0 END) OVER (PARTITION BY k.Keyword) AS PrimaryPages
        FROM dbo.MktPageKeyword k
        INNER JOIN dbo.MktPage p ON p.PageID = k.PageID AND p.Status = N'active'
        ORDER BY k.Keyword, CASE k.Role WHEN N'primary' THEN 0 ELSE 1 END, p.Path
    SQL)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Search Console queries for one page over the last window, with whether each is already mapped. */
function mkt_page_search_queries(int $pageId, int $days = 90, int $limit = 50): array
{
    $window = mkt_analytics_window('MktGscDaily', $days);
    if ($window === null) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    $stmt = db()->prepare(<<<SQL
        SELECT TOP ({$limit}) q.Query, SUM(q.Clicks) AS Clicks, SUM(q.Impressions) AS Impressions,
               CAST(SUM(q.Position * q.Impressions) / NULLIF(SUM(q.Impressions), 0) AS DECIMAL(9, 1)) AS Position,
               MAX(CASE WHEN k.PageKeywordID IS NULL THEN 0 ELSE 1 END) AS Mapped
        FROM dbo.MktGscDaily q
        LEFT JOIN dbo.MktPageKeyword k ON k.PageID = q.PageID AND k.Keyword = q.Query
        WHERE q.PageID = :id AND q.Grain = N'query' AND q.MetricDate BETWEEN :s AND :e
        GROUP BY q.Query
        ORDER BY SUM(q.Clicks) DESC, SUM(q.Impressions) DESC
    SQL);
    $stmt->execute(['id' => $pageId, 's' => $window[0], 'e' => $window[1]]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Search clicks / impressions and GA4 landing sessions for the last $days vs the prior $days. */
function mkt_page_metrics(int $pageId, int $days = 28): array
{
    $out = [];
    foreach (['current' => 0, 'prior' => 1] as $key => $offset) {
        $gsc = mkt_analytics_window('MktGscDaily', $days, $offset);
        $ga4 = mkt_analytics_window('MktGa4Daily', $days, $offset);
        $row = ['Clicks' => 0, 'Impressions' => 0, 'Position' => null, 'Sessions' => 0, 'EngagedSessions' => 0, 'KeyEvents' => 0];
        if ($gsc !== null) {
            $stmt = db()->prepare("SELECT COALESCE(SUM(Clicks), 0) AS Clicks, COALESCE(SUM(Impressions), 0) AS Impressions, CAST(SUM(Position * Impressions) / NULLIF(SUM(Impressions), 0) AS DECIMAL(9, 1)) AS Position FROM dbo.MktGscDaily WHERE PageID = :id AND Grain = N'page' AND MetricDate BETWEEN :s AND :e");
            $stmt->execute(['id' => $pageId, 's' => $gsc[0], 'e' => $gsc[1]]);
            $row = array_merge($row, $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        }
        if ($ga4 !== null) {
            $stmt = db()->prepare("SELECT COALESCE(SUM(Sessions), 0) AS Sessions, COALESCE(SUM(EngagedSessions), 0) AS EngagedSessions, COALESCE(SUM(KeyEvents), 0) AS KeyEvents FROM dbo.MktGa4Daily WHERE PageID = :id AND Grain = N'landing' AND MetricDate BETWEEN :s AND :e");
            $stmt->execute(['id' => $pageId, 's' => $ga4[0], 'e' => $ga4[1]]);
            $row = array_merge($row, $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        }
        $out[$key] = $row;
    }

    return $out;
}

function mkt_page_exclusion_lines(): array
{
    $value = (string) (marketing_settings_all()['pages.exclude_patterns']['SettingValue'] ?? '');

    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $value))));
}

/** The exclusion line that covers a path, mirroring the crawler's prefix / ^regex rules. */
function mkt_page_matching_exclusion(string $path, ?array $lines = null): ?string
{
    $lower = strtolower($path);
    foreach ($lines ?? mkt_page_exclusion_lines() as $line) {
        if (str_starts_with($line, '^')) {
            if (@preg_match('~' . str_replace('~', '\~', $line) . '~i', $path) === 1) {
                return $line;
            }
            continue;
        }
        $base = rtrim(strtolower($line), '/');
        if ($lower === $base || str_starts_with($lower, $base . '/')) {
            return $line;
        }
    }

    return null;
}

/** Exclude adds an exact-path rule to pages.exclude_patterns so the next crawl keeps it out; include removes it. */
function mkt_page_set_excluded(int $pageId, bool $exclude): array
{
    $page = mkt_page_get($pageId);
    if ($page === null) {
        return ['ok' => false, 'error' => 'Page not found.'];
    }
    $path = (string) $page['Path'];
    $exact = '^' . preg_quote($path, '/') . '$';
    $lines = mkt_page_exclusion_lines();

    if ($exclude) {
        if (mkt_page_matching_exclusion($path) === null) {
            $lines[] = $exact;
        }
        $status = 'excluded';
    } else {
        $lines = array_values(array_filter($lines, static fn(string $line): bool => $line !== $exact));
        $still = mkt_page_matching_exclusion($path, $lines);
        if ($still !== null) {
            return ['ok' => false, 'error' => 'The exclusion rule "' . $still . '" in Admin → Settings → pages.exclude_patterns covers this page; edit that rule to include it.'];
        }
        $status = 'active';
    }

    marketing_settings_save(['pages.exclude_patterns' => implode("\n", $lines)]);
    db()->prepare('UPDATE dbo.MktPage SET Status = :s, UpdatedAt = SYSUTCDATETIME() WHERE PageID = :id')->execute(['s' => $status, 'id' => $pageId]);

    return ['ok' => true];
}

/** Last run and data freshness for the crawl and Google ingest jobs. */
function mkt_analytics_status(): array
{
    require_once __DIR__ . '/marketing-jobs.php';
    $last = marketing_jobs_last_runs();
    $status = [];
    foreach (['seo-gsc-ingest' => 'MktGscDaily', 'seo-ga4-ingest' => 'MktGa4Daily', 'seo-crawl' => null] as $code => $table) {
        $status[$code] = [
            'run'    => $last[$code] ?? null,
            'latest' => $table !== null ? mkt_analytics_latest_date($table) : null,
        ];
    }

    return $status;
}
