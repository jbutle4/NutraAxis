<?php

require_once __DIR__ . '/process-log.php';
require_once __DIR__ . '/process-alerts.php';
require_once __DIR__ . '/daily-sales-summary.php';
require_once __DIR__ . '/jazz-inventory-snapshot.php';
require_once __DIR__ . '/monthly-sales-summary.php';
require_once __DIR__ . '/forecast-plan.php';

function process_registry(): array
{
    return [
        'daily-sales-summary' => [
            'code'        => 'daily-sales-summary',
            'name'        => 'Daily Sales Summary',
            'description' => 'Summarize previous day ACCS sales by SKU into DailySalesSummary.',
            'cron_path'   => '/cron/daily-sales-summary.php',
        ],
        'jazz-inventory-snapshot' => [
            'code'        => 'jazz-inventory-snapshot',
            'name'        => 'Jazz Inventory Snapshot',
            'description' => 'Capture weekly Jazz OMS inventory levels by SKU and facility.',
            'cron_path'   => '/cron/jazz-inventory-snapshot.php',
        ],
        'monthly-sales-summary' => [
            'code'        => 'monthly-sales-summary',
            'name'        => 'Monthly Sales Summary',
            'description' => 'Roll up DailySalesSummary into monthly SKU totals for forecasting.',
            'cron_path'   => '/cron/monthly-sales-summary.php',
        ],
        'forecast-plan' => [
            'code'        => 'forecast-plan',
            'name'        => 'Inventory Forecast Plan',
            'description' => 'Generate weighted moving average forecasts and inventory projections by SKU.',
            'cron_path'   => '/cron/weekly-demand.php',
        ],
    ];
}

function process_registry_entry(string $code): ?array
{
    $registry = process_registry();

    return $registry[$code] ?? null;
}

function process_build_result_message(string $code, array $result): string
{
    return match ($code) {
        'daily-sales-summary' => sprintf(
            'Summary date %s — %d orders, %d SKU rows inserted.',
            (string) ($result['summary_date'] ?? '—'),
            (int) ($result['orders'] ?? 0),
            (int) ($result['inserted'] ?? 0)
        ),
        'jazz-inventory-snapshot' => sprintf(
            'Snapshot %s — %d rows inserted.',
            (string) ($result['snapshot_at'] ?? '—'),
            (int) ($result['inserted'] ?? 0)
        ),
        'monthly-sales-summary' => sprintf(
            '%d monthly SKU rows refreshed from daily sales.',
            (int) ($result['inserted'] ?? 0)
        ),
        'forecast-plan' => sprintf(
            '%d SKUs — %d forecast month rows written.',
            (int) ($result['skus'] ?? 0),
            (int) ($result['inserted'] ?? 0)
        ),
        default => 'Process completed.',
    };
}

function process_invoke(string $code, array $params = []): array
{
    return match ($code) {
        'daily-sales-summary' => daily_sales_summary_run(
            daily_sales_summary_parse_target_date($params['date'] ?? null)
        ),
        'jazz-inventory-snapshot' => jazz_inventory_snapshot_run(),
        'monthly-sales-summary' => monthly_sales_summary_run(),
        'forecast-plan' => forecast_plan_run(),
        default => [
            'ok'    => false,
            'error' => 'Unknown process code: ' . $code,
        ],
    };
}

function process_execute(
    string $code,
    array $params = [],
    string $triggerType = PROCESS_LOG_TRIGGER_SCHEDULED,
    ?int $triggeredByUserId = null
): array
{
    $entry = process_registry_entry($code);
    if ($entry === null) {
        return [
            'ok'    => false,
            'error' => 'Unknown process code: ' . $code,
            'log_id' => null,
        ];
    }

    $logId = process_log_start(
        $entry['code'],
        $entry['name'],
        $triggerType,
        $triggeredByUserId,
        $params
    );

    try {
        $result = process_invoke($code, $params);
        $ok = !empty($result['ok']);
        $error = trim((string) ($result['error'] ?? ''));
        $message = $ok
            ? process_build_result_message($code, $result)
            : ($error !== '' ? $error : 'Process failed.');

        process_log_finish(
            $logId,
            $ok,
            $ok ? $message : null,
            $ok ? null : $message,
            $result
        );

        return array_merge($result, [
            'log_id'  => $logId,
            'message' => $message,
        ]);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        error_log('process_execute ' . $code . ': ' . $message);

        process_log_finish($logId, false, null, $message);

        return [
            'ok'     => false,
            'error'  => $message,
            'log_id' => $logId,
        ];
    }
}

function process_rerun_failed_log(int $logId, ?int $triggeredByUserId = null): array
{
    $log = process_log_get($logId);
    if ($log === null) {
        return ['ok' => false, 'error' => 'Process log entry not found.'];
    }

    if (!process_log_can_rerun($log)) {
        return ['ok' => false, 'error' => 'Only failed or abandoned process runs can be rerun.'];
    }

    $params = process_log_decode_params($log);

    return process_execute(
        (string) $log['ProcessCode'],
        $params,
        PROCESS_LOG_TRIGGER_MANUAL,
        $triggeredByUserId
    );
}
