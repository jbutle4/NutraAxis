<?php

require_once __DIR__ . '/auth.php';

const ENHANCEMENT_LOG_PERMISSION_COLUMN = 'OperationsDashboard';

const ENHANCEMENT_LOG_STATUSES = [
    'New',
    'Review',
    'InProgress',
    'OnHold',
    'Complete',
    'Canceled',
];

const ENHANCEMENT_LOG_TYPES = [
    'Enhancement',
    'Bug',
    'UI',
    'New Feature',
];

const ENHANCEMENT_LOG_IT_PRODUCTS = [
    'ACCS',
    'QBO',
    'Operations Portal',
    'Integration or Automation',
    'Other - add in description',
];

const ENHANCEMENT_LOG_PRIORITIES = [
    'High',
    'Medium',
    'Low',
];

const ENHANCEMENT_LOG_IMPACTS = [
    'Critical',
    'High',
    'Medium',
    'Low',
];

const ENHANCEMENT_LOG_LIST_SORT_COLUMNS = [
    'id'           => 'ID',
    'title'        => 'Title',
    'enh_type'     => 'Type',
    'it_product'   => 'IT Product',
    'priority'     => 'Priority',
    'impact'       => 'Impact',
    'requested_by' => 'Requested By',
    'request_date' => 'Request Date',
    'status'       => 'Status',
    'due_date'     => 'Due Date',
];

const ENHANCEMENT_LOG_LIST_SORT_SQL = [
    'id'           => 'EnhancementLogID',
    'title'        => 'EnhancementTitle',
    'enh_type'     => 'EnhType',
    'it_product'   => 'ITProduct',
    'priority'     => 'Priority',
    'impact'       => 'Impact',
    'requested_by' => 'RequestedBy',
    'request_date' => 'RequestDate',
    'status'       => 'RequestStatus',
    'due_date'     => 'ReqDueDate',
];

function enhancement_log_permission_value(): ?string
{
    return auth_permission_value(ENHANCEMENT_LOG_PERMISSION_COLUMN);
}

function enhancement_log_can_read(): bool
{
    return auth_can_read(ENHANCEMENT_LOG_PERMISSION_COLUMN);
}

function enhancement_log_can_create(): bool
{
    return auth_can_create(ENHANCEMENT_LOG_PERMISSION_COLUMN);
}

function enhancement_log_can_update(): bool
{
    return auth_can_update(ENHANCEMENT_LOG_PERMISSION_COLUMN);
}

function enhancement_log_require_read(): void
{
    auth_require_module_read('enhancement-log');
}

function enhancement_log_require_create(): void
{
    enhancement_log_require_read();
    if (enhancement_log_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create backlog items.');
}

function enhancement_log_require_update(): void
{
    enhancement_log_require_read();
    if (enhancement_log_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update backlog items.');
}

function enhancement_log_status_label(string $status): string
{
    return match ($status) {
        'New'        => 'New',
        'Review'     => 'Review',
        'InProgress' => 'In Progress',
        'OnHold'     => 'On Hold',
        'Complete'   => 'Complete',
        'Canceled'   => 'Canceled',
        default      => $status,
    };
}

function enhancement_log_status_class(string $status): string
{
    return match ($status) {
        'New'        => 'status-draft',
        'Review'     => 'status-submitted',
        'InProgress' => 'status-submitted',
        'OnHold'     => 'status-cancelled',
        'Complete'   => 'status-approved',
        'Canceled'   => 'status-cancelled',
        default      => 'status-draft',
    };
}

function enhancement_log_format_datetime(?string $value): string
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

function enhancement_log_format_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));

        return $dt->setTimezone(new DateTimeZone('America/Chicago'))->format('M j, Y');
    } catch (Throwable) {
        return $value;
    }
}

