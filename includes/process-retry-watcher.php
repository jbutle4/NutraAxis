<?php

require_once __DIR__ . '/process-log.php';
require_once __DIR__ . '/process-runner.php';

function process_retry_watcher_run(): array
{
    $candidates = process_log_list_retry_candidates();
    $processed = 0;
    $succeeded = 0;
    $failed = 0;
    $skipped = 0;
    $details = [];

    foreach ($candidates as $candidate) {
        $logId = (int) ($candidate['ProcessExecutionLogID'] ?? 0);
        $code = trim((string) ($candidate['ProcessCode'] ?? ''));

        if ($logId <= 0 || $code === '') {
            $skipped++;
            continue;
        }

        if (!process_log_mark_retry_running($logId)) {
            $skipped++;
            continue;
        }

        $processed++;
        $params = process_log_decode_params($candidate);

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

            if ($ok) {
                $succeeded++;
            } else {
                $failed++;
            }

            $details[] = [
                'log_id'  => $logId,
                'code'    => $code,
                'ok'      => $ok,
                'message' => $message,
            ];
        } catch (Throwable $e) {
            $message = $e->getMessage();
            error_log('process_retry_watcher_run log ' . $logId . ': ' . $message);

            process_log_finish($logId, false, null, $message);
            $failed++;
            $details[] = [
                'log_id'  => $logId,
                'code'    => $code,
                'ok'      => false,
                'message' => $message,
            ];
        }
    }

    return [
        'ok'        => true,
        'error'     => null,
        'found'     => count($candidates),
        'processed' => $processed,
        'succeeded' => $succeeded,
        'failed'    => $failed,
        'skipped'   => $skipped,
        'details'   => $details,
    ];
}
