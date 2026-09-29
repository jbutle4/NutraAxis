<?php

/**
 * Marketing & Research scheduled jobs (Node, nutraaxis-marketing-func) — merged into process_registry().
 */
function marketing_process_registry(): array
{
    $jobs = [
        'seo-noop' => [
            'name'        => 'Marketing Chassis Check',
            'description' => 'Verifies the Marketing & Research tables and settings are reachable from the Function App.',
            'schedule'    => 'Manual / on demand',
        ],
        'research-harvest-due' => [
            'name'          => 'Content Harvester — Due Sources',
            'description'   => 'Fetches every active source whose next run is due (RSS, news/PubMed/trials searches, site crawls), dedupes, and queues new items.',
            'function_name' => 'marketing-harvest',
            'schedule'      => 'Hourly at :15',
        ],
        'research-agent-discover' => [
            'name'          => 'AI Research Agent — Weekly Discovery',
            'description'   => 'For each agent-enabled interest, asks the AI (web search) for recent evidence, verifies every cited URL, and queues verified finds.',
            'function_name' => 'marketing-research-agent',
            'schedule'      => 'Daily 06:00 CT (each interest at most weekly)',
        ],
        'research-score-batch' => [
            'name'          => 'Topic Synthesis — Score Items (Batch)',
            'description'   => 'Collects finished AI scoring batches (relevance per interest, tags, study facts) and submits waiting items as a new half-price batch.',
            'function_name' => 'marketing-score',
            'schedule'      => 'Hourly at :45 (submits at 25 waiting items or after 12h)',
        ],
        'research-cluster-topics' => [
            'name'          => 'Topic Synthesis — Cluster Topics',
            'description'   => 'Groups recent scored items into Topic Board candidates, extends open topics, and suggests emerging interests.',
            'function_name' => 'marketing-cluster',
            'schedule'      => 'Daily 07:30 CT (when 8+ items are newly scored)',
        ],
        'campaign-generate' => [
            'name'        => 'Campaign Studio — Generate Campaign',
            'description' => 'Writes every asset of a campaign from its accepted topic, angle, evidence and linked claims, then claims-checks each asset.',
            'schedule'    => 'On demand from Campaign Studio',
            'on_demand'   => true,
        ],
        'campaign-claims-check' => [
            'name'        => 'Campaign Studio — Claims Check',
            'description' => 'Checks assets against the approved Claims Matrix wording, flag terms and compliance rules; the score gates submission.',
            'schedule'    => 'On demand from Campaign Studio',
            'on_demand'   => true,
        ],
        'campaign-revise-asset' => [
            'name'        => 'Campaign Studio — AI Revise Asset',
            'description' => 'Revises one draft asset from reviewer notes and claims-check findings, then re-checks it.',
            'schedule'    => 'On demand from Campaign Studio',
            'on_demand'   => true,
        ],
        'content-brief' => [
            'name'        => 'Content Pipeline — AI Brief',
            'description' => 'Writes a content brief (intent, outline, evidence to cite, approved claims, meta) from the topic, keyword, product and evidence.',
            'schedule'    => 'On demand from the Content Pipeline',
            'on_demand'   => true,
        ],
        'content-draft' => [
            'name'        => 'Content Pipeline — AI Draft',
            'description' => 'Drafts the long-form piece from its approved brief as a new version, then claims-checks it.',
            'schedule'    => 'On demand from the Content Pipeline',
            'on_demand'   => true,
        ],
        'content-claims-check' => [
            'name'        => 'Content Pipeline — Claims Check',
            'description' => 'Checks the current version against the approved Claims Matrix wording, flag terms, compliance rules and meta limits.',
            'schedule'    => 'On demand from the Content Pipeline',
            'on_demand'   => true,
        ],
        'content-revise' => [
            'name'        => 'Content Pipeline — AI Revise',
            'description' => 'Revises the current version from an instruction, reviewer notes and claims-check findings as a new version, then re-checks it.',
            'schedule'    => 'On demand from the Content Pipeline',
            'on_demand'   => true,
        ],
        'seo-crawl' => [
            'name'          => 'Page Inventory — Crawl Site',
            'description'   => 'Reads the site sitemaps and published content into the Page Inventory, then crawls every active page for status, title, meta, headings, canonical, robots, word count, image alt text, structured data and legacy brand names, and records the findings as an audit in Audit & Issues.',
            'function_name' => 'marketing-crawl',
            'schedule'      => 'Weekly, Monday 05:00 CT (and on demand)',
        ],
        'seo-verify-published' => [
            'name'          => 'Page Inventory — Verify Published Content',
            'description'   => 'Checks each newly published Content Pipeline URL is live, links it to its page, and records the result on the piece.',
            'function_name' => 'marketing-verify-published',
            'schedule'      => 'Daily 05:20 CT (and on publish)',
        ],
        'seo-gsc-ingest' => [
            'name'          => 'Search Console — Nightly Ingest',
            'description'   => 'Loads Search Console clicks, impressions and position by day for the site, each page, and each page × query (nutraaxislabs.com only).',
            'function_name' => 'marketing-gsc',
            'schedule'      => 'Daily 04:30 CT',
        ],
        'seo-ga4-ingest' => [
            'name'          => 'GA4 — Nightly Ingest',
            'description'   => 'Loads GA4 sessions, users, engagement and key events by day per channel, landing page and UTM tag, and matches tagged visits to Campaign Studio assets.',
            'function_name' => 'marketing-ga4',
            'schedule'      => 'Daily 04:45 CT',
        ],
        'engagement-score' => [
            'name'          => 'Engagement — Nightly Scores',
            'description'   => 'Scores each posted asset and published piece from GA4 visits, entered post and email metrics, and responses; rolls scores up to campaigns, topics and interests, then adjusts interest relevance weights and suggested priorities.',
            'function_name' => 'marketing-engagement-score',
            'schedule'      => 'Daily 05:40 CT (and on demand)',
        ],
        'engagement-triage' => [
            'name'          => 'Response Inbox — Triage',
            'description'   => 'Labels new responses with AI plus keyword rules (redacted text only). Claims-risk and possible adverse events open a 24-hour compliance task.',
            'function_name' => 'marketing-engagement-triage',
            'schedule'      => 'Every 6 hours (and when a response is added)',
        ],
        'engagement-digest' => [
            'name'          => 'Engagement — Monday Digest',
            'description'   => 'Summarizes last week and opens up to 5 recommended tasks, each tied to scored assets or other facts.',
            'function_name' => 'marketing-digest',
            'schedule'      => 'Weekly, Monday 07:00 CT (and on demand)',
        ],
        'seo-alerts' => [
            'name'          => 'Marketing Alerts — Daily Check',
            'description'   => 'Checks the alert rules (failed scheduled jobs, traffic drops against a 4-week baseline, legacy brand names, high-severity site issues, overdue compliance reviews), resolves cleared alerts and emails new ones.',
            'function_name' => 'marketing-alerts',
            'schedule'      => 'Daily 06:00 CT (and on demand)',
        ],
        'seo-openrush-import' => [
            'name'        => 'Site Issues — Import OpenRush Audit',
            'description' => 'Records a pasted OpenRush audit_site result as an audit (nutraaxislabs.com only, active inventory pages only).',
            'schedule'    => 'On demand from Audit & Issues',
            'on_demand'   => true,
        ],
        'seo-issue-verify' => [
            'name'        => 'Site Issues — Verify Fix',
            'description' => 'Recrawls the open pages of one issue and marks it verified, or back to open if the problem is still there.',
            'schedule'    => 'On demand when an issue is marked fixed',
            'on_demand'   => true,
        ],
        'seo-fix-spec' => [
            'name'        => 'Site Issues — AI Fix Spec',
            'description' => 'Writes a developer-ready fix spec for one issue from its open pages and what the crawler saw.',
            'schedule'    => 'On demand from an issue',
            'on_demand'   => true,
        ],
    ];

    $registry = [];
    foreach ($jobs as $code => $job) {
        $registry[$code] = [
            'code'          => $code,
            'name'          => $job['name'],
            'category'      => 'marketing',
            'description'   => $job['description'],
            'function_name' => $job['function_name'] ?? null,
            'schedule'      => $job['schedule'],
            'uat_e2e'       => false,
            'function_app'  => 'marketing',
            'on_demand'     => !empty($job['on_demand']),
        ];
    }

    return $registry;
}

