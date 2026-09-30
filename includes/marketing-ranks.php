<?php
require_once __DIR__ . '/marketing.php';

/**
 * Rank Tracker: positions for keywords with TrackRank = 1. Our positions come from Search Console query rows
 * (view MktRankObservation) and imported results; competitor positions only from imports. Windows are anchored on
 * the latest observation date A: current = A-6..A, 7 days ago = A-13..A-7, four weeks ago = A-34..A-28
 * (the rank_drop alert in the Function App uses the same windows).
 */
const MKT_RANK_BANDS = [
    'top3'    => 'Top 3',
    'page1'   => '4–10 (page 1)',
    'p2_3'    => '11–30',
    'low'     => '31–100',
    'missing' => 'Not found / no data',
];

const MKT_IMPORT_MAX_BYTES = 5 * 1024 * 1024;

/* ---------- Shared import helpers (also used by Backlinks) ---------- */

function mkt_our_host(): string
{
    return mkt_domain_of((string) marketing_setting('analytics.allowed_host', 'nutraaxislabs.com')) ?: 'nutraaxislabs.com';
}

/** Lower-case host without www., from a URL or bare domain. */
function mkt_domain_of(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }
    if (!str_contains($value, '://')) {
        $value = 'http://' . ltrim($value, '/');
    }
    $host = (string) (parse_url($value, PHP_URL_HOST) ?? '');

    return preg_replace('/^www\./', '', rtrim($host, '.')) ?? '';
}

function mkt_is_our_domain(string $domain): bool
{
    return $domain !== '' && $domain === mkt_our_host();
}

/** domain => name from seo.competitor_domains (lines "domain|name"). */
function mkt_competitor_domains(): array
{
    $map = [];
    foreach (marketing_setting_lines('seo.competitor_domains') as $line) {
        [$domain, $name] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
        $domain = mkt_domain_of($domain);
        if ($domain !== '') {
            $map[$domain] = $name !== '' ? $name : $domain;
        }
    }

    return $map;
}

/** Competitor name for a domain (exact or subdomain match), or null. */
function mkt_competitor_for(string $domain, array $map): ?string
{
    if ($domain === '') {
        return null;
    }
    foreach ($map as $known => $name) {
        if ($domain === $known || str_ends_with($domain, '.' . $known)) {
            return $name;
        }
    }

    return null;
}

/** Pasted text or an uploaded file (file wins). Returns [text, error]. */
function mkt_import_text(?array $file, string $paste): array
{
    if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return ['', 'The file did not upload.'];
        }
        if ((int) ($file['size'] ?? 0) > MKT_IMPORT_MAX_BYTES) {
            return ['', 'The file is larger than 5 MB.'];
        }
        $text = (string) file_get_contents((string) $file['tmp_name']);
    } else {
        $text = $paste;
    }
    $text = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $text));
    if ($text === '') {
        return ['', 'Paste the results or choose a file.'];
    }
    if (strlen($text) > MKT_IMPORT_MAX_BYTES) {
        return ['', 'The pasted text is larger than 5 MB.'];
    }

    return [$text, null];
}

/** OpenRush envelopes: one object, a list of them, or bare data objects. Returns list of data arrays, or null if not JSON. */
function mkt_openrush_payloads(string $text): ?array
{
    if (!in_array($text[0] ?? '', ['{', '['], true)) {
        return null;
    }
    $json = json_decode($text, true);
    if (!is_array($json)) {
        return null;
    }
    $list = array_is_list($json) ? $json : [$json];
    $out = [];
    foreach ($list as $item) {
        if (is_array($item)) {
            $out[] = is_array($item['data'] ?? null) ? $item['data'] : $item;
        }
    }

    return $out;
}

/** CSV text => list of rows keyed by lower-case header. */
function mkt_csv_records(string $text): array
{
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $text);
    rewind($handle);
    $first = strtok($text, "\n");
    $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : (str_contains((string) $first, "\t") && !str_contains((string) $first, ',') ? "\t" : ',');
    $header = fgetcsv($handle, null, $delimiter, '"', '\\');
    if (!is_array($header)) {
        fclose($handle);
        return [];
    }
    $header = array_map(static fn($h): string => strtolower(trim((string) $h)), $header);
    $rows = [];
    while (($row = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
        if ($row === [null]) {
            continue;
        }
        $record = [];
        foreach ($header as $i => $col) {
            $record[$col] = trim((string) ($row[$i] ?? ''));
        }
        $rows[] = $record;
    }
    fclose($handle);

    return $rows;
}

