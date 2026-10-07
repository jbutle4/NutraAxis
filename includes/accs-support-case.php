<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/table-sort.php';

const ACCS_SUPPORT_CASE_STATUSES = [
    'open',
    'clone_started',
    'cloned',
    'clone_failed',
    'cart_ready',
    'closed',
    'purge_started',
    'purged',
    'purge_failed',
];

const ACCS_SUPPORT_CASE_LIST_SORT_COLUMNS = [
    'id'         => 'Case',
    'status'     => 'Status',
    'prod'       => 'Prod company',
    'stage'      => 'Stage company',
    'ticket'     => 'Ticket',
    'updated'    => 'Updated',
    'created'    => 'Created',
];

const ACCS_SUPPORT_CASE_LIST_SORT_SQL = [
    'id'      => 'c.CaseID',
    'status'  => 'c.Status',
    'prod'    => 'c.ProdCompanyName',
    'stage'   => 'c.StageCompanyName',
    'ticket'  => 'c.TicketRef',
    'updated' => 'c.UpdatedAt',
    'created' => 'c.CreatedAt',
];

function accs_support_case_actor_id(): ?int
{
    $user = auth_user();
    if (!is_array($user) || empty($user['UserID'])) {
        return null;
    }

    return (int) $user['UserID'];
}

/**
 * @param array<string, mixed> $input
 * @return array{ok: bool, error: ?string, id: ?int}
 */
function accs_support_case_create(array $input): array
{
    $ticketRef = trim((string) ($input['ticket_ref'] ?? ''));
    $subject = trim((string) ($input['subject'] ?? ''));
    $prodCompanyId = (int) ($input['prod_company_id'] ?? 0);
    $prodCompanyName = trim((string) ($input['prod_company_name'] ?? ''));
    $prodOrderId = trim((string) ($input['prod_order_id'] ?? ''));
    $actorId = accs_support_case_actor_id();

    if ($subject === '' && $prodCompanyName !== '') {
        $subject = 'Support clone: ' . $prodCompanyName;
    }
    if ($subject === '') {
        $subject = 'ACCS support case';
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.AccsSupportCase (
            TicketRef, Subject, Status,
            ProdCompanyId, ProdCompanyName, ProdOrderId,
            CreatedByUserID, CreatedAt, UpdatedAt
        )
        OUTPUT INSERTED.CaseID AS inserted_id
        VALUES (
            :ticket, :subject, N'open',
            :prod_company_id, :prod_company_name, :prod_order_id,
            :actor, SYSUTCDATETIME(), SYSUTCDATETIME()
        )
    SQL);
    $stmt->execute([
        'ticket'            => $ticketRef !== '' ? $ticketRef : null,
        'subject'           => $subject,
        'prod_company_id'   => $prodCompanyId > 0 ? $prodCompanyId : null,
        'prod_company_name' => $prodCompanyName !== '' ? $prodCompanyName : null,
        'prod_order_id'     => $prodOrderId !== '' ? $prodOrderId : null,
        'actor'             => $actorId,
    ]);

    $caseId = db_fetch_inserted_int($stmt, 'inserted_id');
    if ($caseId <= 0) {
        return ['ok' => false, 'error' => 'Unable to create support case.', 'id' => null];
    }

    accs_support_case_add_event($caseId, 'opened', 'Support case opened.', [
        'ticket_ref' => $ticketRef,
        'subject'    => $subject,
    ]);

    return ['ok' => true, 'error' => null, 'id' => $caseId];
}