function marketing_job_codes(): array
{
    return array_keys(marketing_process_registry());
}

function marketing_jobs_recent(int $limit = 50, ?string $code = null): array
{
    $codes = marketing_job_codes();
    if ($codes === []) {
        return [];
    }

    $limit = max(1, min(500, $limit));
    $params = [];
    $placeholders = [];
    foreach ($codes as $i => $jobCode) {
        $placeholders[] = ':c' . $i;
        $params['c' . $i] = $jobCode;
    }

    $sql = "SELECT TOP ({$limit}) pel.ProcessExecutionLogID, pel.ProcessCode, pel.ProcessName, pel.StartedAt, pel.FinishedAt,
                   pel.Status, pel.ResultMessage, pel.ErrorMessage, pel.TriggerType, u.UserName AS TriggeredByUserName
            FROM dbo.ProcessExecutionLog pel
            LEFT JOIN dbo.[User] u ON u.UserID = pel.TriggeredByUserID
            WHERE pel.ProcessCode IN (" . implode(', ', $placeholders) . ')';
    if ($code !== null && $code !== '') {
        $sql .= ' AND pel.ProcessCode = :code';
        $params['code'] = $code;
    }
    $sql .= ' ORDER BY pel.ProcessExecutionLogID DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Last run per job code.
 */
function marketing_jobs_last_runs(): array
{
    $last = [];
    foreach (marketing_jobs_recent(300) as $row) {
        $code = (string) $row['ProcessCode'];
        if (!isset($last[$code])) {
            $last[$code] = $row;
        }
    }

    return $last;
}

function marketing_usage_month_summary(): array
{
    $stmt = db()->query(<<<SQL
        SELECT Provider, Operation, Mode,
               COUNT(*) AS Calls,
               SUM(CASE WHEN Ok = 0 THEN 1 ELSE 0 END) AS Failures,
               COALESCE(SUM(InputTokens), 0) AS InputTokens,
               COALESCE(SUM(OutputTokens), 0) AS OutputTokens,
               COALESCE(SUM(Units), 0) AS Units,
               COALESCE(SUM(CostUsd), 0) AS CostUsd
        FROM dbo.MktApiUsage
        WHERE CreatedAt >= DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1)
        GROUP BY Provider, Operation, Mode
        ORDER BY SUM(CostUsd) DESC, COUNT(*) DESC
    SQL);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function marketing_usage_recent(int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $stmt = db()->query(<<<SQL
        SELECT TOP ({$limit}) UsageID, CreatedAt, Provider, Operation, Mode, PromptKey, PromptVersion, Model,
               InputTokens, OutputTokens, Units, CostUsd, Ok, ErrorMessage, ProcessLogID
        FROM dbo.MktApiUsage
        ORDER BY UsageID DESC
    SQL);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function marketing_ai_cost_month_to_date(): float
{
    $value = db()->query(<<<SQL
        SELECT COALESCE(SUM(CostUsd), 0)
        FROM dbo.MktApiUsage
        WHERE Provider IN (N'anthropic', N'openai')
          AND CreatedAt >= DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1)
    SQL)->fetchColumn();

    return (float) $value;
}

/**
 * Budget position for the current UTC month (the same window the job budget guard uses).
 * Forecast = month-to-date + trailing 7-day daily rate × days remaining.
 */
function marketing_spend_overview(): array
{
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $daysInMonth = (int) $now->format('t');
    $elapsedDays = ((int) $now->format('j') - 1) + ((int) $now->format('G') * 3600 + (int) $now->format('i') * 60) / 86400;
    $row = db()->query(<<<SQL
        SELECT
            COALESCE(SUM(CASE WHEN CreatedAt >= DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1) THEN CostUsd END), 0) AS MonthCost,
            COALESCE(SUM(CASE WHEN CreatedAt < DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1) THEN CostUsd END), 0) AS PriorMonthCost,
            COALESCE(SUM(CASE WHEN CreatedAt >= CAST(SYSUTCDATETIME() AS date) THEN CostUsd END), 0) AS TodayCost,
            COALESCE(SUM(CASE WHEN CreatedAt >= DATEADD(day, -7, SYSUTCDATETIME()) THEN CostUsd END), 0) AS Last7Cost,
            SUM(CASE WHEN CreatedAt >= DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1) THEN 1 ELSE 0 END) AS MonthCalls,
            SUM(CASE WHEN CreatedAt >= DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1) AND Ok = 0 THEN 1 ELSE 0 END) AS MonthFailures
        FROM dbo.MktApiUsage
        WHERE Provider IN (N'anthropic', N'openai')
          AND CreatedAt >= DATEADD(month, -1, DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1))
    SQL)->fetch(PDO::FETCH_ASSOC) ?: [];

    $monthCost = (float) ($row['MonthCost'] ?? 0);
    $budget = (float) marketing_setting('ai.monthly_budget_usd', '0');
    $dailyRate = (float) ($row['Last7Cost'] ?? 0) / 7;
    $forecast = $monthCost + $dailyRate * max(0.0, $daysInMonth - $elapsedDays);

    return [
        'month_cost'       => $monthCost,
        'prior_month_cost' => (float) ($row['PriorMonthCost'] ?? 0),
        'today_cost'       => (float) ($row['TodayCost'] ?? 0),
        'last7_cost'       => (float) ($row['Last7Cost'] ?? 0),
        'month_calls'      => (int) ($row['MonthCalls'] ?? 0),
        'month_failures'   => (int) ($row['MonthFailures'] ?? 0),
        'budget'           => $budget,
        'forecast'         => $forecast,
        'daily_rate'       => $dailyRate,
        'days_in_month'    => $daysInMonth,
        'elapsed_days'     => $elapsedDays,
        'pct_used'         => $budget > 0 ? $monthCost / $budget * 100 : null,
        'pct_forecast'     => $budget > 0 ? $forecast / $budget * 100 : null,
        'days_to_budget'   => ($budget > 0 && $dailyRate > 0 && $monthCost < $budget) ? ($budget - $monthCost) / $dailyRate : null,
    ];
}