function enhancement_log_parse_date_input(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone('America/Chicago'));
    if ($date === false) {
        return null;
    }

    return $date->setTime(12, 0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function enhancement_log_from_input(array $input): array
{
    return [
        'enhancement_title' => trim((string) ($input['enhancement_title'] ?? '')),
        'enh_desc'          => trim((string) ($input['enh_desc'] ?? '')),
        'enh_type'          => trim((string) ($input['enh_type'] ?? '')),
        'it_product'        => trim((string) ($input['it_product'] ?? '')),
        'priority'          => trim((string) ($input['priority'] ?? '')),
        'impact'            => trim((string) ($input['impact'] ?? '')),
        'requested_by'      => trim((string) ($input['requested_by'] ?? '')),
        'request_date'      => trim((string) ($input['request_date'] ?? '')),
        'request_status'    => trim((string) ($input['request_status'] ?? 'New')),
        'req_due_date'      => trim((string) ($input['req_due_date'] ?? '')),
        'req_notes'         => trim((string) ($input['req_notes'] ?? '')),
    ];
}

function enhancement_log_to_form(array $row): array
{
    $requestDate = '';
    if (!empty($row['RequestDate'])) {
        try {
            $requestDate = (new DateTimeImmutable((string) $row['RequestDate'], new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('America/Chicago'))
                ->format('Y-m-d');
        } catch (Throwable) {
            $requestDate = '';
        }
    }

    $reqDueDate = '';
    if (!empty($row['ReqDueDate'])) {
        try {
            $reqDueDate = (new DateTimeImmutable((string) $row['ReqDueDate'], new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('America/Chicago'))
                ->format('Y-m-d');
        } catch (Throwable) {
            $reqDueDate = '';
        }
    }

    return [
        'enhancement_title' => (string) ($row['EnhancementTitle'] ?? ''),
        'enh_desc'          => (string) ($row['EnhDesc'] ?? ''),
        'enh_type'          => (string) ($row['EnhType'] ?? ''),
        'it_product'        => (string) ($row['ITProduct'] ?? ''),
        'priority'          => (string) ($row['Priority'] ?? ''),
        'impact'            => (string) ($row['Impact'] ?? ''),
        'requested_by'      => (string) ($row['RequestedBy'] ?? ''),
        'request_date'      => $requestDate,
        'request_status'    => (string) ($row['RequestStatus'] ?? 'New'),
        'req_due_date'      => $reqDueDate,
        'req_notes'         => (string) ($row['ReqNotes'] ?? ''),
    ];
}

function enhancement_log_validate_form(array $form): ?string
{
    if ($form['enhancement_title'] === '') {
        return 'Backlog item title is required.';
    }

    if (!in_array($form['enh_type'], ENHANCEMENT_LOG_TYPES, true)) {
        return 'Type is required.';
    }

    if (!in_array($form['it_product'], ENHANCEMENT_LOG_IT_PRODUCTS, true)) {
        return 'IT product is required.';
    }

    if ($form['priority'] !== '' && !in_array($form['priority'], ENHANCEMENT_LOG_PRIORITIES, true)) {
        return 'Invalid priority.';
    }

    if ($form['impact'] !== '' && !in_array($form['impact'], ENHANCEMENT_LOG_IMPACTS, true)) {
        return 'Invalid impact.';
    }

    if (!in_array($form['request_status'], ENHANCEMENT_LOG_STATUSES, true)) {
        return 'Invalid request status.';
    }

    if ($form['request_date'] !== '' && enhancement_log_parse_date_input($form['request_date']) === null) {
        return 'Request date is invalid.';
    }

    if ($form['req_due_date'] !== '' && enhancement_log_parse_date_input($form['req_due_date']) === null) {
        return 'Due date is invalid.';
    }

    return null;
}

function enhancement_log_get(int $logId): ?array
{
    if ($logId <= 0) {
        return null;
    }

    $pdo = db();
    db_apply_sql_server_options($pdo);

    $stmt = $pdo->prepare('SELECT * FROM dbo.EnhancementLog WHERE EnhancementLogID = :id');
    $stmt->execute(['id' => $logId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function enhancement_log_list(array $filters = []): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $sql = <<<SQL
        SELECT
            EnhancementLogID,
            EnhancementTitle,
            EnhDesc,
            EnhType,
            ITProduct,
            Priority,
            Impact,
            RequestedBy,
            RequestDate,
            RequestStatus,
            ReqDueDate,
            ReqNotes,
            CreateDate,
            ModifiedDate
        FROM dbo.EnhancementLog
        WHERE 1 = 1
    SQL;

    $params = [];

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '' && in_array($status, ENHANCEMENT_LOG_STATUSES, true)) {
        $sql .= ' AND RequestStatus = :status';
        $params['status'] = $status;
    }

    $enhType = trim((string) ($filters['enh_type'] ?? ''));
    if ($enhType !== '' && in_array($enhType, ENHANCEMENT_LOG_TYPES, true)) {
        $sql .= ' AND EnhType = :enh_type';
        $params['enh_type'] = $enhType;
    }

    $itProduct = trim((string) ($filters['it_product'] ?? ''));
    if ($itProduct !== '' && in_array($itProduct, ENHANCEMENT_LOG_IT_PRODUCTS, true)) {
        $sql .= ' AND ITProduct = :it_product';
        $params['it_product'] = $itProduct;
    }

    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $sql .= ' AND (
            EnhancementTitle LIKE :q
            OR EnhDesc LIKE :q
            OR RequestedBy LIKE :q
            OR ReqNotes LIKE :q
            OR EnhType LIKE :q
            OR ITProduct LIKE :q
            OR Priority LIKE :q
            OR Impact LIKE :q
        )';
        $params['q'] = '%' . $search . '%';
    }

    $sortState = table_sort_state(ENHANCEMENT_LOG_LIST_SORT_COLUMNS, 'request_date', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(ENHANCEMENT_LOG_LIST_SORT_SQL, $sortState, 'request_date', 'id');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function enhancement_log_save(array $input, ?int $logId = null): array
{
    $form = enhancement_log_from_input($input);
    $error = enhancement_log_validate_form($form);
    if ($error !== null) {
        return ['ok' => false, 'error' => $error, 'id' => null];
    }

    $pdo = db();
    db_apply_sql_server_options($pdo);

    $params = [
        'enhancement_title' => $form['enhancement_title'],
        'enh_desc'          => $form['enh_desc'] !== '' ? $form['enh_desc'] : null,
        'enh_type'          => $form['enh_type'],
        'it_product'        => $form['it_product'],
        'priority'          => $form['priority'] !== '' ? $form['priority'] : null,
        'impact'            => $form['impact'] !== '' ? $form['impact'] : null,
        'requested_by'      => $form['requested_by'] !== '' ? $form['requested_by'] : null,
        'request_date'      => enhancement_log_parse_date_input($form['request_date']),
        'request_status'    => $form['request_status'],
        'req_due_date'      => enhancement_log_parse_date_input($form['req_due_date']),
        'req_notes'         => $form['req_notes'] !== '' ? $form['req_notes'] : null,
    ];

    try {
        if ($logId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.EnhancementLog (
                    EnhancementTitle, EnhDesc, EnhType, ITProduct, Priority, Impact, RequestedBy, RequestDate,
                    RequestStatus, ReqDueDate, ReqNotes
                )
                OUTPUT INSERTED.EnhancementLogID
                VALUES (
                    :enhancement_title, :enh_desc, :enh_type, :it_product, :priority, :impact, :requested_by, :request_date,
                    :request_status, :req_due_date, :req_notes
                )
            SQL);
            $stmt->execute($params);
            $newId = (int) $stmt->fetchColumn();

            return ['ok' => true, 'error' => null, 'id' => $newId];
        }

        $existing = enhancement_log_get($logId);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Backlog item not found.', 'id' => null];
        }

        $params['id'] = $logId;
        $pdo->prepare(<<<SQL
            UPDATE dbo.EnhancementLog
            SET
                EnhancementTitle = :enhancement_title,
                EnhDesc = :enh_desc,
                EnhType = :enh_type,
                ITProduct = :it_product,
                Priority = :priority,
                Impact = :impact,
                RequestedBy = :requested_by,
                RequestDate = :request_date,
                RequestStatus = :request_status,
                ReqDueDate = :req_due_date,
                ReqNotes = :req_notes,
                ModifiedDate = SYSUTCDATETIME()
            WHERE EnhancementLogID = :id
        SQL)->execute($params);

        return ['ok' => true, 'error' => null, 'id' => $logId];
    } catch (Throwable $e) {
        error_log('enhancement_log_save: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to save backlog item.', 'id' => null];
    }
}