/** First non-empty value among header aliases. */
function mkt_csv_pick(array $record, array $aliases): string
{
    foreach ($aliases as $alias) {
        if (isset($record[$alias]) && $record[$alias] !== '') {
            return $record[$alias];
        }
    }

    return '';
}

/** Today (or a relative day) in Central time, as Y-m-d. */
function mkt_local_date(string $modify = 'today'): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Chicago')))->modify($modify)->format('Y-m-d');
}

function mkt_iso_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }
    $tz = new DateTimeZone('America/Chicago');
    try {
        return (new DateTimeImmutable($value, $tz))->setTimezone($tz)->format('Y-m-d');
    } catch (Exception) {
        return null;
    }
}

/* ---------- Positions ---------- */

/** Latest date with a position; if nothing has ranked yet, the latest date checked. */
function mkt_rank_anchor(): ?string
{
    $value = db()->query(<<<SQL
        SELECT CONVERT(varchar(10), COALESCE(MAX(CASE WHEN Position IS NOT NULL THEN ObsDate END), MAX(ObsDate)), 23) FROM dbo.MktRankObservation
    SQL)->fetchColumn();

    return $value ? (string) $value : null;
}

function mkt_rank_band(?float $position): string
{
    if ($position === null) {
        return 'missing';
    }

    return match (true) {
        $position <= 3.49  => 'top3',
        $position <= 10.49 => 'page1',
        $position <= 30.49 => 'p2_3',
        default            => 'low',
    };
}

/**
 * Tracked keywords with current position, changes (positive = moved up), 13-week trend, ranking page, 28-day
 * Search Console totals and competitors on page 1 in the latest import.
 */