/**
 * AI spend per UTC day for the last N days, including zero days.
 */
function marketing_spend_daily(int $days = 30): array
{
    $days = max(1, min(90, $days));
    $stmt = db()->prepare(<<<SQL
        SELECT CONVERT(char(10), CAST(CreatedAt AS date), 23) AS UsageDate,
               COUNT(*) AS Calls,
               SUM(CASE WHEN Ok = 0 THEN 1 ELSE 0 END) AS Failures,
               COALESCE(SUM(CostUsd), 0) AS CostUsd
        FROM dbo.MktApiUsage
        WHERE Provider IN (N'anthropic', N'openai')
          AND CreatedAt >= DATEADD(day, -(:days - 1), CAST(SYSUTCDATETIME() AS date))
        GROUP BY CAST(CreatedAt AS date)
    SQL);
    $stmt->execute(['days' => $days]);
    $byDate = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $byDate[(string) $row['UsageDate']] = $row;
    }

    $out = [];
    $day = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    for ($i = 0; $i < $days; $i++) {
        $key = $day->format('Y-m-d');
        $row = $byDate[$key] ?? null;
        $out[] = [
            'date'     => $key,
            'calls'    => (int) ($row['Calls'] ?? 0),
            'failures' => (int) ($row['Failures'] ?? 0),
            'cost'     => (float) ($row['CostUsd'] ?? 0),
        ];
        $day = $day->modify('-1 day');
    }

    return $out;
}

