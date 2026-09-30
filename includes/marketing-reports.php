<?php

require_once __DIR__ . '/marketing-performance.php';
require_once __DIR__ . '/marketing-ranks.php';
require_once __DIR__ . '/marketing-engagement.php';
require_once __DIR__ . '/marketing-docs.php';

/** Earliest month offered in the picker — the month marketing data starts. */
const MKT_REPORT_FIRST_MONTH = '2026-07';

/**
 * Calendar-month period in Central time with the prior period to compare against.
 * The current month runs to today and compares with the same days of last month.
 *
 * @return ?array{ym: string, label: string, start: string, end: string, next: string, prior_start: string, prior_end: string, prior_label: string, partial: bool, utc_start: string, utc_end: string}
 */
function mkt_report_period(string $ym): ?array
{
    if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $ym) || $ym < MKT_REPORT_FIRST_MONTH) {
        return null;
    }
    $tz = new DateTimeZone('America/Chicago');
    $start = new DateTimeImmutable($ym . '-01', $tz);
    $today = new DateTimeImmutable(mkt_local_date(), $tz);
    if ($start > $today) {
        return null;
    }
    $monthEnd = $start->modify('last day of this month');
    $partial = $monthEnd >= $today;
    $end = $partial ? $today : $monthEnd;
    $priorStart = $start->modify('-1 month');
    $priorEnd = $partial
        ? min($priorStart->modify('+' . ((int) $end->format('j') - 1) . ' days'), $priorStart->modify('last day of this month'))
        : $priorStart->modify('last day of this month');
    $utc = new DateTimeZone('UTC');

    return [
        'ym'          => $ym,
        'label'       => $start->format('F Y'),
        'start'       => $start->format('Y-m-d'),
        'end'         => $end->format('Y-m-d'),
        'next'        => $end->modify('+1 day')->format('Y-m-d'),
        'prior_start' => $priorStart->format('Y-m-d'),
        'prior_end'   => $priorEnd->format('Y-m-d'),
        'prior_label' => $partial ? $priorStart->format('M j') . '–' . $priorEnd->format('j') : $priorStart->format('F Y'),
        'partial'     => $partial,
        'utc_start'   => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
        'utc_end'     => $end->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
    ];
}

/** Months with a report, newest first: [ym => label]. */
function mkt_report_months(): array
{
    $months = [];
    $cursor = new DateTimeImmutable(substr(mkt_local_date(), 0, 7) . '-01');
    while ($cursor->format('Y-m') >= MKT_REPORT_FIRST_MONTH) {
        $months[$cursor->format('Y-m')] = $cursor->format('F Y');
        $cursor = $cursor->modify('-1 month');
    }

    return $months;
}

/** The last full month, which is what the page opens on. */
function mkt_report_default_month(): string
{
    $last = (new DateTimeImmutable(substr(mkt_local_date(), 0, 7) . '-01'))->modify('-1 month')->format('Y-m');

    return $last >= MKT_REPORT_FIRST_MONTH ? $last : substr(mkt_local_date(), 0, 7);
}

/**
 * Run a single-row count query, binding the period bounds :s / :n (Central dates, end exclusive)
 * and :us / :ue (UTC timestamps, end exclusive). sqlsrv needs a distinct name per occurrence.
 */
