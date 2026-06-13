<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin.php';

const AUDIT_BATCH_SEPARATOR = "\n-- AUDIT BATCH --\n";

const AUDIT_ALLOWED_TABLES = [
    'PurchaseOrder',
    'POLineItem',
    'POApprovalLog',
    'POAttachment',
    'User',
    'Role',
];

function audit_require_read(): void
{
    auth_require_admin_read('users');
}

function audit_require_rollback(): void
{
    auth_require_admin_update('users');
}

function audit_sql_literal($value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "N'" . str_replace("'", "''", (string) $value) . "'";
}

function audit_sql_binary($value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_resource($value)) {
        $content = stream_get_contents($value);
        if ($content === false) {
            return 'NULL';
        }

        return '0x' . bin2hex($content);
    }

    return '0x' . bin2hex((string) $value);
}

function audit_sql_ident(string $name): string
{
    return '[' . str_replace(']', ']]', $name) . ']';
}

function audit_join_batches(array $statements): string
{
    $statements = array_values(array_filter(array_map('trim', $statements)));

    return implode(AUDIT_BATCH_SEPARATOR, $statements);
}

function audit_split_batches(string $sql): array
{
    $parts = explode(AUDIT_BATCH_SEPARATOR, $sql);

    return array_values(array_filter(array_map('trim', $parts)));
}

function audit_log_change(string $changeSql, string $reverseSql, ?int $userId = null): ?int
{
    $changeSql = trim($changeSql);
    $reverseSql = trim($reverseSql);

    if ($changeSql === '' || $reverseSql === '') {
        return null;
    }

    $userId = $userId ?? (auth_user()['UserID'] ?? null);
    if ($userId === null || $userId <= 0) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.AuditChangeLog (UserID, ChangeSQL, ReverseSQL)
        OUTPUT INSERTED.LogID AS inserted_id
        VALUES (:user, :change_sql, :reverse_sql)
    SQL);
    $stmt->execute([
        'user'        => $userId,
        'change_sql'  => $changeSql,
        'reverse_sql' => $reverseSql,
    ]);

    return db_fetch_inserted_int($stmt, 'inserted_id');
}