/**
 * AI spend per UTC month for the last N months (newest first).
 */
function marketing_spend_monthly(int $months = 6): array
{
    $months = max(1, min(24, $months));
    $stmt = db()->prepare(<<<SQL
        SELECT YEAR(CreatedAt) AS Y, MONTH(CreatedAt) AS M,
               COUNT(*) AS Calls,
               SUM(CASE WHEN Ok = 0 THEN 1 ELSE 0 END) AS Failures,
               COALESCE(SUM(CostUsd), 0) AS CostUsd
        FROM dbo.MktApiUsage
        WHERE Provider IN (N'anthropic', N'openai')
          AND CreatedAt >= DATEADD(month, -(:months - 1), DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1))
        GROUP BY YEAR(CreatedAt), MONTH(CreatedAt)
    SQL);
    $stmt->execute(['months' => $months]);
    $byMonth = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $byMonth[sprintf('%04d-%02d', (int) $row['Y'], (int) $row['M'])] = $row;
    }

    $out = [];
    $month = new DateTimeImmutable('first day of this month', new DateTimeZone('UTC'));
    for ($i = 0; $i < $months; $i++) {
        $key = $month->format('Y-m');
        $row = $byMonth[$key] ?? null;
        $out[] = [
            'month'    => $key,
            'label'    => $month->format('M Y'),
            'calls'    => (int) ($row['Calls'] ?? 0),
            'failures' => (int) ($row['Failures'] ?? 0),
            'cost'     => (float) ($row['CostUsd'] ?? 0),
        ];
        $month = $month->modify('-1 month');
    }

    return $out;
}

