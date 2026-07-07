<?php

require_once __DIR__ . '/permissions.php';

const APPROVAL_TOKEN_BYTES = 32;
const APPROVAL_TOKEN_EXPIRY_DAYS = 14;

const APPROVAL_TYPES = [
    'PO' => [
        'label'            => 'PO Approval',
        'permission'       => 'POApproval',
        'entity_type'      => 'PurchaseOrder',
        'entity_label'     => 'Purchase order',
    ],
    'TE' => [
        'label'            => 'T&E Approval',
        'permission'       => 'TEApproval',
        'entity_type'      => 'TEReport',
        'entity_label'     => 'Expense report',
    ],
    'QBOInsert' => [
        'label'            => 'QBO Insert Approval',
        'permission'       => 'QBOInsertApproval',
        'entity_type'      => 'SupplierInvoice',
        'entity_label'     => 'Supplier invoice',
    ],
    'Payment' => [
        'label'            => 'Payment Approval',
        'permission'       => 'PaymentApproval',
        'entity_type'      => 'POPayment',
        'entity_label'     => 'Payment request',
        'secondary_type'   => 'SupplierInvoice',
    ],
];

const APPROVAL_STANDARD_ACTIONS = [
    'approve'   => [
        'label'            => 'Approve',
        'result'           => 'Approved',
        'require_comments' => false,
        'viewed_message'   => false,
    ],
    'reject'    => [
        'label'            => 'Reject',
        'result'           => 'Rejected',
        'require_comments' => false,
        'viewed_message'   => false,
    ],
    'send_back' => [
        'label'            => 'Send Back with Comments',
        'result'           => 'Sent Back with Comments',
        'require_comments' => true,
        'viewed_message'   => false,
    ],
    'cancel'    => [
        'label'            => 'Cancel',
        'result'           => 'Viewed by Approver',
        'require_comments' => false,
        'viewed_message'   => true,
    ],
];

