<?php

require_once __DIR__ . '/marketing-pages.php';
require_once __DIR__ . '/marketing-campaigns.php';

const MKT_PERF_PERIODS = [7 => 'Last 7 days', 28 => 'Last 28 days', 90 => 'Last 90 days'];

function mkt_perf_days(mixed $value): int
{
    $days = (int) $value;

    return isset(MKT_PERF_PERIODS[$days]) ? $days : 28;
}

/**
 * Current and prior windows for both sources, each ending on that source's latest day.
 *
 * @return array{gsc: ?array, gsc_prior: ?array, ga4: ?array, ga4_prior: ?array}
 */
function mkt_perf_windows(int $days): array
{
    return [
        'gsc'       => mkt_analytics_window('MktGscDaily', $days),
        'gsc_prior' => mkt_analytics_window('MktGscDaily', $days, 1),
        'ga4'       => mkt_analytics_window('MktGa4Daily', $days),
        'ga4_prior' => mkt_analytics_window('MktGa4Daily', $days, 1),
    ];
}

/** Site-level Search Console totals for a window. */
function mkt_perf_search_totals(?array $window): array
{
    $row = ['Clicks' => 0, 'Impressions' => 0, 'Position' => null];
    if ($window === null) {
        return $row;
    }
    $stmt = db()->prepare(<<<SQL
        SELECT COALESCE(SUM(Clicks), 0) AS Clicks, COALESCE(SUM(Impressions), 0) AS Impressions,
               CAST(SUM(Position * Impressions) / NULLIF(SUM(Impressions), 0) AS DECIMAL(9, 1)) AS Position
        FROM dbo.MktGscDaily
        WHERE Grain = N'site' AND MetricDate BETWEEN :s AND :e
    SQL);
    $stmt->execute(['s' => $window[0], 'e' => $window[1]]);

    return array_merge($row, $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
}

/** GA4 totals for a window, summed from the channel grain (every session has one channel). */
function mkt_perf_ga4_totals(?array $window): array
{
    $row = ['Sessions' => 0, 'EngagedSessions' => 0, 'KeyEvents' => 0, 'Transactions' => 0, 'Revenue' => 0];
    if ($window === null) {
        return $row;
    }
    $stmt = db()->prepare(<<<SQL
        SELECT COALESCE(SUM(Sessions), 0) AS Sessions, COALESCE(SUM(EngagedSessions), 0) AS EngagedSessions,
               COALESCE(SUM(KeyEvents), 0) AS KeyEvents, COALESCE(SUM(Transactions), 0) AS Transactions,
               COALESCE(SUM(Revenue), 0) AS Revenue
        FROM dbo.MktGa4Daily
        WHERE Grain = N'channel' AND MetricDate BETWEEN :s AND :e
    SQL);
    $stmt->execute(['s' => $window[0], 'e' => $window[1]]);

    return array_merge($row, $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
}

/** Sessions by default channel group, current window vs prior. */
function mkt_perf_channels(array $windows): array
{
    if ($windows['ga4'] === null) {
        return [];
    }
    [$s, $e] = $windows['ga4'];
    [$ps] = $windows['ga4_prior'];
    $stmt = db()->prepare(<<<SQL
        SELECT Dim1 AS Channel,
               SUM(CASE WHEN MetricDate >= :s THEN Sessions ELSE 0 END) AS Sessions,
               SUM(CASE WHEN MetricDate >= :s2 THEN EngagedSessions ELSE 0 END) AS EngagedSessions,
               SUM(CASE WHEN MetricDate >= :s3 THEN KeyEvents ELSE 0 END) AS KeyEvents,
               SUM(CASE WHEN MetricDate < :s4 THEN Sessions ELSE 0 END) AS PriorSessions
        FROM dbo.MktGa4Daily
        WHERE Grain = N'channel' AND MetricDate BETWEEN :ps AND :e
        GROUP BY Dim1
        ORDER BY Sessions DESC, PriorSessions DESC, Dim1
    SQL);
    $stmt->execute(['s' => $s, 's2' => $s, 's3' => $s, 's4' => $s, 'ps' => $ps, 'e' => $e]);

    return array_values(array_filter(
        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        static fn(array $r): bool => (int) $r['Sessions'] > 0 || (int) $r['PriorSessions'] > 0
    ));
}

/**
 * Campaign assets with the GA4 sessions their tagged links drove — in the window and all time.
 * Includes scheduled / posted assets that have no sessions yet.
 */
function mkt_perf_assets(?array $window): array
{
    [$s, $e] = $window ?? ['1900-01-01', '1900-01-01'];
    $stmt = db()->prepare(<<<SQL
        SELECT a.AssetID, a.CampaignID, a.Channel, a.SequenceNo, a.Title, a.Subject, a.Status,
               CONVERT(varchar(19), a.PostedAt, 120) AS PostedAt, a.ExternalPostUrl,
               c.Name AS CampaignName, c.Slug AS CampaignSlug,
               COALESCE(g.Sessions, 0) AS Sessions, COALESCE(g.EngagedSessions, 0) AS EngagedSessions,
               COALESCE(g.KeyEvents, 0) AS KeyEvents, COALESCE(g.Transactions, 0) AS Transactions,
               COALESCE(g.Revenue, 0) AS Revenue, COALESCE(g.AllSessions, 0) AS AllSessions,
               CONVERT(varchar(10), g.LastSeen, 23) AS LastSeen
        FROM dbo.MktAsset a
        INNER JOIN dbo.MktCampaign c ON c.CampaignID = a.CampaignID
        LEFT JOIN (
            SELECT AssetID,
                   SUM(CASE WHEN MetricDate BETWEEN :s AND :e THEN Sessions ELSE 0 END) AS Sessions,
                   SUM(CASE WHEN MetricDate BETWEEN :s2 AND :e2 THEN EngagedSessions ELSE 0 END) AS EngagedSessions,
                   SUM(CASE WHEN MetricDate BETWEEN :s3 AND :e3 THEN KeyEvents ELSE 0 END) AS KeyEvents,
                   SUM(CASE WHEN MetricDate BETWEEN :s4 AND :e4 THEN Transactions ELSE 0 END) AS Transactions,
                   SUM(CASE WHEN MetricDate BETWEEN :s5 AND :e5 THEN Revenue ELSE 0 END) AS Revenue,
                   SUM(Sessions) AS AllSessions, MAX(MetricDate) AS LastSeen
            FROM dbo.MktGa4Daily
            WHERE Grain = N'utm' AND AssetID IS NOT NULL
            GROUP BY AssetID
        ) g ON g.AssetID = a.AssetID
        WHERE a.Status IN (N'scheduled', N'posted') OR g.AssetID IS NOT NULL
        ORDER BY COALESCE(g.Sessions, 0) DESC, COALESCE(g.AllSessions, 0) DESC, a.PostedAt DESC, a.AssetID DESC
    SQL);
    $params = [];
    foreach (['', '2', '3', '4', '5'] as $n) {
        $params['s' . $n] = $s;
        $params['e' . $n] = $e;
    }
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** UTM-tagged traffic in the window that did not resolve to a campaign asset. */
function mkt_perf_other_tagged(?array $window, int $limit = 50): array
{
    if ($window === null) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    $stmt = db()->prepare(<<<SQL
        SELECT TOP ({$limit}) Dim1 AS Source, Dim2 AS Medium, Dim3 AS Campaign, Dim4 AS Content,
               SUM(Sessions) AS Sessions, SUM(EngagedSessions) AS EngagedSessions, SUM(KeyEvents) AS KeyEvents
        FROM dbo.MktGa4Daily
        WHERE Grain = N'utm' AND AssetID IS NULL AND MetricDate BETWEEN :s AND :e
        GROUP BY Dim1, Dim2, Dim3, Dim4
        ORDER BY SUM(Sessions) DESC, Dim1
    SQL);
    $stmt->execute(['s' => $window[0], 'e' => $window[1]]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Top search queries site-wide with the page that ranks for each, current vs prior clicks. */
function mkt_perf_queries(array $windows, int $limit = 100): array
{
    if ($windows['gsc'] === null) {
        return [];
    }
    $limit = max(1, min(500, $limit));
    [$s, $e] = $windows['gsc'];
    [$ps, $pe] = $windows['gsc_prior'];
    $stmt = db()->prepare(<<<SQL
        WITH cur AS (
            SELECT Query, SUM(Clicks) AS Clicks, SUM(Impressions) AS Impressions,
                   CAST(SUM(Position * Impressions) / NULLIF(SUM(Impressions), 0) AS DECIMAL(9, 1)) AS Position
            FROM dbo.MktGscDaily
            WHERE Grain = N'query' AND MetricDate BETWEEN :s AND :e
            GROUP BY Query
        ), top_page AS (
            SELECT Query, PageID, Page,
                   ROW_NUMBER() OVER (PARTITION BY Query ORDER BY SUM(Clicks) DESC, SUM(Impressions) DESC) AS rn,
                   COUNT(*) OVER (PARTITION BY Query) AS PageCount
            FROM dbo.MktGscDaily
            WHERE Grain = N'query' AND MetricDate BETWEEN :s2 AND :e2
            GROUP BY Query, PageID, Page
        ), prior AS (
            SELECT Query, SUM(Clicks) AS Clicks
            FROM dbo.MktGscDaily
            WHERE Grain = N'query' AND MetricDate BETWEEN :ps AND :pe
            GROUP BY Query
        )
        SELECT TOP ({$limit}) cur.Query, cur.Clicks, cur.Impressions, cur.Position,
               COALESCE(prior.Clicks, 0) AS PriorClicks,
               tp.PageID, tp.Page, tp.PageCount, p.Path,
               CASE WHEN EXISTS (SELECT 1 FROM dbo.MktPageKeyword k WHERE k.Keyword = cur.Query) THEN 1 ELSE 0 END AS Mapped
        FROM cur
        LEFT JOIN top_page tp ON tp.Query = cur.Query AND tp.rn = 1
        LEFT JOIN dbo.MktPage p ON p.PageID = tp.PageID
        LEFT JOIN prior ON prior.Query = cur.Query
        ORDER BY cur.Clicks DESC, cur.Impressions DESC, cur.Query
    SQL);
    $stmt->execute(['s' => $s, 'e' => $e, 's2' => $s, 'e2' => $e, 'ps' => $ps, 'pe' => $pe]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Top GA4 landing pages with their inventory page (when mapped) and search clicks. */
function mkt_perf_landing(array $windows, int $limit = 100): array
{
    if ($windows['ga4'] === null) {
        return [];
    }
    $limit = max(1, min(500, $limit));
    [$s, $e] = $windows['ga4'];
    [$gs, $ge] = $windows['gsc'] ?? ['1900-01-01', '1900-01-01'];
    $stmt = db()->prepare(<<<SQL
        SELECT TOP ({$limit}) g.Dim1 AS LandingPage, g.PageID, p.Path, p.PageType, p.Title, p.Issues,
               SUM(g.Sessions) AS Sessions, SUM(g.EngagedSessions) AS EngagedSessions,
               SUM(g.EngagementSeconds) AS EngagementSeconds, SUM(g.KeyEvents) AS KeyEvents,
               SUM(g.Transactions) AS Transactions,
               (SELECT COALESCE(SUM(q.Clicks), 0) FROM dbo.MktGscDaily q
                WHERE q.PageID = g.PageID AND q.Grain = N'page' AND q.MetricDate BETWEEN :gs AND :ge) AS SearchClicks
        FROM dbo.MktGa4Daily g
        LEFT JOIN dbo.MktPage p ON p.PageID = g.PageID
        WHERE g.Grain = N'landing' AND g.MetricDate BETWEEN :s AND :e
        GROUP BY g.Dim1, g.PageID, p.Path, p.PageType, p.Title, p.Issues
        ORDER BY SUM(g.Sessions) DESC, g.Dim1
    SQL);
    $stmt->execute(['s' => $s, 'e' => $e, 'gs' => $gs, 'ge' => $ge]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Per-day GA4 sessions from one asset's tagged link, newest first. */
function mkt_perf_asset_daily(int $assetId, int $limit = 60): array
{
    $limit = max(1, min(365, $limit));
    $stmt = db()->prepare(<<<SQL
        SELECT TOP ({$limit}) CONVERT(varchar(10), MetricDate, 23) AS MetricDate, SUM(Sessions) AS Sessions,
               SUM(EngagedSessions) AS EngagedSessions, SUM(KeyEvents) AS KeyEvents, SUM(Transactions) AS Transactions
        FROM dbo.MktGa4Daily
        WHERE Grain = N'utm' AND AssetID = :id
        GROUP BY MetricDate
        ORDER BY MetricDate DESC
    SQL);
    $stmt->execute(['id' => $assetId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** "+12%" / "−5%" / "new" / "" change label between two numbers. */
function mkt_perf_change(float $current, float $prior): string
{
    if ($prior <= 0) {
        return $current > 0 ? 'new' : '';
    }
    $pct = (int) round(($current - $prior) / $prior * 100);

    return ($pct > 0 ? '+' : ($pct < 0 ? '−' : '±')) . abs($pct) . '%';
}

function mkt_perf_rate(float $part, float $whole): string
{
    return $whole > 0 ? round($part / $whole * 100) . '%' : '—';
}
