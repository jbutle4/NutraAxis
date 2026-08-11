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
            'uat_e2e'       => false,
            'function_app'  => 'prod',
        ],
        'jazz-inventory-snapshot' => [
            'code'          => 'jazz-inventory-snapshot',
            'name'          => 'Jazz Inventory Snapshot',
            'description'   => 'Capture weekly Jazz OMS inventory levels by SKU and facility.',
            'function_name' => 'jazz-inventory-snapshot',
            'schedule'      => 'Every Sunday at 12:00 PM US Central',
            'uat_e2e'       => false,
            'function_app'  => 'uat',
        ],
        'monthly-sales-summary' => [
            'code'          => 'monthly-sales-summary',
            'name'          => 'Monthly Sales Summary',
            'description'   => 'Roll up DailySalesSummary into monthly SKU totals for forecasting.',
            'function_name' => 'weekly-chain',
            'schedule'      => 'Every Sunday at 1:00 AM US Central (via weekly-chain)',
            'uat_e2e'       => false,
            'function_app'  => 'prod',
        ],
        'forecast-plan' => [
            'code'          => 'forecast-plan',
            'name'          => 'Inventory Forecast Plan',
            'description'   => 'Generate weighted moving average forecasts and inventory projections by SKU.',
            'function_name' => 'weekly-chain',
            'schedule'      => 'Every Sunday at 1:00 AM US Central (via weekly-chain)',
            'uat_e2e'       => false,
            'function_app'  => 'prod',
        ],
        'staging-db-sync' => [
            'code'          => 'staging-db-sync',
            'name'          => 'Staging Database Sync',
            'description'   => 'Incremental production to staging SQL database sync.',
            'function_name' => 'staging-db-sync',
            'schedule'      => 'Daily at 2:30 AM US Central',
            'uat_e2e'       => false,
            'function_app'  => 'prod',
        ],
        'accs-sales-order-sync' => [
            'code'          => 'accs-sales-order-sync',
            'name'          => 'ACCS Sales Order Sync',
            'description'   => 'Pull Adobe Commerce orders into AccsSalesOrder tables. UAT: ACCS Stage. Production: live ACCS.',
            'function_name' => 'accs-sales-order-sync',
            'schedule'      => 'Every 2 hours (production Function App timer)',
            'uat_e2e'       => true,
            'uat_step'      => 7,
            'function_app'  => 'profile',
        ],
        'accs-employee-customer-create' => [
            'code'          => 'accs-employee-customer-create',
            'name'          => 'ACCS Employee Customer Create',
            'description'   => 'Create or correct ACCS employee customer accounts from portal users.',
            'function_name' => 'accs-employee-customer-create',
            'schedule'      => 'Manual / on demand',
            'uat_e2e'       => false,
            'function_app'  => 'profile',
        ],
        'qbo-coa-sync' => [
            'code'          => 'qbo-coa-sync',
            'name'          => 'QuickBooks Chart of Accounts Sync',
            'description'   => 'Sync QuickBooks Online general ledger accounts for Product Catalog account pickers. Not Certificate of Analysis.',
            'function_name' => 'qbo-coa-sync',
            'schedule'      => 'Friday at 6:00 PM US Central',
            'uat_e2e'       => true,
            'uat_step'      => 0,
            'function_app'  => 'uat',
        ],
        'inventory-receipt-sync' => [
            'code'          => 'inventory-receipt-sync',
            'name'          => 'Inventory Receipt Sync',
            'description'   => 'Post received PO receipts to IMS and QBO InventoryAdjustment (+qty).',
            'function_name' => 'inventory-receipt-sync',
            'schedule'      => 'Daily at 2:30 AM US Central',
            'uat_e2e'       => true,
            'uat_step'      => 6,
            'function_app'  => 'uat',
        ],
        'inventory-sales-sync' => [
            'code'          => 'inventory-sales-sync',
            'name'          => 'Inventory Sales Sync',
            'description'   => 'Post shipped ACCS sales to IMS and QBO InventoryAdjustment (−qty).',
            'function_name' => 'inventory-sales-sync',
            'schedule'      => 'Daily at 3:00 AM US Central',
            'uat_e2e'       => true,
            'uat_step'      => 9,
            'function_app'  => 'uat',
        ],
        'inventory-movement-recon' => [
            'code'          => 'inventory-movement-recon',
            'name'          => 'Inventory Movement Completeness Recon',
            'description'   => 'Scan receipts, sales, transfers, and adjustments for missing IMS/QBO posts.',
            'function_name' => 'inventory-movement-recon',
            'schedule'      => 'Daily at 4:00 AM US Central',
            'uat_e2e'       => true,
            'uat_step'      => 10,
            'function_app'  => 'uat',
        ],
        'supplier-payment-pull' => [
            'code'          => 'supplier-payment-pull',
            'name'          => 'Supplier Bill Payment Pull',
            'description'   => 'Pull QuickBooks Sandbox bill payment status into Operations after manual QBO payment.',
            'function_name' => null,
            'schedule'      => 'Manual / on demand (UAT)',
            'uat_e2e'       => true,
            'uat_step'      => 11,
            'function_app'  => 'portal',
            'runner'        => 'php',
        ],
    ];
}