function approval_site_url(): string
{
    return rtrim((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'), '/');
}

function approval_type_config(string $approvalType): ?array
{
    return APPROVAL_TYPES[$approvalType] ?? null;
}

function approval_type_label(string $approvalType): string
{
    return approval_type_config($approvalType)['label'] ?? $approvalType;
}

function approval_permission_column(string $approvalType): ?string
{
    return approval_type_config($approvalType)['permission'] ?? null;
}

function approval_can_read_type(string $approvalType): bool
{
    $column = approval_permission_column($approvalType);

    return $column !== null && auth_can_read($column);
}

function approval_can_act_on_type(string $approvalType): bool
{
    $column = approval_permission_column($approvalType);

    return $column !== null && auth_can_update($column);
}

function approval_query_users_with_role_permission(string $column, string $action = 'U'): array
{
    $action = strtoupper($action);
    if (!isset(PERMISSION_ACTIONS[$action])) {
        return [];
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            u.UserID,
            u.UserName,
            u.UserLogin,
            r.RoleName
        FROM dbo.[User] u
        INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
        WHERE r.{$column} LIKE :permission_pattern
          AND u.UserLogin IS NOT NULL
          AND LTRIM(RTRIM(u.UserLogin)) <> ''
        ORDER BY u.UserName
    SQL);
    $stmt->execute(['permission_pattern' => '%' . $action . '%']);

    return $stmt->fetchAll();
}

function approval_alert_name_for_type(string $approvalType): ?string
{
    require_once __DIR__ . '/alert-messages.php';

    return match ($approvalType) {
        'QBOInsert' => ALERT_NAME_QBO_INSERT_APPROVAL_REQUEST,
        'Payment'   => ALERT_NAME_PAYMENT_APPROVAL_REQUEST,
        default     => null,
    };
}

function approval_list_alert_subscriber_approvers(string $alertName, string $permissionColumn, string $action = 'U'): array
{
    require_once __DIR__ . '/alert-messages.php';
    if (!alert_tables_available()) {
        return [];
    }

    $action = strtoupper($action);
    if (!isset(PERMISSION_ACTIONS[$action])) {
        return [];
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT DISTINCT
            u.UserID,
            u.UserName,
            u.UserLogin,
            r.RoleName
        FROM dbo.AlertMessage am
        INNER JOIN dbo.AlertSubscription sub ON sub.alertID = am.alertID
        INNER JOIN dbo.[User] u ON u.UserID = sub.UserID
        INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
        WHERE am.AlertName = :alert_name
          AND am.AlertStatus = 1
          AND r.{$permissionColumn} LIKE :permission_pattern
          AND u.UserLogin IS NOT NULL
          AND LTRIM(RTRIM(u.UserLogin)) <> ''
        ORDER BY u.UserName
    SQL);
    $stmt->execute([
        'alert_name'           => $alertName,
        'permission_pattern'   => '%' . $action . '%',
    ]);

    return array_values(array_filter(
        $stmt->fetchAll(),
        static function (array $row): bool {
            $email = strtolower(trim((string) ($row['UserLogin'] ?? '')));

            return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
        }
    ));
}

function approval_list_users_for_type(string $approvalType, string $action = 'U'): array
{
    $column = approval_permission_column($approvalType);
    if ($column === null) {
        return [];
    }

    $approvers = approval_query_users_with_role_permission($column, $action);
    if ($approvers !== []) {
        return $approvers;
    }

    $alertName = approval_alert_name_for_type($approvalType);
    if ($alertName === null) {
        return [];
    }

    return approval_list_alert_subscriber_approvers($alertName, $column, $action);
}

function approval_list_log(
    string $approvalType,
    int $entityId,
    ?string $entityType = null,
    ?int $secondaryEntityId = null
): array {
    $config = approval_type_config($approvalType);
    if ($config === null) {
        return [];
    }

    $entityType = $entityType ?? $config['entity_type'];
    $pdo = db();
    $sql = <<<SQL
        SELECT
            ApprovalID,
            ApprovalType,
            EntityType,
            EntityID,
            SecondaryEntityType,
            SecondaryEntityID,
            ApproverUserID,
            ApproverName,
            ApproverResult,
            ApproverComments,
            LogDate
        FROM dbo.ApprovalLog
        WHERE ApprovalType = :approval_type
          AND EntityType = :entity_type
          AND EntityID = :entity_id
    SQL;
    $params = [
        'approval_type' => $approvalType,
        'entity_type'   => $entityType,
        'entity_id'     => $entityId,
    ];

    if ($secondaryEntityId !== null && !empty($config['secondary_type'])) {
        $sql .= ' AND SecondaryEntityID = :secondary_entity_id';
        $params['secondary_entity_id'] = $secondaryEntityId;
    }

    $sql .= ' ORDER BY LogDate DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return approval_normalize_log_rows($stmt->fetchAll());
}

function approval_normalize_log_rows(array $rows): array
{
    require_once __DIR__ . '/admin.php';

    foreach ($rows as &$row) {
        foreach (['ApproverComments', 'ApproverName', 'ApproverResult'] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = admin_db_to_string($row[$column]);
            }
        }
    }
    unset($row);

    return $rows;
}

function approval_append_log(
    string $approvalType,
    int $entityId,
    string $approverName,
    string $approverResult,
    ?string $approverComments = null,
    ?int $approverUserId = null,
    ?string $entityType = null,
    ?string $secondaryEntityType = null,
    ?int $secondaryEntityId = null
): int {
    $config = approval_type_config($approvalType);
    if ($config === null) {
        throw new InvalidArgumentException('Unknown approval type.');
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.ApprovalLog (
            ApprovalType, EntityType, EntityID,
            SecondaryEntityType, SecondaryEntityID,
            ApproverUserID, ApproverName, ApproverResult, ApproverComments
        )
        OUTPUT INSERTED.ApprovalID AS inserted_id
        VALUES (
            :approval_type, :entity_type, :entity_id,
            :secondary_entity_type, :secondary_entity_id,
            :approver_user_id, :approver_name, :approver_result, :approver_comments
        )
    SQL);
    $stmt->execute([
        'approval_type'         => $approvalType,
        'entity_type'           => $entityType ?? $config['entity_type'],
        'entity_id'             => $entityId,
        'secondary_entity_type' => $secondaryEntityType ?? ($config['secondary_type'] ?? null),
        'secondary_entity_id'   => $secondaryEntityId,
        'approver_user_id'      => $approverUserId,
        'approver_name'         => $approverName,
        'approver_result'       => $approverResult,
        'approver_comments'     => $approverComments,
    ]);

    return db_fetch_inserted_int($stmt, 'inserted_id');
}

function approval_first_result_date(string $approvalType, int $entityId, string $result): ?string
{
    $config = approval_type_config($approvalType);
    if ($config === null) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT MIN(LogDate) AS first_date
        FROM dbo.ApprovalLog
        WHERE ApprovalType = :approval_type
          AND EntityType = :entity_type
          AND EntityID = :entity_id
          AND ApproverResult = :result
    SQL);
    $stmt->execute([
        'approval_type' => $approvalType,
        'entity_type'   => $config['entity_type'],
        'entity_id'     => $entityId,
        'result'        => $result,
    ]);
    $value = $stmt->fetchColumn();

    return ($value === false || $value === null || $value === '') ? null : (string) $value;
}

