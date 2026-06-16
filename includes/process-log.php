<?php

const PROCESS_LOG_STATUS_RUNNING = 'Running';
const PROCESS_LOG_STATUS_SUCCESS = 'Success';
const PROCESS_LOG_STATUS_FAILED = 'Failed';
const PROCESS_LOG_STATUS_ABANDONED = 'Abandoned';

const PROCESS_LOG_TRIGGER_SCHEDULED = 'Scheduled';
const PROCESS_LOG_TRIGGER_MANUAL = 'Manual';
const PROCESS_LOG_TRIGGER_RETRY = 'Retry';

const PROCESS_LOG_DEFAULT_MAX_ATTEMPTS = 3;

const PROCESS_LOG_LIST_SORT_COLUMNS = [
    'log_id'      => 'Log ID',
    'process'     => 'Process',
    'started'     => 'Started',
    'finished'    => 'Finished',
    'duration'    => 'Duration',
    'attempts'    => 'Attempts',
    'next_retry'  => 'Next Retry',
    'trigger'     => 'Trigger',
    'status'      => 'Status',
    'result'      => 'Result',
];

const PROCESS_LOG_LIST_SORT_SQL = [
    'log_id'     => 'pel.ProcessExecutionLogID',
    'process'    => 'pel.ProcessName',
    'started'    => 'pel.StartedAt',
    'finished'   => 'pel.FinishedAt',
    'duration'   => 'pel.StartedAt',
    'attempts'   => 'pel.AttemptCount',
    'next_retry' => 'pel.NextRetryAt',
    'trigger'    => 'pel.TriggerType',
    'status'     => 'pel.Status',
    'result'     => 'pel.ResultMessage',
];

const PROCESS_LOG_LIST_SORT_NUMERIC = ['log_id', 'attempts'];

function process_log_now_sql(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function process_log_retry_delay_minutes(int $attemptCount): int
{
    return (int) pow(2, max(1, $attemptCount));
}

function process_log_encode_params(array $params): ?string
{
    if ($params === []) {
        return null;
    }

    return json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function process_log_decode_params(?array $log): array
{
    if ($log === null) {
        return [];
    }

    $raw = trim((string) ($log['ProcessParams'] ?? ''));
    if ($raw !== '') {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            // Fall through to ResultJson.
        }
    }

    $resultJson = trim((string) ($log['ResultJson'] ?? ''));
    if ($resultJson === '') {
        return [];
    }

    try {
        $decoded = json_decode($resultJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            return [];
        }

        $params = [];
        if (!empty($decoded['summary_date'])) {
            $params['date'] = (string) $decoded['summary_date'];
        }

        return $params;
    } catch (Throwable) {
        return [];
    }
}

function process_log_start(
    string $processCode,
    string $processName,
    string $triggerType = PROCESS_LOG_TRIGGER_SCHEDULED,
    ?int $triggeredByUserId = null,
    array $params = [],
    int $maxAttempts = PROCESS_LOG_DEFAULT_MAX_ATTEMPTS
): int
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $startedAt = process_log_now_sql();

    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.ProcessExecutionLog (
            ProcessCode, ProcessName, StartedAt, LastAttemptAt, CreatedAt,
            Status, TriggerType, TriggeredByUserID,
            ProcessParams, AttemptCount, MaxAttempts
        )
        OUTPUT INSERTED.ProcessExecutionLogID
        VALUES (
            :process_code, :process_name, :started_at, :last_attempt_at, :created_at,
            :status, :trigger_type, :triggered_by,
            :process_params, 0, :max_attempts
        )
    SQL);

    $stmt->execute([
        'process_code'    => $processCode,
        'process_name'    => $processName,
        'started_at'      => $startedAt,
        'last_attempt_at' => $startedAt,
        'created_at'      => $startedAt,
        'status'          => PROCESS_LOG_STATUS_RUNNING,
        'trigger_type'    => $triggerType,
        'triggered_by'    => $triggeredByUserId,
        'process_params'  => process_log_encode_params($params),
        'max_attempts'    => max(1, $maxAttempts),
    ]);

    return (int) $stmt->fetchColumn();
}

function process_log_finish(
    int $logId,
    bool $ok,
    ?string $resultMessage = null,
    ?string $errorMessage = null,
    ?array $resultPayload = null
): void
{
    if ($logId <= 0) {
        return;
    }

    if ($ok) {
        process_log_finish_success($logId, $resultMessage, $resultPayload);

        return;
    }

    process_log_finish_failure($logId, $errorMessage ?? $resultMessage ?? 'Process failed.', $resultPayload);
}