/**
 * Month-to-date AI spend grouped by the job that made the calls (portal actions have no job).
 */
function marketing_spend_by_job(): array
{
    $stmt = db()->query(<<<SQL
        SELECT COALESCE(l.ProcessName, l.ProcessCode, N'Portal action') AS JobName,
               COUNT(*) AS Calls,
               SUM(CASE WHEN u.Ok = 0 THEN 1 ELSE 0 END) AS Failures,
               COUNT(DISTINCT u.ProcessLogID) AS Runs,
               COALESCE(SUM(u.CostUsd), 0) AS CostUsd
        FROM dbo.MktApiUsage u
        LEFT JOIN dbo.ProcessExecutionLog l ON l.ProcessExecutionLogID = u.ProcessLogID
        WHERE u.Provider IN (N'anthropic', N'openai')
          AND u.CreatedAt >= DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1)
        GROUP BY COALESCE(l.ProcessName, l.ProcessCode, N'Portal action')
        ORDER BY SUM(u.CostUsd) DESC, COUNT(*) DESC
    SQL);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Month-to-date AI spend grouped by prompt and model.
 */
function marketing_spend_by_prompt(): array
{
    $stmt = db()->query(<<<SQL
        SELECT COALESCE(PromptKey, N'(none)') AS PromptKey, COALESCE(Model, N'') AS Model, Provider,
               COUNT(*) AS Calls,
               SUM(CASE WHEN Ok = 0 THEN 1 ELSE 0 END) AS Failures,
               COALESCE(SUM(InputTokens), 0) AS InputTokens,
               COALESCE(SUM(OutputTokens), 0) AS OutputTokens,
               COALESCE(SUM(CostUsd), 0) AS CostUsd
        FROM dbo.MktApiUsage
        WHERE Provider IN (N'anthropic', N'openai')
          AND CreatedAt >= DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1)
        GROUP BY COALESCE(PromptKey, N'(none)'), COALESCE(Model, N''), Provider
        ORDER BY SUM(CostUsd) DESC, COUNT(*) DESC
    SQL);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