function accs_support_case_get(int $caseId): ?array
{
    if ($caseId <= 0) {
        return null;
    }

    $stmt = db()->prepare(<<<SQL
        SELECT
            c.*,
            cu.UserName AS CreatedByName
        FROM dbo.AccsSupportCase c
        LEFT JOIN dbo.[User] cu ON cu.UserID = c.CreatedByUserID
        WHERE c.CaseID = :id
    SQL);
    $stmt->execute(['id' => $caseId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * @param array<string, mixed> $filters
 * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}
 */
function accs_support_case_list(array $filters = []): array
{
    $status = trim((string) ($filters['status'] ?? ''));
    $q = trim((string) ($filters['q'] ?? ''));
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = 50;
    $sortState = table_sort_state(
        ACCS_SUPPORT_CASE_LIST_SORT_COLUMNS,
        'updated',
        'desc',
        $filters
    );
    $orderSql = table_sort_sql_clause(
        ACCS_SUPPORT_CASE_LIST_SORT_SQL,
        $sortState,
        'updated',
        'updated'
    );

    $where = ['1 = 1'];
    $params = [];
    if ($status !== '' && in_array($status, ACCS_SUPPORT_CASE_STATUSES, true)) {
        $where[] = 'c.Status = :status';
        $params['status'] = $status;
    }
    if ($q !== '') {
        $where[] = '(
            CAST(c.CaseID AS NVARCHAR(20)) LIKE :q
            OR c.TicketRef LIKE :q
            OR c.Subject LIKE :q
            OR c.ProdCompanyName LIKE :q
            OR c.StageCompanyName LIKE :q
            OR CAST(c.ProdCompanyId AS NVARCHAR(20)) LIKE :q
            OR CAST(c.StageCompanyId AS NVARCHAR(20)) LIKE :q
        )';
        $params['q'] = '%' . $q . '%';
    }

    $whereSql = implode(' AND ', $where);
    $pdo = db();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dbo.AccsSupportCase c WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $listSql = <<<SQL
        SELECT
            c.*,
            cu.UserName AS CreatedByName
        FROM dbo.AccsSupportCase c
        LEFT JOIN dbo.[User] cu ON cu.UserID = c.CreatedByUserID
        WHERE {$whereSql}
        ORDER BY {$orderSql}
        OFFSET {$offset} ROWS FETCH NEXT {$perPage} ROWS ONLY
    SQL;
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);

    return [
        'rows'     => $listStmt->fetchAll() ?: [],
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'sort'     => $sortState,
    ];
}

/**
 * @param array<string, mixed> $fields
 * @return array{ok: bool, error: ?string}
 */
function accs_support_case_update(int $caseId, array $fields): array
{
    $case = accs_support_case_get($caseId);
    if ($case === null) {
        return ['ok' => false, 'error' => 'Support case not found.'];
    }

    $allowed = [
        'TicketRef'            => 'ticket_ref',
        'Subject'              => 'subject',
        'Status'               => 'status',
        'ProdCompanyId'        => 'prod_company_id',
        'ProdCompanyName'      => 'prod_company_name',
        'ProdAdminEmail'       => 'prod_admin_email',
        'ProdAdminCustomerId'  => 'prod_admin_customer_id',
        'ProdSharedCatalogId'  => 'prod_shared_catalog_id',
        'ProdOrderId'          => 'prod_order_id',
        'StageCompanyId'       => 'stage_company_id',
        'StageCompanyName'     => 'stage_company_name',
        'StageCustomerId'      => 'stage_customer_id',
        'StageSharedCatalogId' => 'stage_shared_catalog_id',
        'StageCustomerGroupId' => 'stage_customer_group_id',
        'StageCartId'          => 'stage_cart_id',
        'CloneSummaryJson'     => 'clone_summary_json',
        'ClosedAt'             => 'closed_at',
        'PurgedAt'             => 'purged_at',
    ];

    $sets = ['UpdatedAt = SYSUTCDATETIME()'];
    $params = ['id' => $caseId];
    foreach ($allowed as $column => $key) {
        if (!array_key_exists($key, $fields)) {
            continue;
        }
        $value = $fields[$key];
        if ($column === 'Status') {
            $status = trim((string) $value);
            if (!in_array($status, ACCS_SUPPORT_CASE_STATUSES, true)) {
                return ['ok' => false, 'error' => 'Invalid support case status.'];
            }
            $sets[] = 'Status = :status';
            $params['status'] = $status;
            continue;
        }
        if (in_array($column, [
            'ProdCompanyId', 'ProdAdminCustomerId', 'ProdSharedCatalogId',
            'StageCompanyId', 'StageCustomerId', 'StageSharedCatalogId', 'StageCustomerGroupId',
        ], true)) {
            $intVal = (int) $value;
            $param = strtolower($column);
            $sets[] = "{$column} = :{$param}";
            $params[$param] = $intVal > 0 ? $intVal : null;
            continue;
        }
        if ($column === 'ClosedAt' || $column === 'PurgedAt') {
            $param = strtolower($column);
            $sets[] = "{$column} = :{$param}";
            $params[$param] = $value;
            continue;
        }
        $param = strtolower($column);
        $stringVal = is_string($value) ? trim($value) : (string) $value;
        $sets[] = "{$column} = :{$param}";
        $params[$param] = $stringVal !== '' ? $stringVal : null;
    }

    if (count($sets) === 1) {
        return ['ok' => true, 'error' => null];
    }

    $sql = 'UPDATE dbo.AccsSupportCase SET ' . implode(', ', $sets) . ' WHERE CaseID = :id';
    db()->prepare($sql)->execute($params);

    return ['ok' => true, 'error' => null];
}