function mkt_rank_keywords(): array
{
    $anchor = mkt_rank_anchor();
    $pdo = db();
    $rows = $pdo->query("SELECT KeywordID, Keyword, Cluster, Priority FROM dbo.MktKeyword WHERE TrackRank = 1 AND Status = N'active' ORDER BY Keyword")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $out[(int) $row['KeywordID']] = $row + [
            'Cur' => null, 'Prev7' => null, 'Prev28' => null, 'CurGsc' => false, 'Checked' => false,
            'LastPos' => null, 'LastDate' => null, 'Impr28' => 0, 'Clicks28' => 0, 'Page' => null,
            'CompPage1' => null, 'Trend' => [],
        ];
    }
    if ($out === []) {
        return ['anchor' => $anchor, 'rows' => []];
    }

    if ($anchor !== null) {
        $stmt = $pdo->prepare(<<<SQL
            SELECT o.KeywordID,
                   AVG(CASE WHEN o.ObsDate >= DATEADD(DAY, -6, :a1) THEN o.Position END) AS Cur,
                   AVG(CASE WHEN o.ObsDate BETWEEN DATEADD(DAY, -13, :a2) AND DATEADD(DAY, -7, :a3) THEN o.Position END) AS Prev7,
                   AVG(CASE WHEN o.ObsDate BETWEEN DATEADD(DAY, -34, :a4) AND DATEADD(DAY, -28, :a5) THEN o.Position END) AS Prev28,
                   MAX(CASE WHEN o.ObsDate >= DATEADD(DAY, -6, :a6) AND o.Source = N'gsc' THEN 1 ELSE 0 END) AS CurGsc,
                   MAX(CASE WHEN o.ObsDate >= DATEADD(DAY, -6, :a7) THEN 1 ELSE 0 END) AS Checked,
                   SUM(CASE WHEN o.ObsDate >= DATEADD(DAY, -27, :a8) THEN o.Impressions ELSE 0 END) AS Impr28,
                   SUM(CASE WHEN o.ObsDate >= DATEADD(DAY, -27, :a9) THEN o.Clicks ELSE 0 END) AS Clicks28
            FROM dbo.MktRankObservation o
            WHERE o.ObsDate >= DATEADD(DAY, -90, :a10)
            GROUP BY o.KeywordID
        SQL);
        $params = [];
        for ($i = 1; $i <= 10; $i++) {
            $params['a' . $i] = $anchor;
        }
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) $r['KeywordID'];
            if (!isset($out[$id])) {
                continue;
            }
            $f = static fn($v): ?float => $v === null ? null : round((float) $v, 1);
            $out[$id]['Cur'] = $f($r['Cur']);
            $out[$id]['Prev7'] = $f($r['Prev7']);
            $out[$id]['Prev28'] = $f($r['Prev28']);
            $out[$id]['CurGsc'] = (int) $r['CurGsc'] === 1;
            $out[$id]['Checked'] = (int) $r['Checked'] === 1;
            $out[$id]['Impr28'] = (int) $r['Impr28'];
            $out[$id]['Clicks28'] = (int) $r['Clicks28'];
        }

        $trend = $pdo->prepare(<<<SQL
            SELECT KeywordID, DATEDIFF(DAY, ObsDate, :a1) / 7 AS WeeksAgo, AVG(Position) AS Position
            FROM dbo.MktRankObservation
            WHERE Position IS NOT NULL AND ObsDate BETWEEN DATEADD(DAY, -90, :a2) AND :a3
            GROUP BY KeywordID, DATEDIFF(DAY, ObsDate, :a4) / 7
        SQL);
        $trend->execute(['a1' => $anchor, 'a2' => $anchor, 'a3' => $anchor, 'a4' => $anchor]);
        foreach ($trend->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) $r['KeywordID'];
            if (isset($out[$id])) {
                $out[$id]['Trend'][12 - min(12, (int) $r['WeeksAgo'])] = (float) $r['Position'];
            }
        }
    }

    $last = $pdo->query(<<<SQL
        SELECT KeywordID, Position, CONVERT(varchar(10), ObsDate, 23) AS ObsDate FROM (
            SELECT KeywordID, Position, ObsDate, ROW_NUMBER() OVER (PARTITION BY KeywordID ORDER BY ObsDate DESC) AS rn
            FROM dbo.MktRankObservation WHERE Position IS NOT NULL
        ) x WHERE rn = 1
    SQL)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($last as $r) {
        $id = (int) $r['KeywordID'];
        if (isset($out[$id])) {
            $out[$id]['LastPos'] = round((float) $r['Position'], 1);
            $out[$id]['LastDate'] = (string) $r['ObsDate'];
        }
    }

    $gsc = mkt_rank_gsc_window();
    if ($gsc !== null) {
        $pages = $pdo->prepare(<<<SQL
            SELECT KeywordID, Path FROM (
                SELECT k.KeywordID, p.Path, ROW_NUMBER() OVER (PARTITION BY k.KeywordID ORDER BY SUM(g.Impressions) DESC) AS rn
                FROM dbo.MktKeyword k
                INNER JOIN dbo.MktGscDaily g ON g.Grain = N'query' AND LOWER(g.Query) = LOWER(k.Keyword) AND g.MetricDate BETWEEN :s AND :e
                INNER JOIN dbo.MktPage p ON p.PageID = g.PageID
                WHERE k.TrackRank = 1
                GROUP BY k.KeywordID, p.Path
            ) x WHERE rn = 1
        SQL);
        $pages->execute(['s' => $gsc[0], 'e' => $gsc[1]]);
        foreach ($pages->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (isset($out[(int) $r['KeywordID']])) {
                $out[(int) $r['KeywordID']]['Page'] = (string) $r['Path'];
            }
        }
    }

    $latest = $pdo->query(<<<SQL
        WITH l AS (SELECT KeywordID, MAX(ResultDate) AS D FROM dbo.MktRankResult GROUP BY KeywordID)
        SELECT r.KeywordID,
               COUNT(DISTINCT CASE WHEN r.IsOurs = 0 AND r.Position <= 10 THEN r.CompetitorName END) AS CompPage1,
               MAX(CASE WHEN r.IsOurs = 1 THEN r.Url END) AS OurUrl
        FROM dbo.MktRankResult r INNER JOIN l ON l.KeywordID = r.KeywordID AND l.D = r.ResultDate
        GROUP BY r.KeywordID
    SQL)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($latest as $r) {
        $id = (int) $r['KeywordID'];
        if (!isset($out[$id])) {
            continue;
        }
        $out[$id]['CompPage1'] = (int) $r['CompPage1'];
        if ($out[$id]['Page'] === null && !empty($r['OurUrl'])) {
            $out[$id]['Page'] = (string) (parse_url((string) $r['OurUrl'], PHP_URL_PATH) ?: '/');
        }
    }

    foreach ($out as &$row) {
        $row['Change7'] = $row['Cur'] !== null && $row['Prev7'] !== null ? round($row['Prev7'] - $row['Cur'], 1) : null;
        $row['Change28'] = $row['Cur'] !== null && $row['Prev28'] !== null ? round($row['Prev28'] - $row['Cur'], 1) : null;
        $row['Band'] = mkt_rank_band($row['Cur']);
        ksort($row['Trend']);
    }
    unset($row);

    return ['anchor' => $anchor, 'rows' => array_values($out)];
}