function audit_list_logs(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            l.LogID,
            l.ChangeDate,
            l.UserID,
            l.ChangeSQL,
            l.ReverseSQL,
            l.RolledBackDate,
            u.UserName,
            u.UserLogin
        FROM dbo.AuditChangeLog l
        INNER JOIN dbo.[User] u ON u.UserID = l.UserID
        WHERE 1 = 1
    SQL;
    $params = [];

    if (!empty($filters['log_id'])) {
        $sql .= ' AND l.LogID = :log_id';
        $params['log_id'] = (int) $filters['log_id'];
    }

    if (!empty($filters['user_id'])) {
        $sql .= ' AND l.UserID = :user_id';
        $params['user_id'] = (int) $filters['user_id'];
    }

    if (!empty($filters['date_from'])) {
        $sql .= ' AND l.ChangeDate >= :date_from';
        $params['date_from'] = $filters['date_from'] . ' 00:00:00';
    }

    if (!empty($filters['date_to'])) {
        $sql .= ' AND l.ChangeDate <= :date_to';
        $params['date_to'] = $filters['date_to'] . ' 23:59:59';
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (l.ChangeSQL LIKE :q OR l.ReverseSQL LIKE :q OR u.UserName LIKE :q OR u.UserLogin LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    if (($filters['rolled_back'] ?? '') === 'yes') {
        $sql .= ' AND l.RolledBackDate IS NOT NULL';
    } elseif (($filters['rolled_back'] ?? '') === 'no') {
        $sql .= ' AND l.RolledBackDate IS NULL';
    }

    $sql .= ' ORDER BY l.ChangeDate DESC, l.LogID DESC';

    $limit = (int) ($filters['limit'] ?? 200);
    if ($limit > 0) {
        $sql .= ' OFFSET 0 ROWS FETCH NEXT ' . $limit . ' ROWS ONLY';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function audit_get_log(int $logId): ?array
{
    $rows = audit_list_logs(['log_id' => $logId, 'limit' => 1]);

    return $rows[0] ?? null;
}

function audit_statement_is_allowed(string $statement): bool
{
    $statement = trim($statement);
    if ($statement === '') {
        return false;
    }

    if (preg_match('/^SET IDENTITY_INSERT/i', $statement)) {
        $normalized = str_replace(['[', ']'], '', $statement);
        foreach (AUDIT_ALLOWED_TABLES as $table) {
            if (stripos($normalized, 'dbo.' . $table) !== false) {
                return true;
            }
        }

        return false;
    }

    if (!preg_match('/^(INSERT|UPDATE|DELETE)\s+/i', $statement)) {
        return false;
    }

    if (preg_match('/\b(DROP|TRUNCATE|ALTER|CREATE|EXEC|EXECUTE|GRANT|REVOKE|MERGE)\b/i', $statement)) {
        return false;
    }

    $normalized = str_replace(['[', ']'], '', $statement);
    $allowed = false;
    foreach (AUDIT_ALLOWED_TABLES as $table) {
        if (stripos($normalized, 'dbo.' . $table) !== false) {
            $allowed = true;
            break;
        }
    }

    return $allowed;
}

function audit_execute_rollback(int $logId): array
{
    audit_require_rollback();

    $log = audit_get_log($logId);
    if ($log === null) {
        return ['ok' => false, 'error' => 'Audit log entry not found.'];
    }

    if ($log['RolledBackDate'] !== null) {
        return ['ok' => false, 'error' => 'This change has already been rolled back.'];
    }

    $statements = audit_split_batches((string) $log['ReverseSQL']);
    if ($statements === []) {
        return ['ok' => false, 'error' => 'No reverse SQL is available for this entry.'];
    }

    foreach ($statements as $statement) {
        if (!audit_statement_is_allowed($statement)) {
            return ['ok' => false, 'error' => 'Reverse SQL contains a statement that is not allowed to run.'];
        }
    }

    $pdo = db();

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $mark = $pdo->prepare('UPDATE dbo.AuditChangeLog SET RolledBackDate = SYSUTCDATETIME() WHERE LogID = :id');
        $mark->execute(['id' => $logId]);

        $pdo->commit();

        audit_log_change(
            '-- ROLLBACK applied for LogID ' . $logId,
            '-- ROLLBACK marker only; manual restore required if needed',
            auth_user()['UserID'] ?? null
        );

        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => 'Rollback failed: ' . $e->getMessage()];
    }
}

function audit_build_update(string $table, string $pkColumn, $pkValue, array $newValues, array $oldValues): array
{
    $sets = [];
    $reverseSets = [];

    foreach ($newValues as $column => $value) {
        $sets[] = audit_sql_ident($column) . ' = ' . audit_sql_literal($value);
        $reverseSets[] = audit_sql_ident($column) . ' = ' . audit_sql_literal($oldValues[$column] ?? null);
    }

    $tableSql = 'dbo.' . audit_sql_ident($table);
    $change = 'UPDATE ' . $tableSql . ' SET ' . implode(', ', $sets)
        . ' WHERE ' . audit_sql_ident($pkColumn) . ' = ' . audit_sql_literal($pkValue);
    $reverse = 'UPDATE ' . $tableSql . ' SET ' . implode(', ', $reverseSets)
        . ' WHERE ' . audit_sql_ident($pkColumn) . ' = ' . audit_sql_literal($pkValue);

    return ['change' => $change, 'reverse' => $reverse];
}

function audit_build_insert(string $table, array $values, string $pkColumn, $pkValue): array
{
    $columns = array_keys($values);
    $tableSql = 'dbo.' . audit_sql_ident($table);
    $change = 'INSERT INTO ' . $tableSql . ' (' . implode(', ', array_map('audit_sql_ident', $columns)) . ') VALUES ('
        . implode(', ', array_map('audit_sql_literal', array_values($values))) . ')';
    $reverse = 'DELETE FROM ' . $tableSql . ' WHERE ' . audit_sql_ident($pkColumn) . ' = ' . audit_sql_literal($pkValue);

    return ['change' => $change, 'reverse' => $reverse];
}

function audit_build_delete(string $table, array $values, string $pkColumn, bool $preserveIdentity = false): array
{
    $pkValue = $values[$pkColumn] ?? null;
    $tableSql = 'dbo.' . audit_sql_ident($table);
    $columns = array_keys($values);
    $insert = 'INSERT INTO ' . $tableSql . ' (' . implode(', ', array_map('audit_sql_ident', $columns)) . ') VALUES ('
        . implode(', ', array_map('audit_sql_literal', array_values($values))) . ')';

    if ($preserveIdentity) {
        $reverse = 'SET IDENTITY_INSERT ' . $tableSql . ' ON'
            . AUDIT_BATCH_SEPARATOR . $insert
            . AUDIT_BATCH_SEPARATOR . 'SET IDENTITY_INSERT ' . $tableSql . ' OFF';
    } else {
        $reverse = $insert;
    }

    return [
        'change'  => 'DELETE FROM ' . $tableSql . ' WHERE ' . audit_sql_ident($pkColumn) . ' = ' . audit_sql_literal($pkValue),
        'reverse' => $reverse,
    ];
}

function audit_log_simple_change(string $changeSql, string $reverseSql): void
{
    audit_log_change($changeSql, $reverseSql);
}

function audit_po_order_values(array $order): array
{
    return [
        'PONumber'             => $order['PONumber'] ?? null,
        'SupplierID'           => $order['SupplierID'] ?? null,
        'POStatus'             => $order['POStatus'] ?? null,
        'OrderDate'            => $order['OrderDate'] ?? null,
        'ExpectedDeliveryDate' => $order['ExpectedDeliveryDate'] ?? null,
        'Notes'                => $order['Notes'] ?? null,
        'Subtotal'             => $order['Subtotal'] ?? null,
        'ShippingHandling'     => $order['ShippingHandling'] ?? null,
        'TotalDue'             => $order['TotalDue'] ?? null,
        'BuyerName'            => $order['BuyerName'] ?? null,
        'BuyerAddress'         => $order['BuyerAddress'] ?? null,
        'BuyerContactName'     => $order['BuyerContactName'] ?? null,
        'BuyerContactEmail'    => $order['BuyerContactEmail'] ?? null,
        'BuyerContactPhone'    => $order['BuyerContactPhone'] ?? null,
        'SupplierAddress'      => $order['SupplierAddress'] ?? null,
        'DeliveryAddress'      => $order['DeliveryAddress'] ?? null,
        'PaymentTerms'         => $order['PaymentTerms'] ?? null,
        'DeliveryTerms'        => $order['DeliveryTerms'] ?? null,
        'ReferenceDocuments'   => $order['ReferenceDocuments'] ?? null,
        'SpecialInstructions'  => $order['SpecialInstructions'] ?? null,
        'ModifiedbyUser'       => $order['ModifiedbyUser'] ?? null,
    ];
}

function audit_po_line_insert_sql(array $line, int $poId, bool $preserveIdentity = false): string
{
    $lineId = $line['POLineID'] ?? null;
    $columns = ['POID', 'LineNumber', 'ItemSKU', 'ItemDescription', 'QuoteNumber', 'Quantity', 'UnitPrice', 'ExpirationDate'];
    $values = [
        $poId,
        $line['LineNumber'] ?? $line['line_number'] ?? null,
        $line['ItemSKU'] ?? $line['sku'] ?? null,
        $line['ItemDescription'] ?? $line['description'] ?? null,
        $line['QuoteNumber'] ?? $line['quote_number'] ?? null,
        $line['Quantity'] ?? $line['quantity'] ?? null,
        $line['UnitPrice'] ?? $line['unit_price'] ?? null,
        po_normalize_date_for_db($line['ExpirationDate'] ?? $line['expiration_date'] ?? null),
    ];

    if ($preserveIdentity && $lineId !== null) {
        array_unshift($columns, 'POLineID');
        array_unshift($values, $lineId);
    }

    $sql = 'INSERT INTO dbo.POLineItem (' . implode(', ', array_map('audit_sql_ident', $columns)) . ') VALUES ('
        . implode(', ', array_map('audit_sql_literal', $values)) . ')';

    if ($preserveIdentity && $lineId !== null) {
        return 'SET IDENTITY_INSERT dbo.POLineItem ON'
            . AUDIT_BATCH_SEPARATOR . $sql
            . AUDIT_BATCH_SEPARATOR . 'SET IDENTITY_INSERT dbo.POLineItem OFF';
    }

    return $sql;
}

function audit_log_po_save(int $poId, bool $isInsert, ?array $beforeOrder = null, ?array $beforeLines = null): void
{
    require_once __DIR__ . '/po.php';

    $order = po_get_order($poId);
    if ($order === null) {
        return;
    }

    $lines = po_get_lines($poId);
    $changeParts = [];
    $reverseParts = [];

    if ($isInsert) {
        $insertCols = array_merge(
            ['POID' => $poId, 'CreatedByUser' => $order['CreatedByUser'] ?? null],
            audit_po_order_values($order)
        );
        $built = audit_build_insert('PurchaseOrder', $insertCols, 'POID', $poId);
        $changeParts[] = $built['change'];
        foreach ($lines as $line) {
            $changeParts[] = audit_po_line_insert_sql($line, $poId);
        }
        $reverseParts[] = 'DELETE FROM dbo.POLineItem WHERE POID = ' . audit_sql_literal($poId);
        $reverseParts[] = $built['reverse'];
    } else {
        $afterValues = audit_po_order_values($order);
        $beforeValues = audit_po_order_values($beforeOrder ?? []);
        $built = audit_build_update('PurchaseOrder', 'POID', $poId, $afterValues, $beforeValues);
        $changeParts[] = $built['change'];
        $changeParts[] = 'DELETE FROM dbo.POLineItem WHERE POID = ' . audit_sql_literal($poId);
        foreach ($lines as $line) {
            $changeParts[] = audit_po_line_insert_sql($line, $poId);
        }
        $reverseParts[] = 'DELETE FROM dbo.POLineItem WHERE POID = ' . audit_sql_literal($poId);
        foreach ($beforeLines ?? [] as $line) {
            $reverseParts[] = audit_po_line_insert_sql($line, $poId, true);
        }
        $reverseParts[] = $built['reverse'];
    }

    audit_log_change(audit_join_batches($changeParts), audit_join_batches($reverseParts));
}

function audit_log_po_delete(array $order, array $lines): void
{
    $poId = (int) $order['POID'];
    $changeParts = ['DELETE FROM dbo.PurchaseOrder WHERE POID = ' . audit_sql_literal($poId)];
    $reverseParts = [];

    $insertCols = array_merge(
        ['POID' => $poId, 'CreatedByUser' => $order['CreatedByUser'] ?? null],
        audit_po_order_values($order)
    );
    $reverseParts[] = audit_build_delete('PurchaseOrder', $insertCols, 'POID', true)['reverse'];

    foreach ($lines as $line) {
        $reverseParts[] = audit_po_line_insert_sql($line, $poId);
    }

    audit_log_change(audit_join_batches($changeParts), audit_join_batches($reverseParts));
}

function audit_log_po_status_change(int $poId, string $oldStatus, string $newStatus): void
{
    $built = audit_build_update(
        'PurchaseOrder',
        'POID',
        $poId,
        ['POStatus' => $newStatus],
        ['POStatus' => $oldStatus]
    );
    audit_log_change($built['change'], $built['reverse']);
}

function audit_user_row_for_sql(array $user): array
{
    return [
        'UserID'            => $user['UserID'] ?? null,
        'UserName'          => $user['UserName'] ?? null,
        'UserLogin'         => $user['UserLogin'] ?? null,
        'UserPassword'      => $user['UserPassword'] ?? null,
        'UserAssignedRole'  => $user['UserAssignedRole'] ?? null,
        'CreateDate'        => $user['CreateDate'] ?? null,
        'ModifiedDate'      => $user['ModifiedDate'] ?? null,
        'LastPasswordReset' => $user['LastPasswordReset'] ?? null,
        'Modifiedbyuser'    => $user['Modifiedbyuser'] ?? null,
    ];
}

function audit_log_user_save(?array $before, array $after, bool $isInsert): void
{
    $columns = ['UserName', 'UserLogin', 'UserPassword', 'UserAssignedRole', 'Modifiedbyuser'];
    $after = audit_user_row_for_sql($after);
    $before = $before !== null ? audit_user_row_for_sql($before) : null;
    $userId = (int) ($after['UserID'] ?? $before['UserID'] ?? 0);

    if ($isInsert) {
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = $after[$column] ?? null;
        }
        $values['CreateDate'] = $after['CreateDate'] ?? date('Y-m-d H:i:s');
        $values['ModifiedDate'] = $after['ModifiedDate'] ?? date('Y-m-d H:i:s');
        $values['LastPasswordReset'] = $after['LastPasswordReset'] ?? date('Y-m-d H:i:s');
        $built = audit_build_insert('User', array_merge(['UserID' => $userId], $values), 'UserID', $userId);
    } else {
        $newValues = [];
        $oldValues = [];
        foreach ($columns as $column) {
            $newValues[$column] = $after[$column] ?? null;
            $oldValues[$column] = $before[$column] ?? null;
        }
        $built = audit_build_update('User', 'UserID', $userId, $newValues, $oldValues);
    }

    audit_log_change($built['change'], $built['reverse']);
}

function audit_log_user_delete(array $user): void
{
    $built = audit_build_delete('User', audit_user_row_for_sql($user), 'UserID', true);
    audit_log_change($built['change'], $built['reverse']);
}

function audit_log_role_save(?array $before, array $after, bool $isInsert): void
{
    $columns = array_merge(
        ['RoleName', 'RoleDesc', 'ModifiedbyUser'],
        array_keys(ROLE_PERMISSION_FIELDS)
    );
    $roleId = (int) ($after['RoleID'] ?? $before['RoleID'] ?? 0);

    if ($isInsert) {
        $values = ['RoleID' => $roleId, 'RoleCreateDate' => $after['RoleCreateDate'] ?? date('Y-m-d H:i:s')];
        foreach ($columns as $column) {
            $values[$column] = $after[$column] ?? null;
        }
        $built = audit_build_insert('Role', $values, 'RoleID', $roleId);
    } else {
        $newValues = [];
        $oldValues = [];
        foreach ($columns as $column) {
            $newValues[$column] = $after[$column] ?? null;
            $oldValues[$column] = $before[$column] ?? null;
        }
        $built = audit_build_update('Role', 'RoleID', $roleId, $newValues, $oldValues);
    }

    audit_log_change($built['change'], $built['reverse']);
}

function audit_log_role_delete(array $role): void
{
    $built = audit_build_delete('Role', $role, 'RoleID', true);
    audit_log_change($built['change'], $built['reverse']);
}

function audit_log_po_approval_action(int $poId, string $oldStatus, string $newStatus, array $logRow): void
{
    $changeParts = [
        audit_build_update('PurchaseOrder', 'POID', $poId, ['POStatus' => $newStatus], ['POStatus' => $oldStatus])['change'],
    ];
    $reverseParts = [];

    if (isset($logRow['ApprovalID'])) {
        $approvalValues = [
            'ApprovalID'       => $logRow['ApprovalID'],
            'POID'             => $logRow['POID'],
            'ApproverName'     => $logRow['ApproverName'],
            'ApproverResult'   => $logRow['ApproverResult'],
            'ApproverComments' => $logRow['ApproverComments'] ?? null,
        ];
        $changeParts[] = audit_build_insert('POApprovalLog', $approvalValues, 'ApprovalID', $logRow['ApprovalID'])['change'];
        $reverseParts[] = 'DELETE FROM dbo.POApprovalLog WHERE ApprovalID = ' . audit_sql_literal($logRow['ApprovalID']);
    }

    $reverseParts[] = audit_build_update('PurchaseOrder', 'POID', $poId, ['POStatus' => $oldStatus], ['POStatus' => $newStatus])['reverse'];

    audit_log_change(audit_join_batches($changeParts), audit_join_batches($reverseParts));
}

function audit_log_attachment_insert(array $attachment): void
{
    $id = (int) $attachment['AttachmentID'];
    $change = 'INSERT INTO dbo.POAttachment (POID, FileName, ContentType, FileSizeBytes, FileData, AttachmentKind, UploadedByUser) VALUES ('
        . audit_sql_literal($attachment['POID'] ?? null) . ', '
        . audit_sql_literal($attachment['FileName'] ?? null) . ', '
        . audit_sql_literal($attachment['ContentType'] ?? null) . ', '
        . audit_sql_literal($attachment['FileSizeBytes'] ?? null) . ', '
        . audit_sql_binary($attachment['FileData'] ?? null) . ', '
        . audit_sql_literal($attachment['AttachmentKind'] ?? null) . ', '
        . audit_sql_literal($attachment['UploadedByUser'] ?? null) . ')';
    $reverse = 'DELETE FROM dbo.POAttachment WHERE AttachmentID = ' . audit_sql_literal($id);

    audit_log_change($change, $reverse);
}

function audit_list_users_for_filter(): array
{
    $pdo = db();

    return $pdo->query('SELECT UserID, UserName, UserLogin FROM dbo.[User] ORDER BY UserName')->fetchAll();
}

function audit_preview_sql(string $sql, int $max = 180): string
{
    $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;
    if (strlen($sql) <= $max) {
        return $sql;
    }

    return substr($sql, 0, $max) . '...';
}