function process_registry_uat_e2e(): array
{
    $entries = array_values(array_filter(
        process_registry(),
        static fn(array $entry): bool => !empty($entry['uat_e2e'])
    ));

    usort($entries, static function (array $a, array $b): int {
        return ((int) ($a['uat_step'] ?? 99)) <=> ((int) ($b['uat_step'] ?? 99));
    });

    return $entries;
}

function process_registry_function_app_label(array $entry): string
{
    $mode = (string) ($entry['function_app'] ?? 'uat');

    if ($mode === 'profile') {
        return function_exists('data_profile_is_uat') && data_profile_is_uat()
            ? process_functions_uat_app_label() . ' (ACCS Stage)'
            : process_functions_prod_app_label() . ' (ACCS Production)';
    }

    if ($mode === 'prod') {
        return process_functions_prod_app_label();
    }

    if ($mode === 'portal') {
        return 'Operations portal';
    }

    return process_functions_uat_app_label() . ' (UAT / Sandbox)';
}

function process_registry_resolved_app_label(array $entry): string
{
    return process_functions_target_label((string) ($entry['code'] ?? ''));
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
    $entry = process_registry_entry($code);
    if ($entry === null) {
        return [
            'ok'     => false,
            'error'  => 'Unknown process code: ' . $code,
            'log_id' => null,
        ];
    }

    if (($entry['runner'] ?? '') === 'php') {
        return process_execute_php($code, $entry, $params, $triggerType, $triggeredByUserId);
    }

    return process_functions_execute($code, $params, $triggerType, $triggeredByUserId);
}

function process_execute_php(
    string $code,
    array $entry,
    array $params,
    string $triggerType,
    ?int $triggeredByUserId
): array {
    $logId = process_log_start(
        $code,
        (string) ($entry['name'] ?? $code),
        $triggerType,
        $triggeredByUserId,
        $params
    );

    try {
        if ($code === 'supplier-payment-pull') {
            require_once __DIR__ . '/qbo-reconcile.php';
            require_once __DIR__ . '/procurement-ledger.php';

            procurement_bind_ledger_profile(PO_LEDGER_PROFILE_UAT);
            $result = qbo_reconcile_bills(PO_LEDGER_PROFILE_UAT);
            $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
            $errors = (int) ($summary['errors'] ?? 0);
            $payments = (int) ($summary['payments_synced'] ?? 0);
            $poPaid = (int) ($summary['po_marked_paid'] ?? 0);
            $message = $errors === 0
                ? trim("Bill payment pull completed. Payments synced: {$payments}. POs marked paid: {$poPaid}.")
                : 'Bill payment pull completed with errors.';

            process_log_finish($logId, $errors === 0, $message, $errors > 0 ? $message : null, [
                'summary' => $summary,
                'rows'    => array_slice($result['rows'] ?? [], 0, 50),
            ]);

            return [
                'ok'     => $errors === 0,
                'error'  => $errors > 0 ? $message : null,
                'log_id' => $logId,
            ];
        }

        $error = 'PHP process runner is not configured for: ' . $code;
        process_log_finish($logId, false, null, $error);

        return ['ok' => false, 'error' => $error, 'log_id' => $logId];
    } catch (Throwable $e) {
        $error = $e->getMessage();
        process_log_finish($logId, false, null, $error);

        return ['ok' => false, 'error' => $error, 'log_id' => $logId];
    }
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