/** Last 28 days of Search Console data [start, end], or null. */
function mkt_rank_gsc_window(): ?array
{
    $latest = db()->query('SELECT CONVERT(varchar(10), MAX(MetricDate), 23) FROM dbo.MktGscDaily')->fetchColumn();
    if (!$latest) {
        return null;
    }
    $end = new DateTimeImmutable((string) $latest);

    return [$end->modify('-27 days')->format('Y-m-d'), $end->format('Y-m-d')];
}

function mkt_rank_filter(array $rows, array $filters): array
{
    $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));

    return array_values(array_filter($rows, static function (array $r) use ($filters, $q): bool {
        if (($filters['band'] ?? '') !== '' && $r['Band'] !== $filters['band']) {
            return false;
        }
        if (($filters['cluster'] ?? '') !== '' && (string) ($r['Cluster'] ?? '') !== $filters['cluster']) {
            return false;
        }
        if ($q !== '' && !str_contains(mb_strtolower($r['Keyword'] . ' ' . ($r['Page'] ?? '')), $q)) {
            return false;
        }

        return true;
    }));
}

function mkt_rank_sort(array $rows): array
{
    usort($rows, static fn(array $a, array $b): int => [$a['Cur'] === null, $a['Cur'] ?? 0, $a['LastPos'] === null, mb_strtolower($a['Keyword'])]
        <=> [$b['Cur'] === null, $b['Cur'] ?? 0, $b['LastPos'] === null, mb_strtolower($b['Keyword'])]);

    return $rows;
}

function mkt_rank_summary(array $rows): array
{
    $counts = array_fill_keys(array_keys(MKT_RANK_BANDS), 0);
    foreach ($rows as $r) {
        $counts[$r['Band']]++;
    }

    return $counts + ['total' => count($rows)];
}

/** Biggest 28-day gains and drops, and keywords that reached or left page 1 against four weeks earlier. */
function mkt_rank_movers(array $rows): array
{
    $moved = array_values(array_filter($rows, static fn(array $r): bool => $r['Change28'] !== null && abs($r['Change28']) >= 0.5));
    $gains = array_values(array_filter($moved, static fn(array $r): bool => $r['Change28'] > 0));
    $drops = array_values(array_filter($moved, static fn(array $r): bool => $r['Change28'] < 0));
    usort($gains, static fn(array $a, array $b): int => $b['Change28'] <=> $a['Change28']);
    usort($drops, static fn(array $a, array $b): int => $a['Change28'] <=> $b['Change28']);
    $reached = [];
    $left = [];
    foreach ($rows as $r) {
        if ($r['Cur'] === null || $r['Prev28'] === null) {
            continue;
        }
        if ($r['Prev28'] <= 10 && $r['Cur'] > 10) {
            $left[] = $r;
        } elseif ($r['Prev28'] > 10 && $r['Cur'] <= 10) {
            $reached[] = $r;
        }
    }

    return ['gains' => array_slice($gains, 0, 15), 'drops' => array_slice($drops, 0, 15), 'reached' => $reached, 'left' => $left];
}

