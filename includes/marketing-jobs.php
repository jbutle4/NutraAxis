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
            'description'   => 'Reads the site sitemaps and published content into the Page Inventory, then crawls every active page for status, title, meta, headings, canonical, robots and word count, flagging issues and changes.',
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
