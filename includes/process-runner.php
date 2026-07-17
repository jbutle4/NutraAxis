<?php

require_once __DIR__ . '/process-log.php';
require_once __DIR__ . '/process-functions-client.php';

function process_registry(): array
{
    return [
        'daily-sales-summary' => [
            'code'          => 'daily-sales-summary',
            'name'          => 'Daily Sales Summary',
            'description'   => 'Summarize previous day ACCS sales by SKU into DailySalesSummary.',
            'function_name' => 'daily-sales-summary',
            'schedule'      => 'Daily at 2:00 AM US Central',
        ],
        'jazz-inventory-snapshot' => [
            'code'          => 'jazz-inventory-snapshot',
            'name'          => 'Jazz Inventory Snapshot',
            'description'   => 'Capture weekly Jazz OMS inventory levels by SKU and facility.',
            'function_name' => 'jazz-inventory-snapshot',
            'schedule'      => 'Every Sunday at 12:00 PM US Central',
        ],
        'monthly-sales-summary' => [
            'code'          => 'monthly-sales-summary',
            'name'          => 'Monthly Sales Summary',
            'description'   => 'Roll up DailySalesSummary into monthly SKU totals for forecasting.',
            'function_name' => 'weekly-chain',
            'schedule'      => 'Every Sunday at 1:00 AM US Central (via weekly-chain)',
        ],
        'forecast-plan' => [
            'code'          => 'forecast-plan',
            'name'          => 'Inventory Forecast Plan',
            'description'   => 'Generate weighted moving average forecasts and inventory projections by SKU.',
            'function_name' => 'weekly-chain',
            'schedule'      => 'Every Sunday at 1:00 AM US Central (via weekly-chain)',
        ],
        'staging-db-sync' => [
            'code'          => 'staging-db-sync',
            'name'          => 'Staging Database Sync',
            'description'   => 'Incremental production to staging SQL database sync.',
            'function_name' => 'staging-db-sync',
            'schedule'      => 'Daily at 2:30 AM US Central',
        ],
        'accs-sales-order-sync' => [
            'code'          => 'accs-sales-order-sync',
            'name'          => 'ACCS Sales Order Sync',
            'description'   => 'Pull ACCS Magento sales orders into AccsSalesOrder tables for reporting and inventory sales posting.',
            'function_name' => 'accs-sales-order-sync',
            'schedule'      => 'Every 2 hours',
        ],
        'accs-employee-customer-create' => [
            'code'          => 'accs-employee-customer-create',
            'name'          => 'ACCS Stage Employee Customer Create',
            'description'   => 'Create or correct ACCS stage employee customer accounts from portal users.',
            'function_name' => 'accs-employee-customer-create',
            'schedule'      => 'Manual / on demand',
        ],
        'qbo-coa-sync' => [
            'code'          => 'qbo-coa-sync',
            'name'          => 'QuickBooks Chart of Accounts Sync',
            'description'   => 'Sync QuickBooks Online general ledger accounts for Product Catalog account pickers. Not Certificate of Analysis.',
            'function_name' => 'qbo-coa-sync',
            'schedule'      => 'Friday at 6:00 PM US Central',
        ],
        'inventory-receipt-sync' => [
            'code'          => 'inventory-receipt-sync',
            'name'          => 'Inventory Receipt Sync',
            'description'   => 'Post Jazz-received PO receipts to IMS and QBO InventoryAdjustment (+qty).',
            'function_name' => 'inventory-receipt-sync',
            'schedule'      => 'Daily at 2:30 AM US Central',
        ],
        'inventory-sales-sync' => [
            'code'          => 'inventory-sales-sync',
            'name'          => 'Inventory Sales Sync',
            'description'   => 'Post shipped ACCS sales to IMS and QBO InventoryAdjustment (−qty).',
            'function_name' => 'inventory-sales-sync',
            'schedule'      => 'Daily at 3:00 AM US Central',
        ],
    ];
}

function process_registry_entry(string $code): ?array
{
    $registry = process_registry();

    return $registry[$code] ?? null;
}

function process_execute(
    string $code,
    array $params = [],
    string $triggerType = PROCESS_LOG_TRIGGER_SCHEDULED,
    ?int $triggeredByUserId = null
): array {
    if (process_registry_entry($code) === null) {
        return [
            'ok'     => false,
            'error'  => 'Unknown process code: ' . $code,
            'log_id' => null,
        ];
    }

    return process_functions_execute($code, $params, $triggerType, $triggeredByUserId);
}

function process_rerun_failed_log(int $logId, ?int $triggeredByUserId = null): array
{
    $log = process_log_get($logId);
    if ($log === null) {
        return ['ok' => false, 'error' => 'Process log entry not found.', 'log_id' => null];
    }

    if (!process_log_can_rerun($log)) {
        return ['ok' => false, 'error' => 'Only failed or abandoned process runs can be rerun.', 'log_id' => $logId];
    }

    return process_functions_rerun($logId, $triggeredByUserId);
}