/**
 * Per competitor in Settings: tracked keywords on page 1, average position and keywords where they outrank us,
 * from each keyword's latest imported result for that competitor; plus page-1 counts for the last 8 result dates.
 */
function mkt_rank_competitors(array $keywordRows): array
{
    $ours = [];
    foreach ($keywordRows as $r) {
        $ours[(int) $r['KeywordID']] = $r['Cur'] ?? $r['LastPos'];
    }
    $stats = [];
    foreach (array_unique(array_values(mkt_competitor_domains())) as $name) {
        $stats[$name] = ['name' => $name, 'domains' => [], 'page1' => 0, 'positions' => [], 'outrank' => 0, 'keywords' => 0, 'trend' => []];
    }
    foreach (mkt_competitor_domains() as $domain => $name) {
        $stats[$name]['domains'][] = $domain;
    }
    if ($stats === []) {
        return [];
    }

    $latest = db()->query(<<<SQL
        SELECT KeywordID, CompetitorName, Position FROM (
            SELECT r.KeywordID, r.CompetitorName, r.Position,
                   ROW_NUMBER() OVER (PARTITION BY r.KeywordID, r.CompetitorName ORDER BY r.ResultDate DESC, r.ResultID DESC) AS rn
            FROM dbo.MktRankResult r INNER JOIN dbo.MktKeyword k ON k.KeywordID = r.KeywordID AND k.TrackRank = 1 AND k.Status = N'active'
            WHERE r.IsOurs = 0 AND r.CompetitorName IS NOT NULL
        ) x WHERE rn = 1 AND Position IS NOT NULL
    SQL)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($latest as $r) {
        $name = (string) $r['CompetitorName'];
        if (!isset($stats[$name])) {
            continue;
        }
        $pos = (float) $r['Position'];
        $stats[$name]['keywords']++;
        $stats[$name]['positions'][] = $pos;
        if ($pos <= 10) {
            $stats[$name]['page1']++;
        }
        $our = $ours[(int) $r['KeywordID']] ?? null;
        if ($our === null || $pos < $our) {
            $stats[$name]['outrank']++;
        }
    }

    $trend = db()->query(<<<SQL
        WITH d AS (SELECT DISTINCT TOP (8) ResultDate FROM dbo.MktRankResult WHERE IsOurs = 0 ORDER BY ResultDate DESC)
        SELECT r.CompetitorName, CONVERT(varchar(10), r.ResultDate, 23) AS ResultDate, COUNT(DISTINCT CASE WHEN r.Position <= 10 THEN r.KeywordID END) AS Page1
        FROM dbo.MktRankResult r INNER JOIN d ON d.ResultDate = r.ResultDate
        WHERE r.IsOurs = 0 AND r.CompetitorName IS NOT NULL
        GROUP BY r.CompetitorName, r.ResultDate
        ORDER BY r.ResultDate
    SQL)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trend as $r) {
        if (isset($stats[(string) $r['CompetitorName']])) {
            $stats[(string) $r['CompetitorName']]['trend'][] = (int) $r['Page1'];
        }
    }

    foreach ($stats as &$s) {
        $s['avg'] = $s['positions'] !== [] ? round(array_sum($s['positions']) / count($s['positions']), 1) : null;
    }
    unset($s);
    uasort($stats, static fn(array $a, array $b): int => [$b['page1'], $b['keywords']] <=> [$a['page1'], $a['keywords']]);

    return array_values($stats);
}

/* ---------- Imports ---------- */