function process_log_finish_success(
    int $logId,
    ?string $resultMessage = null,
    ?array $resultPayload = null
): void
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $finishedAt = process_log_now_sql();
    $resultJson = $resultPayload !== null
        ? json_encode($resultPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        : null;

    $pdo->prepare(<<<SQL
        UPDATE dbo.ProcessExecutionLog
        SET
            FinishedAt = :finished_at,
            LastAttemptAt = :last_attempt_at,
            Status = :status,
            ResultMessage = :result_message,
            ErrorMessage = NULL,
            ResultJson = :result_json,
            NextRetryAt = NULL
        WHERE ProcessExecutionLogID = :log_id
    SQL)->execute([
        'finished_at'     => $finishedAt,
        'last_attempt_at' => $finishedAt,
        'status'          => PROCESS_LOG_STATUS_SUCCESS,
        'result_message'  => $resultMessage,
        'result_json'     => $resultJson,
        'log_id'          => $logId,
    ]);
}

function process_log_finish_failure(
    int $logId,
    string $errorMessage,
    ?array $resultPayload = null
): void
{
    $log = process_log_get($logId);
    if ($log === null) {
        return;
    }

    $pdo = db();
    db_apply_sql_server_options($pdo);

    $finishedAt = process_log_now_sql();
    $attemptCount = (int) ($log['AttemptCount'] ?? 0) + 1;
    $maxAttempts = max(1, (int) ($log['MaxAttempts'] ?? PROCESS_LOG_DEFAULT_MAX_ATTEMPTS));
    $resultJson = $resultPayload !== null
        ? json_encode($resultPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        : ($log['ResultJson'] ?? null);

    $status = PROCESS_LOG_STATUS_FAILED;
    $nextRetryAt = null;

    if ($attemptCount <= $maxAttempts) {
        $retryAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . process_log_retry_delay_minutes($attemptCount) . ' minutes');
        $nextRetryAt = $retryAt->format('Y-m-d H:i:s');
    } else {
        $status = PROCESS_LOG_STATUS_ABANDONED;
    }

    $pdo->prepare(<<<SQL
        UPDATE dbo.ProcessExecutionLog
        SET
            FinishedAt = :finished_at,
            LastAttemptAt = :last_attempt_at,
            Status = :status,
            ResultMessage = NULL,
            ErrorMessage = :error_message,
            ResultJson = :result_json,
            AttemptCount = :attempt_count,
            NextRetryAt = :next_retry_at
        WHERE ProcessExecutionLogID = :log_id
    SQL)->execute([
        'finished_at'     => $finishedAt,
        'last_attempt_at' => $finishedAt,
        'status'          => $status,
        'error_message'   => $errorMessage,
        'result_json'     => $resultJson,
        'attempt_count'   => $attemptCount,
        'next_retry_at'   => $nextRetryAt,
        'log_id'          => $logId,
    ]);

    if ($status === PROCESS_LOG_STATUS_ABANDONED) {
        require_once __DIR__ . '/process-alerts.php';
        process_alert_on_abandoned(
            (string) $log['ProcessCode'],
            (string) $log['ProcessName'],
            $errorMessage,
            [
                'log_id'         => $logId,
                'attempt_count'  => $attemptCount,
                'max_attempts'   => $maxAttempts,
            ]
        );
    }
}

function process_log_mark_retry_running(int $logId): bool
{
    if ($logId <= 0) {
        return false;
    }

    $pdo = db();
    db_apply_sql_server_options($pdo);

    $now = process_log_now_sql();
    $stmt = $pdo->prepare(<<<SQL
        UPDATE dbo.ProcessExecutionLog
        SET
            Status = :running,
            LastAttemptAt = :last_attempt_at,
            FinishedAt = NULL,
            ResultMessage = NULL
        WHERE ProcessExecutionLogID = :log_id
          AND Status = :failed
          AND NextRetryAt IS NOT NULL
          AND NextRetryAt <= :now
          AND AttemptCount <= MaxAttempts
    SQL);

    $stmt->execute([
        'running'         => PROCESS_LOG_STATUS_RUNNING,
        'last_attempt_at' => $now,
        'log_id'          => $logId,
        'failed'          => PROCESS_LOG_STATUS_FAILED,
        'now'             => $now,
    ]);

    return $stmt->rowCount() > 0;
}

