<?php

/**
 * Marketing & Research scheduled jobs (Node, Nutra-forecast-tool-prod) — merged into process_registry().
 */
function marketing_process_registry(): array
{
    $jobs = [
        'seo-noop' => [
            'name'        => 'Marketing Chassis Check',
            'description' => 'Verifies the Marketing & Research tables and settings are reachable from the Function App.',
            'schedule'    => 'Manual / on demand',
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
            'function_app'  => 'prod',
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