function mkt_rank_imports(int $limit = 20): array
{
    $limit = max(1, min(100, $limit));

    return db()->query(<<<SQL
        SELECT TOP ({$limit}) i.*, CONVERT(varchar(10), i.ResultDate, 23) AS ResultDateIso, u.UserName AS CreatedByName
        FROM dbo.MktRankImport i LEFT JOIN dbo.[User] u ON u.UserID = i.CreatedBy
        ORDER BY i.ImportID DESC
    SQL)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Import ranking results. OpenRush JSON (search visibility for one domain, or search results for one keyword;
 * one envelope or a list) or CSV with keyword, position and url (or domain) columns, such as a Semrush Organic
 * Positions export. Only tracked keywords, our domain and competitor domains in Settings are kept.
 */
function mkt_rank_import(string $text, ?string $resultDate): array
{
    $tracked = [];
    foreach (db()->query("SELECT KeywordID, Keyword FROM dbo.MktKeyword WHERE TrackRank = 1 AND Status = N'active'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $tracked[mb_strtolower(trim((string) $r['Keyword']))] = (int) $r['KeywordID'];
    }
    if ($tracked === []) {
        return ['ok' => false, 'error' => 'No keywords are tracked. Tick “Track rank” on keywords in the Keyword Universe first.'];
    }
    $competitors = mkt_competitor_domains();
    $results = [];
    $skipped = 0;
    $dateFound = null;

    $add = static function (string $keyword, string $domain, ?float $position, ?string $url, bool $allowMissing) use (&$results, &$skipped, $tracked, $competitors): void {
        $keywordId = $tracked[mb_strtolower(trim($keyword))] ?? null;
        if ($keywordId === null) {
            $skipped++;
            return;
        }
        $isOurs = mkt_is_our_domain($domain);
        $competitor = $isOurs ? null : mkt_competitor_for($domain, $competitors);
        if (!$isOurs && $competitor === null) {
            return;
        }
        if ($position === null && !($isOurs && $allowMissing)) {
            return;
        }
        if ($position !== null && ($position < 1 || $position > 999)) {
            $skipped++;
            return;
        }
        $key = $keywordId . '|' . ($isOurs ? '*' : $competitor);
        $existing = $results[$key] ?? null;
        if ($existing !== null && ($position === null || ($existing['Position'] !== null && $existing['Position'] <= $position))) {
            return;
        }
        $results[$key] = [
            'KeywordID'      => $keywordId,
            'IsOurs'         => $isOurs ? 1 : 0,
            'Domain'         => mb_substr($domain, 0, 253),
            'CompetitorName' => $competitor !== null ? mb_substr($competitor, 0, 150) : null,
            'Position'       => $position,
            'Url'            => $isOurs && $url !== null && $url !== '' ? mb_substr($url, 0, 1000) : null,
        ];
    };

    $payloads = mkt_openrush_payloads($text);
    if ($payloads !== null) {
        $source = 'openrush';
        foreach ($payloads as $data) {
            $dateFound ??= mkt_iso_date($data['fetched_at'] ?? null);
            if (is_array($data['positions'] ?? null)) {
                $domain = mkt_domain_of((string) ($data['domain'] ?? ''));
                if (!mkt_is_our_domain($domain) && mkt_competitor_for($domain, $competitors) === null) {
                    return ['ok' => false, 'error' => sprintf('These results are for %s, which is neither %s nor a competitor domain in Settings (seo.competitor_domains).', $domain ?: 'an unnamed domain', mkt_our_host())];
                }
                foreach ($data['positions'] as $p) {
                    if (is_array($p)) {
                        $pos = $p['position'] ?? null;
                        $add((string) ($p['keyword'] ?? ''), $domain, $pos === null ? null : (float) $pos, isset($p['url']) ? (string) $p['url'] : null, true);
                    }
                }
            } elseif (is_array($data['organic'] ?? null)) {
                $keyword = (string) ($data['query'] ?? $data['keyword'] ?? '');
                $foundOurs = false;
                foreach ($data['organic'] as $o) {
                    if (!is_array($o) || !isset($o['position'])) {
                        continue;
                    }
                    $url = (string) ($o['url'] ?? '');
                    $domain = mkt_domain_of((string) ($o['domain'] ?? '') ?: $url);
                    $foundOurs = $foundOurs || mkt_is_our_domain($domain);
                    $add($keyword, $domain, (float) $o['position'], $url, false);
                }
                if (!$foundOurs) {
                    $add($keyword, mkt_our_host(), null, null, true);
                }
            } else {
                return ['ok' => false, 'error' => 'This JSON is not an OpenRush search visibility or search results payload.'];
            }
        }
    } else {
        $source = 'csv';
        $records = mkt_csv_records($text);
        $headers = array_keys($records[0] ?? []);
        if (array_intersect($headers, ['keyword', 'query', 'search term']) === [] || array_intersect($headers, ['position', 'current position', 'rank', 'pos']) === []) {
            return ['ok' => false, 'error' => 'The CSV needs a header row with keyword and position columns (and url or domain).'];
        }
        $hasSite = array_intersect($headers, ['url', 'landing page', 'page', 'ranking url', 'domain', 'site']) !== [];
        foreach ($records as $record) {
            $url = mkt_csv_pick($record, ['url', 'landing page', 'ranking url', 'page']);
            $domain = mkt_domain_of(mkt_csv_pick($record, ['domain', 'site']) ?: $url) ?: ($hasSite ? '' : mkt_our_host());
            $pos = mkt_csv_pick($record, ['position', 'current position', 'rank', 'pos']);
            $dateFound ??= mkt_iso_date(mkt_csv_pick($record, ['date']));
            $add(mkt_csv_pick($record, ['keyword', 'query', 'search term']), $domain, is_numeric($pos) ? (float) $pos : null, $url, false);
        }
    }

    if ($results === []) {
        return ['ok' => false, 'error' => sprintf('Nothing to import: no rows matched a tracked keyword for %s or a competitor domain (%d rows for untracked keywords skipped).', mkt_our_host(), $skipped)];
    }
    $date = mkt_iso_date($resultDate) ?? $dateFound ?? mkt_local_date();
    $keywords = count(array_unique(array_column($results, 'KeywordID')));
    $ours = count(array_filter($results, static fn(array $r): bool => $r['IsOurs'] === 1 && $r['Position'] !== null));
    $competitorRows = count(array_filter($results, static fn(array $r): bool => $r['IsOurs'] === 0));

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO dbo.MktRankImport (Source, ResultDate, Keywords, OursRanked, CompetitorRows, Skipped, CreatedBy) OUTPUT INSERTED.ImportID AS inserted_id VALUES (:s, :d, :k, :o, :c, :sk, :u)');
        $stmt->execute(['s' => $source, 'd' => $date, 'k' => $keywords, 'o' => $ours, 'c' => $competitorRows, 'sk' => $skipped, 'u' => marketing_user_id()]);
        $importId = db_fetch_inserted_int($stmt, 'inserted_id');
        $insert = $pdo->prepare('INSERT INTO dbo.MktRankResult (ImportID, KeywordID, ResultDate, IsOurs, Domain, CompetitorName, Position, Url) VALUES (:i, :k, :d, :o, :dom, :c, :p, :url)');
        foreach ($results as $r) {
            $insert->execute([
                'i' => $importId, 'k' => $r['KeywordID'], 'd' => $date, 'o' => $r['IsOurs'], 'dom' => $r['Domain'],
                'c' => $r['CompetitorName'], 'p' => $r['Position'], 'url' => $r['Url'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['ok' => true, 'message' => sprintf('Imported results for %s: %d keywords, %d with our position, %d competitor positions%s.', marketing_format_date($date), $keywords, $ours, $competitorRows, $skipped > 0 ? sprintf(', %d rows for untracked keywords skipped', $skipped) : '')];
}

function mkt_rank_import_delete(int $importId): bool
{
    $stmt = db()->prepare('DELETE FROM dbo.MktRankImport WHERE ImportID = :id');
    $stmt->execute(['id' => $importId]);

    return $stmt->rowCount() > 0;
}

function mkt_rank_export_csv(array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rank-tracker-' . mkt_local_date() . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['keyword', 'cluster', 'position', 'change_7d', 'change_28d', 'source', 'last_position', 'last_date', 'ranking_page', 'impressions_28d', 'clicks_28d', 'competitors_on_page_1'], ',', '"', '\\');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['Keyword'], $r['Cluster'] ?? '', $r['Cur'] ?? '', $r['Change7'] ?? '', $r['Change28'] ?? '',
            $r['Cur'] === null ? '' : ($r['CurGsc'] ? 'search console' : 'import'), $r['LastPos'] ?? '', $r['LastDate'] ?? '',
            $r['Page'] ?? '', $r['Impr28'], $r['Clicks28'], $r['CompPage1'] ?? '',
        ], ',', '"', '\\');
    }
    fclose($out);
    exit;
}