/**
 * @param array<string, mixed>|null $payload
 * @return array{ok: bool, error: ?string, id: ?int}
 */
function accs_support_case_add_event(
    int $caseId,
    string $eventType,
    ?string $message = null,
    ?array $payload = null,
    ?string $processRunId = null
): array {
    $eventType = trim($eventType);
    if ($caseId <= 0 || $eventType === '') {
        return ['ok' => false, 'error' => 'Case ID and event type are required.', 'id' => null];
    }

    $payloadJson = null;
    if ($payload !== null) {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payloadJson = $encoded !== false ? $encoded : null;
    }

    $stmt = db()->prepare(<<<SQL
        INSERT INTO dbo.AccsSupportCaseEvent (
            CaseID, EventType, Message, ProcessRunId, PayloadJson, CreatedByUserID, CreatedAt
        )
        OUTPUT INSERTED.EventID AS inserted_id
        VALUES (
            :case_id, :event_type, :message, :process_run_id, :payload, :actor, SYSUTCDATETIME()
        )
    SQL);
    $stmt->execute([
        'case_id'         => $caseId,
        'event_type'      => $eventType,
        'message'         => $message !== null && trim($message) !== '' ? trim($message) : null,
        'process_run_id'  => $processRunId !== null && trim($processRunId) !== '' ? trim($processRunId) : null,
        'payload'         => $payloadJson,
        'actor'           => accs_support_case_actor_id(),
    ]);

    return [
        'ok'    => true,
        'error' => null,
        'id'    => db_fetch_inserted_int($stmt, 'inserted_id'),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function accs_support_case_list_events(int $caseId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT
            e.*,
            u.UserName AS CreatedByName
        FROM dbo.AccsSupportCaseEvent e
        LEFT JOIN dbo.[User] u ON u.UserID = e.CreatedByUserID
        WHERE e.CaseID = :case_id
        ORDER BY e.CreatedAt ASC, e.EventID ASC
    SQL);
    $stmt->execute(['case_id' => $caseId]);

    return $stmt->fetchAll() ?: [];
}

/**
 * @return array{ok: bool, error: ?string, id: ?int}
 */
function accs_support_case_add_comment(int $caseId, string $body): array
{
    $body = trim($body);
    if ($caseId <= 0) {
        return ['ok' => false, 'error' => 'Case ID is required.', 'id' => null];
    }
    if ($body === '') {
        return ['ok' => false, 'error' => 'Comment body is required.', 'id' => null];
    }
    if (accs_support_case_get($caseId) === null) {
        return ['ok' => false, 'error' => 'Support case not found.', 'id' => null];
    }

    $stmt = db()->prepare(<<<SQL
        INSERT INTO dbo.AccsSupportCaseComment (
            CaseID, Body, CreatedByUserID, CreatedAt
        )
        OUTPUT INSERTED.CommentID AS inserted_id
        VALUES (:case_id, :body, :actor, SYSUTCDATETIME())
    SQL);
    $stmt->execute([
        'case_id' => $caseId,
        'body'    => $body,
        'actor'   => accs_support_case_actor_id(),
    ]);

    $commentId = db_fetch_inserted_int($stmt, 'inserted_id');
    accs_support_case_update($caseId, []);
    accs_support_case_add_event($caseId, 'comment_added', 'Comment added.', [
        'comment_id' => $commentId,
    ]);

    return ['ok' => true, 'error' => null, 'id' => $commentId];
}

/**
 * @return list<array<string, mixed>>
 */
function accs_support_case_list_comments(int $caseId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT
            c.*,
            u.UserName AS CreatedByName
        FROM dbo.AccsSupportCaseComment c
        LEFT JOIN dbo.[User] u ON u.UserID = c.CreatedByUserID
        WHERE c.CaseID = :case_id
        ORDER BY c.CreatedAt ASC, c.CommentID ASC
    SQL);
    $stmt->execute(['case_id' => $caseId]);

    return $stmt->fetchAll() ?: [];
}