function mkt_report_row(string $sql, array $p): array
{
    $values = ['s' => $p['start'], 'n' => $p['next'], 'us' => $p['utc_start'], 'ue' => $p['utc_end']];
    $params = [];
    $sql = (string) preg_replace_callback('/:(us|ue|s|n)\b/', static function (array $m) use ($values, &$params): string {
        $name = $m[1] . count($params);
        $params[$name] = $values[$m[1]];

        return ':' . $name;
    }, $sql);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return array_map(static fn($v) => $v === null ? 0 : $v, $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
}

/** Everything the monthly report shows, for one period. */
function mkt_report_data(array $p): array
{
    $windows = [
        'gsc'       => [$p['start'], $p['end']],
        'gsc_prior' => [$p['prior_start'], $p['prior_end']],
        'ga4'       => [$p['start'], $p['end']],
        'ga4_prior' => [$p['prior_start'], $p['prior_end']],
    ];

    $ranks = db()->prepare(<<<SQL
        WITH r AS (
            SELECT KeywordID, ResultDate, MIN(CASE WHEN IsOurs = 1 THEN Position END) AS Pos
            FROM dbo.MktRankResult WHERE ResultDate BETWEEN :ps AND :e
            GROUP BY KeywordID, ResultDate
        ), cur AS (
            SELECT KeywordID, Pos, ROW_NUMBER() OVER (PARTITION BY KeywordID ORDER BY ResultDate DESC) AS rn FROM r WHERE ResultDate >= :s
        ), pri AS (
            SELECT KeywordID, Pos, ROW_NUMBER() OVER (PARTITION BY KeywordID ORDER BY ResultDate DESC) AS rn FROM r WHERE ResultDate <= :pe
        )
        SELECT k.Keyword, cur.Pos AS Position, pri.Pos AS PriorPosition,
               CASE WHEN cur.KeywordID IS NULL THEN 0 ELSE 1 END AS Checked, CASE WHEN pri.KeywordID IS NULL THEN 0 ELSE 1 END AS PriorChecked
        FROM dbo.MktKeyword k
        LEFT JOIN cur ON cur.KeywordID = k.KeywordID AND cur.rn = 1
        LEFT JOIN pri ON pri.KeywordID = k.KeywordID AND pri.rn = 1
        WHERE cur.KeywordID IS NOT NULL OR pri.KeywordID IS NOT NULL
        ORDER BY CASE WHEN cur.Pos IS NULL THEN 1 ELSE 0 END, cur.Pos, k.Keyword
    SQL);
    $ranks->execute(['ps' => $p['prior_start'], 'e' => $p['end'], 's' => $p['start'], 'pe' => $p['prior_end']]);
    $rankRows = $ranks->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $newLinks = db()->prepare("SELECT TOP 10 SourceDomain, Authority, Followed FROM dbo.MktBacklink WHERE FirstSeen >= :s AND FirstSeen < :n ORDER BY Authority DESC, SourceDomain");
    $newLinks->execute(['s' => $p['start'], 'n' => $p['next']]);
    $newLinkRows = $newLinks->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $spendByJob = db()->prepare(<<<SQL
        SELECT TOP 8 COALESCE(l.ProcessName, l.ProcessCode, N'Portal action') AS JobName, COUNT(*) AS Calls, COALESCE(SUM(u.CostUsd), 0) AS CostUsd
        FROM dbo.MktApiUsage u
        LEFT JOIN dbo.ProcessExecutionLog l ON l.ProcessExecutionLogID = u.ProcessLogID
        WHERE u.Provider IN (N'anthropic', N'openai') AND u.CreatedAt >= :us AND u.CreatedAt < :ue
        GROUP BY COALESCE(l.ProcessName, l.ProcessCode, N'Portal action')
        ORDER BY SUM(u.CostUsd) DESC
    SQL);
    $spendByJob->execute(['us' => $p['utc_start'], 'ue' => $p['utc_end']]);
    $spendJobRows = $spendByJob->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $digests = db()->prepare("SELECT DigestID, CONVERT(varchar(10), PeriodStart, 23) AS PeriodStart, CONVERT(varchar(10), PeriodEnd, 23) AS PeriodEnd, Status, TaskCount
        FROM dbo.MktDigest WHERE PeriodEnd >= :s AND PeriodEnd < :n ORDER BY PeriodEnd");
    $digests->execute(['s' => $p['start'], 'n' => $p['next']]);
    $digestRows = $digests->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $assets = array_values(array_filter(mkt_perf_assets($windows['ga4']), static fn(array $a): bool => (int) $a['Sessions'] > 0));

    return [
        'gsc'        => mkt_perf_search_totals($windows['gsc']),
        'gsc_prior'  => mkt_perf_search_totals($windows['gsc_prior']),
        'ga4'        => mkt_perf_ga4_totals($windows['ga4']),
        'ga4_prior'  => mkt_perf_ga4_totals($windows['ga4_prior']),
        'gsc_latest' => mkt_analytics_latest_date('MktGscDaily'),
        'ga4_latest' => mkt_analytics_latest_date('MktGa4Daily'),
        'channels'   => mkt_perf_channels($windows),
        'queries'    => mkt_perf_queries($windows, 10),
        'landing'    => mkt_perf_landing($windows, 10),
        'ranks'      => $rankRows,
        'links'      => mkt_report_row(<<<SQL
            SELECT SUM(CASE WHEN FirstSeen >= :s AND FirstSeen < :n THEN 1 ELSE 0 END) AS New,
                   SUM(CASE WHEN LostAt >= :s AND LostAt < :n THEN 1 ELSE 0 END) AS Lost,
                   SUM(CASE WHEN FirstSeen < :n AND (LostAt IS NULL OR LostAt >= :n) THEN 1 ELSE 0 END) AS Live,
                   (SELECT COUNT(*) FROM dbo.MktProspect WHERE Status = N'won' AND WonAt >= :us AND WonAt < :ue) AS Won,
                   (SELECT COUNT(*) FROM dbo.MktProspect WHERE CreatedAt >= :us AND CreatedAt < :ue) AS Prospects
            FROM dbo.MktBacklink
        SQL, $p),
        'new_links'  => $newLinkRows,
        'issues'     => mkt_report_row(<<<SQL
            SELECT SUM(CASE WHEN FirstSeenAt >= :us AND FirstSeenAt < :ue THEN 1 ELSE 0 END) AS Opened,
                   SUM(CASE WHEN FixedAt >= :us AND FixedAt < :ue THEN 1 ELSE 0 END) AS Fixed,
                   SUM(CASE WHEN VerifiedAt >= :us AND VerifiedAt < :ue THEN 1 ELSE 0 END) AS Verified,
                   SUM(CASE WHEN ReopenedAt >= :us AND ReopenedAt < :ue THEN 1 ELSE 0 END) AS Reopened,
                   SUM(CASE WHEN Status IN (N'new', N'open') AND Severity = N'high' THEN 1 ELSE 0 END) AS OpenHigh,
                   SUM(CASE WHEN Status IN (N'new', N'open') AND Severity = N'medium' THEN 1 ELSE 0 END) AS OpenMedium,
                   SUM(CASE WHEN Status IN (N'new', N'open') AND Severity = N'low' THEN 1 ELSE 0 END) AS OpenLow
            FROM dbo.MktIssue
        SQL, $p),
        'content'    => mkt_report_row(<<<SQL
            SELECT (SELECT COUNT(*) FROM dbo.MktContent WHERE PublishedAt >= :us AND PublishedAt < :ue) AS Published,
                   (SELECT COUNT(*) FROM dbo.MktBlogPost WHERE FirstPublishedAt >= :us AND FirstPublishedAt < :ue) AS BlogPosts,
                   (SELECT COUNT(*) FROM dbo.MktCampaign WHERE GeneratedAt >= :us AND GeneratedAt < :ue) AS Campaigns,
                   (SELECT COUNT(*) FROM dbo.MktAsset WHERE PostedAt >= :us AND PostedAt < :ue) AS AssetsPosted
        SQL, $p),
        'assets'     => array_slice($assets, 0, 8),
        'research'   => mkt_report_row(<<<SQL
            SELECT (SELECT COUNT(*) FROM dbo.MktHarvestedItem WHERE FetchedAt >= :us AND FetchedAt < :ue) AS Harvested,
                   (SELECT COUNT(*) FROM dbo.MktTopic WHERE Status = N'accepted' AND DecidedAt >= :us AND DecidedAt < :ue) AS TopicsAccepted,
                   (SELECT COUNT(*) FROM dbo.MktLitSource WHERE Status = N'active' AND CreatedAt >= :us AND CreatedAt < :ue) AS SourcesAdded,
                   (SELECT COUNT(*) FROM dbo.MktLitFlyerRef WHERE MatchStatus = N'matched' AND UpdatedAt >= :us AND UpdatedAt < :ue) AS RefsMatched,
                   (SELECT COUNT(*) FROM dbo.MktLitRegulatory WHERE Status = N'action' AND UpdatedAt >= :us AND UpdatedAt < :ue) AS RegulatoryAction
        SQL, $p),
        'responses'  => mkt_report_row(<<<SQL
            SELECT SUM(CASE WHEN ReceivedAt >= :us AND ReceivedAt < :ue THEN 1 ELSE 0 END) AS Received,
                   SUM(CASE WHEN EscalatedAt >= :us AND EscalatedAt < :ue THEN 1 ELSE 0 END) AS Escalated,
                   SUM(CASE WHEN RepliedAt >= :us AND RepliedAt < :ue THEN 1 ELSE 0 END) AS Replied
            FROM dbo.MktResponse
        SQL, $p),
        'spend'      => mkt_report_row(<<<SQL
            SELECT COALESCE(SUM(CostUsd), 0) AS Cost, COUNT(*) AS Calls, SUM(CASE WHEN Ok = 0 THEN 1 ELSE 0 END) AS Failures
            FROM dbo.MktApiUsage WHERE Provider IN (N'anthropic', N'openai') AND CreatedAt >= :us AND CreatedAt < :ue
        SQL, $p),
        'spend_jobs' => $spendJobRows,
        'budget'     => (float) marketing_setting('ai.monthly_budget_usd', '0'),
        'digests'    => $digestRows,
    ];
}

/* ---------- Snapshots ---------- */

/** Saved report rows keyed by month, without their data. */
function mkt_reports_saved(): array
{
    $rows = db()->query(<<<SQL
        SELECT r.ReportID, r.PeriodMonth, CONVERT(varchar(19), r.FrozenAt, 120) AS FrozenAt, r.FrozenBy,
               CASE WHEN r.Highlights IS NULL THEN 0 ELSE 1 END AS HasHighlights, r.HighlightsError,
               CONVERT(varchar(19), r.ReviewedAt, 120) AS ReviewedAt, r.ReviewNote, u.UserName AS ReviewedByName
        FROM dbo.MktReport r
        LEFT JOIN dbo.[User] u ON u.UserID = r.ReviewedBy
    SQL)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_column($rows, null, 'PeriodMonth');
}

function mkt_report_get(string $ym): ?array
{
    $stmt = db()->prepare(<<<SQL
        SELECT ReportID, PeriodMonth, DataJson, CONVERT(varchar(19), FrozenAt, 120) AS FrozenAt, Highlights,
               CONVERT(varchar(19), HighlightsAt, 120) AS HighlightsAt, HighlightsError, CONVERT(varchar(19), ReviewedAt, 120) AS ReviewedAt
        FROM dbo.MktReport WHERE PeriodMonth = :m
    SQL);
    $stmt->execute(['m' => $ym]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * A closed month freezes from the 2nd of the next month once both analytics sources have data through its
 * last day, or on reports.freeze_after_days of the next month at the latest.
 */
function mkt_report_ready_to_freeze(array $p): bool
{
    if ($p['partial']) {
        return false;
    }
    $today = mkt_local_date();
    $nextMonth = (new DateTimeImmutable($p['start']))->modify('+1 month');
    if ($today < $nextMonth->format('Y-m-02')) {
        return false;
    }
    $latestDay = $nextMonth->modify('+' . (max(1, min(28, (int) marketing_setting('reports.freeze_after_days', '5'))) - 1) . ' days')->format('Y-m-d');
    if ($today >= $latestDay) {
        return true;
    }

    return (string) mkt_analytics_latest_date('MktGscDaily') >= $p['end'] && (string) mkt_analytics_latest_date('MktGa4Daily') >= $p['end'];
}

/** Save (or with $replace, refresh) a closed month's report data. Refreshing clears the highlights so they are rewritten. */
function mkt_report_freeze(array $p, ?int $userId, bool $replace = false): void
{
    $json = json_encode(['period' => $p] + mkt_report_data($p), JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($replace) {
        db()->prepare(<<<SQL
            UPDATE dbo.MktReport SET DataJson = :j, FrozenAt = SYSUTCDATETIME(), FrozenBy = :u, Highlights = NULL, HighlightsAt = NULL,
                   HighlightsModel = NULL, HighlightsPromptVersion = NULL, HighlightsCostUsd = NULL, HighlightsError = NULL, HighlightsLogID = NULL
            WHERE PeriodMonth = :m
        SQL)->execute(['j' => $json, 'u' => $userId, 'm' => $p['ym']]);

        return;
    }
    try {
        db()->prepare('INSERT INTO dbo.MktReport (PeriodMonth, PeriodStart, PeriodEnd, DataJson, FrozenBy) VALUES (:m, :s, :e, :j, :u)')
            ->execute(['m' => $p['ym'], 's' => $p['start'], 'e' => $p['end'], 'j' => $json, 'u' => $userId]);
    } catch (PDOException $e) {
        // Another request froze it first (unique month).
    }
}

/** Freeze every closed month that is ready and not yet saved. Returns the months frozen. */
function mkt_report_freeze_due(): array
{
    $saved = mkt_reports_saved();
    $frozen = [];
    foreach (array_keys(mkt_report_months()) as $ym) {
        if (isset($saved[$ym])) {
            continue;
        }
        $p = mkt_report_period($ym);
        if ($p !== null && mkt_report_ready_to_freeze($p)) {
            mkt_report_freeze($p, null);
            $frozen[] = $ym;
        }
    }

    return $frozen;
}

function mkt_report_mark_reviewed(string $ym, string $note): array
{
    $report = mkt_report_get($ym);
    if ($report === null) {
        return ['ok' => false, 'error' => 'That month is not frozen yet — it can be reviewed once its report is saved.'];
    }
    db()->prepare('UPDATE dbo.MktReport SET ReviewedAt = SYSUTCDATETIME(), ReviewedBy = :u, ReviewNote = :n WHERE ReportID = :id')
        ->execute(['u' => marketing_user_id(), 'n' => trim($note) !== '' ? mb_substr(trim($note), 0, 1000) : null, 'id' => (int) $report['ReportID']]);

    return ['ok' => true];
}

/** One review task for the last closed month, from the 2nd of the next month until the report is marked reviewed. */
function mkt_report_desired_tasks(callable $add): void
{
    mkt_report_freeze_due();
    $ym = (new DateTimeImmutable(substr(mkt_local_date(), 0, 7) . '-01'))->modify('-1 month')->format('Y-m');
    if ($ym < MKT_REPORT_FIRST_MONTH || mkt_local_date() < substr(mkt_local_date(), 0, 7) . '-02') {
        return;
    }
    $report = mkt_report_get($ym);
    if ($report !== null && $report['ReviewedAt'] !== null) {
        return;
    }
    $label = (new DateTimeImmutable($ym . '-01'))->format('F Y');
    $add('report:' . $ym . ':review', [
        'Title' => 'Review the ' . $label . ' marketing report', 'TaskType' => 'report_review', 'AssigneeRole' => 'coordinator',
        'RefType' => 'report', 'RefID' => $report !== null ? (int) $report['ReportID'] : null, 'Href' => '/marketing/reports/?month=' . $ym,
        'DueDate' => mkt_task_due(substr(mkt_local_date(), 0, 7) . '-02 14:00:00', 'report'),
        'Detail' => 'Read the report and its highlights, act on anything that needs it, then press Mark reviewed on the Reports page. '
            . 'The month is frozen once Search Console and Analytics have data through its last day.',
    ]);
}

/**
 * The report for a period: the saved snapshot when there is one, otherwise live data.
 *
 * @return array{data: array, report: ?array}
 */
function mkt_report_load(array $p): array
{
    $report = mkt_report_get($p['ym']);
    $data = $report !== null ? json_decode((string) $report['DataJson'], true) : null;
    if (!is_array($data)) {
        return ['data' => mkt_report_data($p), 'report' => null];
    }
    unset($data['period']);

    return ['data' => $data, 'report' => $report];
}

/** Position change where lower is better: "+2.1" means up the page. */
function mkt_report_position_change(mixed $current, mixed $prior): string
{
    if ($current === null || $prior === null || (float) $current <= 0 || (float) $prior <= 0) {
        return '';
    }
    $delta = round((float) $prior - (float) $current, 1);

    return $delta == 0 ? '±0' : ($delta > 0 ? '+' : '−') . abs($delta);
}

function mkt_report_short_date(string $ymd): string
{
    return (new DateTimeImmutable($ymd))->format('M j');
}

/** The monthly report as a document (see marketing-docs.php). */
function mkt_report_doc(array $p, array $d, ?array $report = null): array
{
    $lit = 'mkt_doc_literal';
    $n = static fn(mixed $v): string => number_format((float) $v);
    $metric = static fn(string $label, float $cur, float $prior, string $hint = ''): array => [$label, number_format($cur), mkt_perf_change($cur, $prior), $hint];
    $range = mkt_report_short_date($p['start']) . '–' . ((new DateTimeImmutable($p['end']))->format(substr($p['start'], 0, 7) === substr($p['end'], 0, 7) ? 'j' : 'M j')) . ', ' . substr($p['end'], 0, 4);

    $blocks = [];
    $lags = [];
    foreach (['gsc_latest' => 'Search Console', 'ga4_latest' => 'Google Analytics'] as $key => $label) {
        if ($d[$key] !== null && $d[$key] < $p['end']) {
            $lags[] = $label . ' data runs through ' . mkt_report_short_date($d[$key]);
        }
    }
    $status = $report !== null
        ? 'Frozen ' . marketing_format_datetime($report['FrozenAt']) . ' — these figures no longer change. '
        : ($p['partial'] ? 'Month to date. Changes compare with the same days of last month (' . $p['prior_label'] . '). ' : 'Not frozen yet — figures can still change while analytics data fills in. ');
    $blocks[] = ['type' => 'note', 'text' => trim($status . ($lags !== [] ? implode('; ', $lags) . ' — analytics data lags a day or more behind.' : ''))];

    if ($report !== null && trim((string) $report['Highlights']) !== '') {
        $blocks[] = ['type' => 'h2', 'text' => 'Highlights'];
        $blocks[] = ['type' => 'markdown', 'text' => (string) $report['Highlights']];
        $blocks[] = ['type' => 'note', 'text' => 'Written by AI from this report\'s numbers on ' . marketing_format_datetime($report['HighlightsAt']) . '. Check against the tables below before sharing.'];
    }

    $gsc = $d['gsc'];
    $gp = $d['gsc_prior'];
    $ga = $d['ga4'];
    $gap = $d['ga4_prior'];
    $blocks[] = ['type' => 'h2', 'text' => 'Summary'];
    $blocks[] = ['type' => 'metrics', 'items' => [
        $metric('Search clicks', (float) $gsc['Clicks'], (float) $gp['Clicks'], 'Google Search Console'),
        $metric('Search impressions', (float) $gsc['Impressions'], (float) $gp['Impressions']),
        ['Average position', $gsc['Position'] !== null && (float) $gsc['Position'] > 0 ? (string) $gsc['Position'] : '—', mkt_report_position_change($gsc['Position'], $gp['Position']), 'lower is better; + means up the page'],
        $metric('Sessions', (float) $ga['Sessions'], (float) $gap['Sessions'], 'Google Analytics'),
        $metric('Engaged sessions', (float) $ga['EngagedSessions'], (float) $gap['EngagedSessions'], mkt_perf_rate((float) $ga['EngagedSessions'], (float) $ga['Sessions']) . ' of sessions'),
        $metric('Key events', (float) $ga['KeyEvents'], (float) $gap['KeyEvents']),
        $metric('Purchases', (float) $ga['Transactions'], (float) $gap['Transactions'], '$' . number_format((float) $ga['Revenue'], 2) . ' revenue'),
    ]];

    $blocks[] = ['type' => 'h2', 'text' => 'Search and traffic'];
    $blocks[] = ['type' => 'h3', 'text' => 'Top search queries'];
    $blocks[] = ['type' => 'table', 'head' => ['Query', 'Clicks', 'Impressions', 'Position', 'vs prior'], 'widths' => [46, 12, 16, 12, 14],
        'empty' => 'No search queries recorded for this period.',
        'rows' => array_map(static fn(array $q): array => [$lit((string) $q['Query']), $n($q['Clicks']), $n($q['Impressions']), (string) ($q['Position'] ?? '—'), mkt_perf_change((float) $q['Clicks'], (float) $q['PriorClicks'])], $d['queries'])];
    $blocks[] = ['type' => 'h3', 'text' => 'Top landing pages'];
    $blocks[] = ['type' => 'table', 'head' => ['Page', 'Sessions', 'Engaged', 'Key events', 'Search clicks'], 'widths' => [48, 13, 13, 13, 13],
        'empty' => 'No landing-page traffic recorded for this period.',
        'rows' => array_map(static fn(array $l): array => [$lit((string) ($l['Path'] ?: $l['LandingPage'])), $n($l['Sessions']), $n($l['EngagedSessions']), $n($l['KeyEvents']), $n($l['SearchClicks'])], $d['landing'])];
    $blocks[] = ['type' => 'h3', 'text' => 'Sessions by channel'];
    $blocks[] = ['type' => 'table', 'head' => ['Channel', 'Sessions', 'Engaged', 'Key events', 'vs prior'], 'widths' => [40, 15, 15, 15, 15],
        'empty' => 'No sessions recorded for this period.',
        'rows' => array_map(static fn(array $c): array => [$lit((string) $c['Channel']), $n($c['Sessions']), $n($c['EngagedSessions']), $n($c['KeyEvents']), mkt_perf_change((float) $c['Sessions'], (float) $c['PriorSessions'])], $d['channels'])];

    $ranked = array_filter($d['ranks'], static fn(array $r): bool => (int) $r['Checked'] === 1 && $r['Position'] !== null);
    $improved = count(array_filter($d['ranks'], static fn(array $r): bool => str_starts_with(mkt_report_position_change($r['Position'], $r['PriorPosition']), '+')));
    $declined = count(array_filter($d['ranks'], static fn(array $r): bool => str_starts_with(mkt_report_position_change($r['Position'], $r['PriorPosition']), '−')));
    $blocks[] = ['type' => 'h2', 'text' => 'Rankings'];
    $blocks[] = ['type' => 'metrics', 'items' => [
        ['Keywords checked', $n(count(array_filter($d['ranks'], static fn(array $r): bool => (int) $r['Checked'] === 1))), '', 'in the Rank Tracker this period'],
        ['Ranking in top 10', $n(count(array_filter($ranked, static fn(array $r): bool => (int) $r['Position'] <= 10))), '', 'latest check in the period'],
        ['Moved up', $n($improved), '', 'vs the last check before'],
        ['Moved down', $n($declined), '', ''],
    ]];
    $blocks[] = ['type' => 'table', 'head' => ['Keyword', 'Position', 'Before', 'Change'], 'widths' => [55, 15, 15, 15],
        'empty' => 'No rank checks in this period. Import results in the Rank Tracker.',
        'rows' => array_map(static fn(array $r): array => [
            $lit((string) $r['Keyword']),
            (int) $r['Checked'] === 1 ? ($r['Position'] !== null ? (string) (int) $r['Position'] : 'Not ranking') : 'Not checked',
            (int) $r['PriorChecked'] === 1 ? ($r['PriorPosition'] !== null ? (string) (int) $r['PriorPosition'] : 'Not ranking') : '—',
            mkt_report_position_change($r['Position'], $r['PriorPosition']),
        ], array_slice($d['ranks'], 0, 25))];

    $l = $d['links'];
    $blocks[] = ['type' => 'h2', 'text' => 'Backlinks and outreach'];
    $blocks[] = ['type' => 'metrics', 'items' => [
        ['New links', $n($l['New']), '', 'first seen this period'],
        ['Lost links', $n($l['Lost']), '', ''],
        ['Live links', $n($l['Live']), '', 'at the end of the period'],
        ['Outreach wins', $n($l['Won']), '', $n($l['Prospects']) . ' prospects added'],
    ]];
    if ($d['new_links'] !== []) {
        $blocks[] = ['type' => 'table', 'head' => ['New linking site', 'Authority', 'Follow'], 'widths' => [60, 20, 20],
            'rows' => array_map(static fn(array $b): array => [$lit((string) $b['SourceDomain']), $b['Authority'] !== null ? (string) (int) $b['Authority'] : '—', (int) $b['Followed'] === 1 ? 'Follow' : 'No-follow'], $d['new_links'])];
    }

    $i = $d['issues'];
    $blocks[] = ['type' => 'h2', 'text' => 'Site health'];
    $blocks[] = ['type' => 'metrics', 'items' => [
        ['Issues found', $n($i['Opened']), '', 'new this period'],
        ['Marked fixed', $n($i['Fixed']), '', ''],
        ['Fix confirmed', $n($i['Verified']), '', 'by a recheck'],
        ['Came back', $n($i['Reopened']), '', ''],
    ]];
    $blocks[] = ['type' => 'p', 'text' => ($report !== null ? '**Open when frozen:** ' : '**Open now:** ') . $n($i['OpenHigh']) . ' high, ' . $n($i['OpenMedium']) . ' medium, ' . $n($i['OpenLow']) . ' low severity.'];

    $c = $d['content'];
    $blocks[] = ['type' => 'h2', 'text' => 'Content and campaigns'];
    $blocks[] = ['type' => 'metrics', 'items' => [
        ['Pieces published', $n($c['Published']), '', 'Content Pipeline'],
        ['Blog posts', $n($c['BlogPosts']), '', 'first published'],
        ['Campaigns generated', $n($c['Campaigns']), '', ''],
        ['Assets posted', $n($c['AssetsPosted']), '', 'marked posted'],
    ]];
    $blocks[] = ['type' => 'h3', 'text' => 'Campaign assets that drove visits'];
    $blocks[] = ['type' => 'table', 'head' => ['Asset', 'Campaign', 'Sessions', 'Engaged', 'Key events'], 'widths' => [34, 30, 12, 12, 12],
        'empty' => 'No sessions from tagged campaign links in this period.',
        'rows' => array_map(static fn(array $a): array => [$lit(trim((string) ($a['Title'] ?: $a['Subject'] ?: ucfirst((string) $a['Channel']) . ' part ' . $a['SequenceNo']))), $lit((string) $a['CampaignName']), $n($a['Sessions']), $n($a['EngagedSessions']), $n($a['KeyEvents'])], $d['assets'])];

    $r = $d['research'];
    $blocks[] = ['type' => 'h2', 'text' => 'Research and evidence'];
    $blocks[] = ['type' => 'metrics', 'items' => [
        ['Items harvested', $n($r['Harvested']), '', 'research feed'],
        ['Topics accepted', $n($r['TopicsAccepted']), '', ''],
        ['Library sources added', $n($r['SourcesAdded']), '', 'Literature & Intelligence'],
        ['Flyer references matched', $n($r['RefsMatched']), '', $n($r['RegulatoryAction']) . ' regulatory items need action'],
    ]];

    $s = $d['responses'];
    $blocks[] = ['type' => 'h2', 'text' => 'Responses'];
    $blocks[] = ['type' => 'p', 'text' => $n($s['Received']) . ' response' . ((int) $s['Received'] === 1 ? '' : 's') . ' logged, ' . $n($s['Escalated']) . ' escalated to compliance, ' . $n($s['Replied']) . ' replied to.'];

    $sp = $d['spend'];
    $blocks[] = ['type' => 'h2', 'text' => 'AI spend'];
    $blocks[] = ['type' => 'kv', 'rows' => array_values(array_filter([
        ['Spend', '$' . number_format((float) $sp['Cost'], 2) . ($d['budget'] > 0 ? ' of a $' . number_format($d['budget'], 0) . ' monthly budget (' . round((float) $sp['Cost'] / $d['budget'] * 100) . '%)' : '')],
        ['AI calls', $n($sp['Calls']) . ((int) $sp['Failures'] > 0 ? ' (' . $n($sp['Failures']) . ' failed)' : '')],
    ]))];
    if ($d['spend_jobs'] !== []) {
        $blocks[] = ['type' => 'table', 'head' => ['Job', 'Calls', 'Cost'], 'widths' => [60, 20, 20],
            'rows' => array_map(static fn(array $j): array => [$lit((string) $j['JobName']), $n($j['Calls']), '$' . number_format((float) $j['CostUsd'], 2)], $d['spend_jobs'])];
    }

    if ($d['digests'] !== []) {
        $blocks[] = ['type' => 'h2', 'text' => 'Weekly digests'];
        $blocks[] = ['type' => 'bullets', 'items' => array_map(static fn(array $g): string => 'Week of ' . mkt_report_short_date($g['PeriodStart']) . '–' . mkt_report_short_date($g['PeriodEnd'])
            . ($g['Status'] === 'sparse' ? ' — not enough data for a full digest' : ' — ' . (int) $g['TaskCount'] . ' recommended task' . ((int) $g['TaskCount'] === 1 ? '' : 's')), $d['digests'])];
    }

    $blocks[] = ['type' => 'h2', 'text' => 'About this report'];
    $blocks[] = ['type' => 'p', 'text' => 'Built from the portal\'s own records: Google Search Console and Google Analytics daily imports, the Rank Tracker, Backlinks, Site Issues, the Content Pipeline, Campaigns, Responses, Literature & Intelligence and AI usage logs. Periods are calendar months in Central time; AI spend follows the UTC month the budget guard uses.'];

    return [
        'title'    => 'Marketing report — ' . $p['label'],
        'subtitle' => $p['partial'] ? 'Month to date' : 'Monthly summary',
        'meta'     => [['Period', $range], ['Compared with', $p['prior_label']], mkt_doc_generated_meta()],
        'notice'   => MKT_DOC_INTERNAL_NOTICE,
        'filename' => 'NutraAxis marketing report ' . $p['ym'],
        'blocks'   => $blocks,
    ];
}