function approval_list_recent(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            l.ApprovalID,
            l.ApprovalType,
            l.EntityType,
            l.EntityID,
            l.SecondaryEntityType,
            l.SecondaryEntityID,
            l.ApproverName,
            l.ApproverResult,
            l.ApproverComments,
            l.LogDate
        FROM dbo.ApprovalLog l
        WHERE 1 = 1
    SQL;
    $params = [];

    if (!empty($filters['approval_type'])) {
        $sql .= ' AND l.ApprovalType = :approval_type';
        $params['approval_type'] = $filters['approval_type'];
    }

    if (!empty($filters['result'])) {
        $sql .= ' AND l.ApproverResult = :result';
        $params['result'] = $filters['result'];
    }

    $allowedTypes = $filters['allowed_types'] ?? null;
    if (is_array($allowedTypes)) {
        if ($allowedTypes === []) {
            return [];
        }

        $placeholders = [];
        foreach (array_values($allowedTypes) as $index => $type) {
            $key = 'allowed_type_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $type;
        }
        $sql .= ' AND l.ApprovalType IN (' . implode(', ', $placeholders) . ')';
    }

    $sql .= ' ORDER BY l.LogDate DESC';

    if (!empty($filters['limit'])) {
        $sql .= ' OFFSET 0 ROWS FETCH NEXT ' . (int) $filters['limit'] . ' ROWS ONLY';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return approval_normalize_log_rows($stmt->fetchAll());
}

function approval_pending_row(
    string $approvalType,
    string $entityType,
    int $entityId,
    mixed $submittedAt,
    ?string $submitterName,
    ?string $secondaryEntityType = null,
    ?int $secondaryEntityId = null
): array {
    $submitterName = trim((string) ($submitterName ?? ''));

    return [
        'ApprovalID'          => null,
        'ApprovalType'        => $approvalType,
        'EntityType'          => $entityType,
        'EntityID'            => $entityId,
        'SecondaryEntityType' => $secondaryEntityType,
        'SecondaryEntityID'   => $secondaryEntityId,
        'ApproverName'        => '—',
        'ApproverResult'      => 'Pending approval',
        'ApproverComments'    => $submitterName !== '' ? 'Submitted by ' . $submitterName : 'Awaiting approver action',
        'LogDate'             => $submittedAt,
        'IsPending'           => true,
    ];
}

function approval_list_pending_entries(array $filters = []): array
{
    $allowedTypes = $filters['allowed_types'] ?? null;
    if (is_array($allowedTypes) && $allowedTypes === []) {
        return [];
    }

    $typeFilter = (string) ($filters['approval_type'] ?? '');
    $includeType = static function (string $type) use ($allowedTypes, $typeFilter): bool {
        if ($typeFilter !== '' && $typeFilter !== $type) {
            return false;
        }

        return !is_array($allowedTypes) || in_array($type, $allowedTypes, true);
    };

    $entries = [];

    if ($includeType('PO') && approval_can_read_type('PO')) {
        require_once __DIR__ . '/po-approval.php';
        foreach (po_list_pending_approvals() as $row) {
            $entries[] = approval_pending_row(
                'PO',
                'PurchaseOrder',
                (int) $row['POID'],
                $row['ModifiedDate'] ?? $row['CreateDate'] ?? null,
                (string) ($row['CreatedByName'] ?? '')
            );
        }
    }

    if ($includeType('TE') && !app_approval_type_nav_hidden('TE') && approval_can_read_type('TE')) {
        require_once __DIR__ . '/te-approval.php';
        foreach (te_list_pending_approvals() as $row) {
            $entries[] = approval_pending_row(
                'TE',
                'TEReport',
                (int) $row['ReportID'],
                $row['SubmittedAt'] ?? null,
                (string) ($row['EmployeeName'] ?? '')
            );
        }
    }

    if ($includeType('QBOInsert') && approval_can_read_type('QBOInsert')) {
        require_once __DIR__ . '/qbo-insert-approval.php';
        foreach (qbo_insert_list_pending() as $row) {
            $entries[] = approval_pending_row(
                'QBOInsert',
                'SupplierInvoice',
                (int) $row['SupplierInvoiceID'],
                $row['ModifiedDate'] ?? $row['TxnDate'] ?? null,
                (string) ($row['CreatedByName'] ?? '')
            );
        }
    }

    if ($includeType('Payment') && approval_can_read_type('Payment')) {
        require_once __DIR__ . '/payment-approval.php';
        foreach (payment_approval_list_pending() as $row) {
            $entries[] = approval_pending_row(
                'Payment',
                'SupplierInvoice',
                (int) $row['SupplierInvoiceID'],
                $row['ModifiedDate'] ?? $row['TxnDate'] ?? null,
                (string) ($row['CreatedByName'] ?? '')
            );
        }
    }

    return $entries;
}

function approval_list_combined(array $filters = []): array
{
    $status = (string) ($filters['status'] ?? 'all');
    if ($status === 'pending') {
        return approval_list_pending_entries($filters);
    }

    if ($status === 'completed') {
        return approval_list_recent($filters);
    }

    $pending = approval_list_pending_entries($filters);
    $historyLimit = !empty($filters['limit']) ? (int) $filters['limit'] : null;
    $historyFilters = $filters;
    if ($historyLimit !== null) {
        $historyFilters['limit'] = max($historyLimit, 200);
    }

    $history = approval_list_recent($historyFilters);
    $combined = array_merge($pending, $history);

    usort($combined, static function (array $a, array $b): int {
        $dateA = $a['LogDate'] ?? '';
        $dateB = $b['LogDate'] ?? '';
        if ($dateA instanceof DateTimeInterface) {
            $dateA = $dateA->format('Y-m-d H:i:s');
        }
        if ($dateB instanceof DateTimeInterface) {
            $dateB = $dateB->format('Y-m-d H:i:s');
        }

        return strcmp((string) $dateB, (string) $dateA);
    });

    if ($historyLimit !== null) {
        $combined = array_slice($combined, 0, $historyLimit);
    }

    return $combined;
}

function approval_index_url(?string $type = null, ?string $status = null, ?string $notice = null): string
{
    $params = [];
    if ($type !== null && $type !== '') {
        $params['type'] = $type;
    }
    if ($status !== null && $status !== '' && $status !== 'all') {
        $params['status'] = $status;
    }
    if ($notice !== null && $notice !== '') {
        $params['notice'] = $notice;
    }

    $query = http_build_query($params);

    return '/approvals/' . ($query !== '' ? '?' . $query : '');
}

function approval_count_pending_for_user(): int
{
    $total = 0;
    foreach (approval_queue_links_for_user() as $link) {
        $total += (int) ($link['pending'] ?? 0);
    }

    return $total;
}

function approval_pending_href(string $approvalType, int $entityId, array $row = []): ?string
{
    if ($approvalType === 'Payment' && ($row['EntityType'] ?? '') === 'SupplierInvoice') {
        return '/accounting/supplier-invoices/approve.php?id=' . $entityId;
    }

    return match ($approvalType) {
        'PO'        => '/po-management/approve.php?id=' . $entityId,
        'TE'        => '/travel-expense/approve.php?id=' . $entityId,
        'QBOInsert' => '/accounting/supplier-invoices/approve.php?id=' . $entityId,
        'Payment'   => '/accounting/invoice-payments/approve.php?id=' . $entityId,
        default     => null,
    };
}

function approval_format_log_row_for_display(array $row): array
{
    $approvalType = (string) ($row['ApprovalType'] ?? '');
    $entityId = (int) ($row['EntityID'] ?? 0);
    $reference = approval_type_label($approvalType) . ' #' . $entityId;

    if ($approvalType === 'PO') {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT PONumber FROM dbo.PurchaseOrder WHERE POID = :id');
        $stmt->execute(['id' => $entityId]);
        $poNumber = $stmt->fetchColumn();
        if ($poNumber !== false && $poNumber !== '') {
            $reference = (string) $poNumber;
        }
    } elseif ($approvalType === 'TE') {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT ReportNumber FROM dbo.TEReport WHERE ReportID = :id');
        $stmt->execute(['id' => $entityId]);
        $reportNumber = $stmt->fetchColumn();
        if ($reportNumber !== false && $reportNumber !== '') {
            $reference = (string) $reportNumber;
        }
    } elseif ($approvalType === 'Payment' || $approvalType === 'QBOInsert') {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT DocNumber FROM dbo.SupplierInvoice WHERE SupplierInvoiceID = :id');
        $invoiceId = (int) ($row['SecondaryEntityID'] ?? $entityId);
        $stmt->execute(['id' => $invoiceId]);
        $docNumber = $stmt->fetchColumn();
        if ($docNumber !== false && trim((string) $docNumber) !== '') {
            $reference = (string) $docNumber;
        }
    }

    return [
        'reference' => $reference,
        'href'      => approval_entity_href($approvalType, $entityId, $row),
    ];
}

function approval_entity_href(string $approvalType, int $entityId, array $row = []): ?string
{
    if ($approvalType === 'Payment' && ($row['EntityType'] ?? '') === 'SupplierInvoice') {
        return '/accounting/supplier-invoices/view.php?id=' . $entityId;
    }

    return match ($approvalType) {
        'PO'        => '/po-management/view.php?id=' . $entityId,
        'TE'        => '/travel-expense/view.php?id=' . $entityId,
        'QBOInsert' => '/accounting/supplier-invoices/view.php?id=' . $entityId,
        'Payment'   => '/accounting/invoice-payments/edit.php?id=' . $entityId,
        default     => null,
    };
}

function approval_types_for_user(): array
{
    $types = [];
    foreach (array_keys(APPROVAL_TYPES) as $approvalType) {
        if (app_approval_type_nav_hidden($approvalType)) {
            continue;
        }

        if (approval_can_read_type($approvalType)) {
            $types[] = $approvalType;
        }
    }

    return $types;
}

function approval_require_any(): void
{
    auth_require_login();
    if (approval_types_for_user() !== []) {
        return;
    }
    auth_render_access_denied('You do not have permission to view approvals.');
}

function approval_queue_links_for_user(): array
{
    $links = [];

    if (approval_can_read_type('PO')) {
        require_once __DIR__ . '/po-approval.php';
        $links[] = [
            'label'   => 'PO Approval',
            'type'    => 'PO',
            'href'    => approval_index_url('PO', 'pending'),
            'pending' => po_count_pending_approvals(),
        ];
    }

    if (approval_can_read_type('TE') && !app_approval_type_nav_hidden('TE')) {
        require_once __DIR__ . '/te-approval.php';
        $links[] = [
            'label'   => 'T&E Approval',
            'type'    => 'TE',
            'href'    => approval_index_url('TE', 'pending'),
            'pending' => te_count_pending_approvals(),
        ];
    }

    if (approval_can_read_type('QBOInsert')) {
        require_once __DIR__ . '/qbo-insert-approval.php';
        $links[] = [
            'label'   => 'QBO Insert Approval',
            'type'    => 'QBOInsert',
            'href'    => approval_index_url('QBOInsert', 'pending'),
            'pending' => qbo_insert_count_pending(),
        ];
    }

    if (approval_can_read_type('Payment')) {
        require_once __DIR__ . '/payment-approval.php';
        $links[] = [
            'label'   => 'Payment Approval',
            'type'    => 'Payment',
            'href'    => approval_index_url('Payment', 'pending'),
            'pending' => payment_approval_count_pending(),
        ];
    }

    return $links;
}
