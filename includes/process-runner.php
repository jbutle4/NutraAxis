<?php

require_once __DIR__ . '/process-log.php';
require_once __DIR__ . '/process-functions-client.php';

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

    return process_functions_rerun($logId, $triggeredByUserId, (string) ($log['ProcessCode'] ?? ''));
}