/**
 * @param array<string, mixed>|null $payload
 * @return array{ok: bool, error: ?string, id: ?int}
 */
function accs_support_case_add_resource(
    int $caseId,
    string $action,
    string $resourceType,
    ?string $stageEntityId = null,
    ?string $stageEntityName = null,
    ?string $prodSourceId = null,
    ?array $payload = null
): array {
    $action = strtolower(trim($action));
    $resourceType = trim($resourceType);
    if ($caseId <= 0 || !in_array($action, ['created', 'deleted'], true) || $resourceType === '') {
        return ['ok' => false, 'error' => 'Case ID, action, and resource type are required.', 'id' => null];
    }

    $payloadJson = null;
    if ($payload !== null) {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payloadJson = $encoded !== false ? $encoded : null;
    }

    $stmt = db()->prepare(<<<SQL
        INSERT INTO dbo.AccsSupportCaseResource (
            CaseID, Action, ResourceType, StageEntityId, StageEntityName,
            ProdSourceId, PayloadJson, CreatedByUserID, CreatedAt
        )
        OUTPUT INSERTED.ResourceID AS inserted_id
        VALUES (
            :case_id, :action, :resource_type, :stage_entity_id, :stage_entity_name,
            :prod_source_id, :payload, :actor, SYSUTCDATETIME()
        )
    SQL);
    $stmt->execute([
        'case_id'           => $caseId,
        'action'            => $action,
        'resource_type'     => $resourceType,
        'stage_entity_id'   => $stageEntityId !== null && trim($stageEntityId) !== '' ? trim($stageEntityId) : null,
        'stage_entity_name' => $stageEntityName !== null && trim($stageEntityName) !== '' ? trim($stageEntityName) : null,
        'prod_source_id'    => $prodSourceId !== null && trim($prodSourceId) !== '' ? trim($prodSourceId) : null,
        'payload'           => $payloadJson,
        'actor'             => accs_support_case_actor_id(),
    ]);

    return [
        'ok'    => true,
        'error' => null,
        'id'    => db_fetch_inserted_int($stmt, 'inserted_id'),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function accs_support_case_list_resources(int $caseId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT
            r.*,
            u.UserName AS CreatedByName
        FROM dbo.AccsSupportCaseResource r
        LEFT JOIN dbo.[User] u ON u.UserID = r.CreatedByUserID
        WHERE r.CaseID = :case_id
        ORDER BY r.CreatedAt ASC, r.ResourceID ASC
    SQL);
    $stmt->execute(['case_id' => $caseId]);

    return $stmt->fetchAll() ?: [];
}

/**
 * Active (not deleted) Stage resources for purge targeting.
 *
 * @return list<array<string, mixed>>
 */
function accs_support_case_active_resources(int $caseId): array
{
    $rows = accs_support_case_list_resources($caseId);
    $deletedKeys = [];
    foreach ($rows as $row) {
        if (($row['Action'] ?? '') !== 'deleted') {
            continue;
        }
        $key = strtolower((string) ($row['ResourceType'] ?? '')) . ':' . trim((string) ($row['StageEntityId'] ?? ''));
        $deletedKeys[$key] = true;
    }

    $active = [];
    foreach ($rows as $row) {
        if (($row['Action'] ?? '') !== 'created') {
            continue;
        }
        $key = strtolower((string) ($row['ResourceType'] ?? '')) . ':' . trim((string) ($row['StageEntityId'] ?? ''));
        if (isset($deletedKeys[$key])) {
            continue;
        }
        $active[] = $row;
    }

    return $active;
}

function accs_support_case_status_label(string $status): string
{
    return match ($status) {
        'open'          => 'Open',
        'clone_started' => 'Clone started',
        'cloned'        => 'Cloned',
        'clone_failed'  => 'Clone failed',
        'cart_ready'    => 'Cart ready',
        'closed'        => 'Closed',
        'purge_started' => 'Purge started',
        'purged'        => 'Purged',
        'purge_failed'  => 'Purge failed',
        default         => $status,
    };
}