function process_log_list_retry_candidates(): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $now = process_log_now_sql();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            ProcessExecutionLogID,
            ProcessCode,
            ProcessName,
            ProcessParams,
            AttemptCount,
            MaxAttempts,
            NextRetryAt
        FROM dbo.ProcessExecutionLog
        WHERE Status = :failed
          AND NextRetryAt IS NOT NULL
          AND NextRetryAt <= :now
          AND AttemptCount <= MaxAttempts
        ORDER BY NextRetryAt ASC, ProcessExecutionLogID ASC
    SQL);
    $stmt->execute([
        'failed' => PROCESS_LOG_STATUS_FAILED,
        'now'    => $now,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function process_log_select_columns(): string
{
    return <<<SQL
        pel.ProcessExecutionLogID,
        pel.ProcessCode,
        pel.ProcessName,
        pel.StartedAt,
        pel.FinishedAt,
        pel.LastAttemptAt,
        pel.CreatedAt,
        pel.Status,
        pel.ResultMessage,
        pel.ErrorMessage,
        pel.TriggerType,
        pel.TriggeredByUserID,
        pel.ProcessParams,
        pel.AttemptCount,
        pel.MaxAttempts,
        pel.NextRetryAt,
        pel.ResultJson
    SQL;
}

function process_log_get(int $logId): ?array
{
    if ($logId <= 0) {
        return null;
    }

    $pdo = db();
    db_apply_sql_server_options($pdo);

    $sql = <<<SQL
        SELECT
            {cols},
            u.UserName AS TriggeredByUserName
        FROM dbo.ProcessExecutionLog pel
        LEFT JOIN dbo.[User] u ON u.UserID = pel.TriggeredByUserID
        WHERE pel.ProcessExecutionLogID = :log_id
    SQL;
    $sql = str_replace('{cols}', process_log_select_columns(), $sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['log_id' => $logId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function process_log_list(array $filters = []): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $sql = <<<SQL
        SELECT TOP (:limit)
            {cols},
            u.UserName AS TriggeredByUserName
        FROM dbo.ProcessExecutionLog pel
        LEFT JOIN dbo.[User] u ON u.UserID = pel.TriggeredByUserID
        WHERE 1 = 1
    SQL;
    $sql = str_replace('{cols}', process_log_select_columns(), $sql);

    $params = [
        'limit' => max(1, min(500, (int) ($filters['limit'] ?? 100))),
    ];

    $processCode = trim((string) ($filters['process_code'] ?? ''));
    if ($processCode !== '') {
        $sql .= ' AND pel.ProcessCode = :process_code';
        $params['process_code'] = $processCode;
    }

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '') {
        $sql .= ' AND pel.Status = :status';
        $params['status'] = $status;
    }

    $sortState = table_sort_state(PROCESS_LOG_LIST_SORT_COLUMNS, 'started', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(PROCESS_LOG_LIST_SORT_SQL, $sortState, 'started', 'log_id');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function process_log_status_class(string $status): string
{
    return match ($status) {
        PROCESS_LOG_STATUS_SUCCESS   => 'status-approved',
        PROCESS_LOG_STATUS_FAILED    => 'status-cancelled',
        PROCESS_LOG_STATUS_ABANDONED => 'status-cancelled',
        PROCESS_LOG_STATUS_RUNNING   => 'status-submitted',
        default                      => 'status-draft',
    };
}

function process_log_status_label(string $status): string
{
    return match ($status) {
        PROCESS_LOG_STATUS_SUCCESS   => 'Success',
        PROCESS_LOG_STATUS_FAILED    => 'Failed',
        PROCESS_LOG_STATUS_ABANDONED => 'Abandoned',
        PROCESS_LOG_STATUS_RUNNING   => 'Running',
        default                      => $status,
    };
}

function process_log_format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));

        return $dt->setTimezone(new DateTimeZone('America/Chicago'))->format('M j, Y g:i A T');
    } catch (Throwable) {
        return $value;
    }
}

function process_log_format_datetime_compact(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'))
            ->setTimezone(new DateTimeZone('America/Chicago'));

        return $dt->format('n/j g:ia');
    } catch (Throwable) {
        return $value;
    }
}

function process_log_result_text(array $log): string
{
    if (!empty($log['ResultMessage'])) {
        return trim((string) $log['ResultMessage']);
    }

    if (!empty($log['ErrorMessage'])) {
        return trim((string) $log['ErrorMessage']);
    }

    return '';
}

function process_log_duration_label(?string $startedAt, ?string $finishedAt): string
{
    if ($startedAt === null || $finishedAt === null || trim($startedAt) === '' || trim($finishedAt) === '') {
        return '—';
    }

    try {
        $start = new DateTimeImmutable($startedAt);
        $end = new DateTimeImmutable($finishedAt);
        $seconds = max(0, $end->getTimestamp() - $start->getTimestamp());

        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            return (int) floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        }

        return (int) floor($seconds / 3600) . 'h ' . (int) floor(($seconds % 3600) / 60) . 'm';
    } catch (Throwable) {
        return '—';
    }
}

function process_log_attempt_label(array $log): string
{
    $attempt = (int) ($log['AttemptCount'] ?? 0);
    $max = (int) ($log['MaxAttempts'] ?? PROCESS_LOG_DEFAULT_MAX_ATTEMPTS);

    return $attempt . ' / ' . $max;
}

function process_log_can_rerun(array $log): bool
{
    $status = (string) ($log['Status'] ?? '');

    return in_array($status, [PROCESS_LOG_STATUS_FAILED, PROCESS_LOG_STATUS_ABANDONED], true);
}
