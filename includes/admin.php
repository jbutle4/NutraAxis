<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';

const ROLE_PERMISSION_FIELDS = [
    'POManagement'         => 'PO Management',
    'POApproval'           => 'PO Approval',
    'TEManagement'         => 'Travel & Expense',
    'TEApproval'           => 'T&E Approval',
    'TEProcessing'         => 'T&E Processing',
    'QBOInsertApproval'    => 'QBO Insert Approval',
    'PaymentApproval'      => 'Payment Approval',
    'InventoryReporting'   => 'Inventory Reporting (Jazz / ACCS / UAT)',
    'SalesReporting'       => 'Sales Reporting',
    'InventoryForecasting' => 'Inventory Forecasting',
    'LabelingOperations'   => 'Labeling Operations',
    'OperationsDashboard'  => 'Operations Dashboard',
    'LegalAgreements'      => 'Legal Agreements & Contracts',
    'ProductCatalog'       => 'Product Catalog / SKU Master',
    'LinksIndex'           => 'Links Index',
    'Support'              => 'Support',
    'Accounting'           => 'Accounting',
    'UserAdmin'            => 'User Administration',
    'ProviderAccountReview'  => 'Provider Account Review',

const ADMIN_USERS_LIST_SORT_COLUMNS = [
    'name'       => 'Name',
    'email'      => 'Email',
    'role'       => 'Role',
    'last_login' => 'Last Login',
];

const ADMIN_USERS_LIST_SORT_SQL = [
    'name'       => 'u.UserName',
    'email'      => 'u.UserLogin',
    'role'       => 'r.RoleName',
    'last_login' => 'u.LastLoginDate',
];

const ADMIN_ROLES_LIST_SORT_COLUMNS = [
    'role'        => 'Role',
    'description' => 'Description',
];

const ADMIN_ROLES_LIST_SORT_SQL = [
    'role'        => 'RoleName',
    'description' => 'RoleDesc',
];

function admin_db_to_string(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if (is_string($value)) {
        return $value;
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    if (is_resource($value) && get_resource_type($value) === 'stream') {
        $contents = stream_get_contents($value);

        return is_string($contents) ? $contents : '';
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    return '';
}

function admin_format_datetime(DateTimeInterface|string|null $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        if ($value instanceof DateTimeInterface) {
            return $value->format('M j, Y g:i A');
        }

        $dt = new DateTimeImmutable((string) $value);

        return $dt->format('M j, Y g:i A');
    } catch (Throwable) {
        return is_scalar($value) ? (string) $value : '—';
    }
}

function admin_list_users(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            u.UserID,
            u.UserName,
            u.UserLogin,
            u.UserAssignedRole,
            r.RoleName,
            u.CreateDate,
            u.ModifiedDate,
            u.LastLoginDate
        FROM dbo.[User] u
        INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
    SQL;

    $sortState = table_sort_state(ADMIN_USERS_LIST_SORT_COLUMNS, 'name', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(ADMIN_USERS_LIST_SORT_SQL, $sortState, 'name', 'name');

    return $pdo->query($sql)->fetchAll();
}

function admin_get_user(int $userId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            u.UserID,
            u.UserName,
            u.UserLogin,
            u.UserPassword,
            u.UserAssignedRole,
            r.RoleName,
            u.CreateDate,
            u.ModifiedDate,
            u.LastLoginDate,
            u.LastPasswordReset
        FROM dbo.[User] u
        INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
        WHERE u.UserID = :id
    SQL);
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function admin_list_users_with_permission(string $column, string $action = 'U'): array
{
    if (!array_key_exists($column, ROLE_PERMISSION_FIELDS)) {
        return [];
    }

    $action = strtoupper($action);
    if (!isset(PERMISSION_ACTIONS[$action])) {
        return [];
    }

    $pdo = db();
    $sql = <<<SQL
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
    SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['permission_pattern' => '%' . $action . '%']);

    return $stmt->fetchAll();
}

function admin_user_has_role_permission(int $userId, string $column, string $action = 'U'): bool
{
    if (!array_key_exists($column, ROLE_PERMISSION_FIELDS)) {
        return false;
    }

    $pdo = db();
    $sql = <<<SQL
        SELECT r.{$column}
        FROM dbo.[User] u
        INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
        WHERE u.UserID = :user_id
    SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $value = $stmt->fetchColumn();

    return permission_has($value === false ? null : (string) $value, $action);
}

function admin_list_roles(array $filters = []): array
{
    $pdo = db();
    $sortState = table_sort_state(ADMIN_ROLES_LIST_SORT_COLUMNS, 'role', 'asc', $filters);

    return $pdo->query(
        'SELECT * FROM dbo.Role ORDER BY ' . table_sort_sql_clause(ADMIN_ROLES_LIST_SORT_SQL, $sortState, 'role', 'role')
    )->fetchAll();
}

function admin_get_role(int $roleId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.Role WHERE RoleID = :id');
    $stmt->execute(['id' => $roleId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function admin_role_options(?int $selectedId = null): array
{
    $roles = admin_list_roles();
    $options = [];

    foreach ($roles as $role) {
        $options[] = [
            'id'       => (int) $role['RoleID'],
            'name'     => (string) $role['RoleName'],
            'selected' => $selectedId !== null && (int) $role['RoleID'] === $selectedId,
        ];
    }

    return $options;
}

function admin_save_user(array $input, ?int $userId = null): array
{
    $userName = trim($input['user_name'] ?? '');
    $userLogin = trim($input['user_login'] ?? '');
    $password = (string) ($input['user_password'] ?? '');
    $roleId = (int) ($input['user_assigned_role'] ?? 0);
    $actorId = auth_user()['UserID'] ?? null;

    if ($userName === '' || $userLogin === '' || $roleId <= 0) {
        return ['ok' => false, 'error' => 'Name, email, and role are required.'];
    }

    if ($userId === null && $password === '') {
        return ['ok' => false, 'error' => 'Password is required for new users.'];
    }

    if (admin_get_role($roleId) === null) {
        return ['ok' => false, 'error' => 'Select a valid role.'];
    }

    $pdo = db();

    $dup = $pdo->prepare('SELECT UserID FROM dbo.[User] WHERE UserLogin = :login AND UserID <> :id');
    $dup->execute(['login' => $userLogin, 'id' => $userId ?? 0]);
    if ($dup->fetch() !== false) {
        return ['ok' => false, 'error' => 'That email is already assigned to another user.'];
    }

    if ($userId === null) {
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.[User] (
                UserName, UserLogin, UserPassword, UserAssignedRole,
                CreateDate, ModifiedDate, LastPasswordReset, Modifiedbyuser
            )
            OUTPUT INSERTED.UserID AS inserted_id
            VALUES (
                :name, :login, :password, :role,
                SYSUTCDATETIME(), SYSUTCDATETIME(), SYSUTCDATETIME(), :modified_by
            )
        SQL);
        $stmt->execute([
            'name'        => $userName,
            'login'       => $userLogin,
            'password'    => $password,
            'role'        => $roleId,
            'modified_by' => $actorId,
        ]);

        $newId = db_fetch_inserted_int($stmt, 'inserted_id');

        require_once __DIR__ . '/audit.php';
        $saved = admin_get_user($newId);
        if ($saved !== null) {
            audit_log_user_save(null, $saved, true);
        }

        return ['ok' => true, 'error' => null, 'id' => $newId];
    }

    $existing = admin_get_user($userId);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    $fields = [
        'name'  => $userName,
        'login' => $userLogin,
        'role'  => $roleId,
        'actor' => $actorId,
        'id'    => $userId,
    ];

    if ($password !== '') {
        $sql = <<<SQL
            UPDATE dbo.[User]
            SET UserName = :name,
                UserLogin = :login,
                UserPassword = :password,
                UserAssignedRole = :role,
                ModifiedDate = SYSUTCDATETIME(),
                LastPasswordReset = SYSUTCDATETIME(),
                Modifiedbyuser = :actor
            WHERE UserID = :id
        SQL;
        $fields['password'] = $password;
    } else {
        $sql = <<<SQL
            UPDATE dbo.[User]
            SET UserName = :name,
                UserLogin = :login,
                UserAssignedRole = :role,
                ModifiedDate = SYSUTCDATETIME(),
                Modifiedbyuser = :actor
            WHERE UserID = :id
        SQL;
    }

    $pdo->prepare($sql)->execute($fields);

    require_once __DIR__ . '/audit.php';
    $saved = admin_get_user($userId);
    if ($saved !== null) {
        audit_log_user_save($existing, $saved, false);
    }

    return ['ok' => true, 'error' => null, 'id' => $userId];
}

function admin_schema_table_exists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare('SELECT OBJECT_ID(:table_name, N\'U\') AS object_id');
    $stmt->execute(['table_name' => $tableName]);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * @return list<string>
 */
function admin_user_delete_blocking_references(PDO $pdo, int $userId): array
{
    $checks = [
        'other user accounts (modified by this user)' => 'SELECT COUNT(*) FROM dbo.[User] WHERE Modifiedbyuser = :id',
        'role records (modified by this user)' => 'SELECT COUNT(*) FROM dbo.Role WHERE ModifiedbyUser = :id',
        'audit log entries' => 'SELECT COUNT(*) FROM dbo.AuditChangeLog WHERE UserID = :id',
        'purchase orders' => 'SELECT COUNT(*) FROM dbo.PurchaseOrder WHERE CreatedByUser = :id OR ModifiedbyUser = :id',
        'suppliers' => 'SELECT COUNT(*) FROM dbo.Supplier WHERE ModifiedbyUser = :id',
        'PO payments' => 'SELECT COUNT(*) FROM dbo.POPayment WHERE CreatedByUser = :id OR ModifiedbyUser = :id',
        'supplier invoices' => 'SELECT COUNT(*) FROM dbo.SupplierInvoice WHERE CreatedByUser = :id OR ModifiedByUser = :id',
        'travel & expense reports' => 'SELECT COUNT(*) FROM dbo.TEReport WHERE EmployeeUserID = :id OR CreatedByUser = :id OR ModifiedByUser = :id',
        'SKU master records' => 'SELECT COUNT(*) FROM dbo.SKUMaster WHERE CreatedByUser = :id OR ModifiedbyUser = :id',
        'facilities' => 'SELECT COUNT(*) FROM dbo.Facility WHERE CreatedByUser = :id OR ModifiedByUser = :id',
        'inventory transactions' => 'SELECT COUNT(*) FROM dbo.InvTxn WHERE CreatedByUser = :id',
        'inventory adjustments' => 'SELECT COUNT(*) FROM dbo.InvAdj WHERE ApprovedByUser = :id OR CreatedByUser = :id',
        'inventory transfers' => 'SELECT COUNT(*) FROM dbo.InvTrf WHERE RequestedByUser = :id OR ModifiedByUser = :id',
        'inventory reclassifications' => 'SELECT COUNT(*) FROM dbo.InvRR WHERE CreatedByUser = :id OR ModifiedByUser = :id',
        'inventory cycle counts' => 'SELECT COUNT(*) FROM dbo.InvCS WHERE CountedByUser = :id OR ApprovedByUser = :id OR CreatedByUser = :id',
        'PO production status updates' => 'SELECT COUNT(*) FROM dbo.POProductionStatus WHERE LastUpdatedByUser = :id',
        'contracts' => 'SELECT COUNT(*) FROM dbo.ContractRegister WHERE InternalOwnerUser = :id OR ModifiedbyUser = :id',
        'QuickBooks connections' => 'SELECT COUNT(*) FROM dbo.QBOConnection WHERE ConnectedByUser = :id',
        'links index entries' => 'SELECT COUNT(*) FROM dbo.LinksIndex WHERE ModifiedbyUser = :id',
        'COA documents' => 'SELECT COUNT(*) FROM dbo.CoaDocument WHERE CreatedByUser = :id OR ModifiedByUser = :id',
        'product enrichment records' => 'SELECT COUNT(*) FROM dbo.ProductEnrichment WHERE CreatedByUser = :id OR ModifiedByUser = :id',
        'process execution log entries' => 'SELECT COUNT(*) FROM dbo.ProcessExecutionLog WHERE TriggeredByUserID = :id',
        'label templates' => 'SELECT COUNT(*) FROM dbo.LabelTemplate WHERE CreatedByUser = :id OR ModifiedbyUser = :id',
        'label template versions' => 'SELECT COUNT(*) FROM dbo.LabelTemplateVersion WHERE ApprovedByUser = :id OR CreatedByUser = :id',
        'label order runs' => 'SELECT COUNT(*) FROM dbo.LabelOrderRun WHERE CreatedByUser = :id OR ModifiedbyUser = :id',
        'batch print orders' => 'SELECT COUNT(*) FROM dbo.BatchPrintOrder WHERE CreatedByUser = :id OR ModifiedbyUser = :id',
        'label compliance reviews' => 'SELECT COUNT(*) FROM dbo.LabelComplianceReview WHERE CreatedByUser = :id',
        'white label production orders' => 'SELECT COUNT(*) FROM dbo.WhiteLabelProductionOrder WHERE CreatedByUser = :id OR ModifiedbyUser = :id',
        'PO attachments' => 'SELECT COUNT(*) FROM dbo.POAttachment WHERE UploadedByUser = :id',
        'PO receiving attachments' => 'SELECT COUNT(*) FROM dbo.PORAttachment WHERE UploadedByUser = :id',
        'PO payment attachments' => 'SELECT COUNT(*) FROM dbo.POPaymentAttachment WHERE UploadedByUser = :id',
        'supplier invoice attachments' => 'SELECT COUNT(*) FROM dbo.SupplierInvoiceAttachment WHERE UploadedByUser = :id',
        'SKU master attachments' => 'SELECT COUNT(*) FROM dbo.SKUMasterAttachment WHERE UploadedByUser = :id',
        'contract attachments' => 'SELECT COUNT(*) FROM dbo.ContractAttachment WHERE UploadedByUser = :id',
        'enhanced log attachments' => 'SELECT COUNT(*) FROM dbo.EnhLogAttachment WHERE UploadedByUser = :id',
        'travel & expense attachments' => 'SELECT COUNT(*) FROM dbo.TEAttachment WHERE UploadedByUser = :id',
    ];

    $blocking = [];
    foreach ($checks as $label => $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $userId]);
            if ((int) $stmt->fetchColumn() > 0) {
                $blocking[] = $label;
            }
        } catch (Throwable $e) {
            // Table may not exist in older environments; skip unavailable checks.
            continue;
        }
    }

    return $blocking;
}

function admin_user_delete_dependent_rows(PDO $pdo, int $userId): void
{
    $deletes = [
        'DELETE FROM dbo.AlertSubscription WHERE UserID = :id',
        'DELETE FROM dbo.PasswordResetToken WHERE UserID = :id',
        'DELETE FROM dbo.ApprovalToken WHERE UserID = :id',
    ];

    foreach ($deletes as $sql) {
        $pdo->prepare($sql)->execute(['id' => $userId]);
    }

    foreach (['dbo.POApprovalToken', 'dbo.InvoicePaymentApprovalToken', 'dbo.TEApprovalToken'] as $legacyTable) {
        if (!admin_schema_table_exists($pdo, $legacyTable)) {
            continue;
        }

        $pdo->prepare("DELETE FROM {$legacyTable} WHERE UserID = :id")->execute(['id' => $userId]);
    }

    if (admin_schema_table_exists($pdo, 'dbo.ApprovalLog')) {
        $pdo->prepare('UPDATE dbo.ApprovalLog SET ApproverUserID = NULL WHERE ApproverUserID = :id')
            ->execute(['id' => $userId]);
    }
}

function admin_delete_user(int $userId): array
{
    $currentUserId = auth_user()['UserID'] ?? null;
    if ($currentUserId !== null && $userId === $currentUserId) {
        return ['ok' => false, 'error' => 'You cannot delete your own account while signed in.'];
    }

    $existing = admin_get_user($userId);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    $pdo = db();

    $blocking = admin_user_delete_blocking_references($pdo, $userId);
    if ($blocking !== []) {
        return [
            'ok' => false,
            'error' => 'This user is referenced on business records and cannot be deleted: '
                . implode(', ', $blocking)
                . '.',
        ];
    }

    try {
        $pdo->beginTransaction();

        admin_user_delete_dependent_rows($pdo, $userId);

        $deleted = $pdo->prepare('DELETE FROM dbo.[User] WHERE UserID = :id');
        $deleted->execute(['id' => $userId]);
        if ($deleted->rowCount() === 0) {
            throw new RuntimeException('User record was not deleted.');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('admin_delete_user failed for UserID ' . $userId . ': ' . $e->getMessage());

        return [
            'ok' => false,
            'error' => 'Unable to delete user. '
                . ($e instanceof PDOException
                    ? 'Database constraint prevented deletion.'
                    : $e->getMessage()),
        ];
    }

    require_once __DIR__ . '/audit.php';
    audit_log_user_delete($existing);

    return ['ok' => true, 'error' => null];
}

function admin_save_role(array $input, ?int $roleId = null): array
{
    $roleName = trim($input['role_name'] ?? '');
    $roleDesc = trim($input['role_desc'] ?? '');
    $actorId = auth_user()['UserID'] ?? null;

    if ($roleName === '') {
        return ['ok' => false, 'error' => 'Role name is required.'];
    }

    $permissions = [];
    foreach (array_keys(ROLE_PERMISSION_FIELDS) as $column) {
        $flags = $input['permissions'][$column] ?? [];
        $value = permission_from_flags(
            !empty($flags['C']),
            !empty($flags['R']),
            !empty($flags['U']),
            !empty($flags['D'])
        );

        if ($value !== null && !permission_is_valid($value)) {
            return ['ok' => false, 'error' => 'One or more permission values are invalid.'];
        }

        $permissions[$column] = $value;
    }

    $pdo = db();

    $dup = $pdo->prepare('SELECT RoleID FROM dbo.Role WHERE RoleName = :name AND RoleID <> :id');
    $dup->execute(['name' => $roleName, 'id' => $roleId ?? 0]);
    if ($dup->fetch() !== false) {
        return ['ok' => false, 'error' => 'A role with that name already exists.'];
    }

    if ($roleId === null) {
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.Role (
                RoleName, RoleDesc, RoleCreateDate, ModifiedbyUser,
                POManagement, POApproval, TEManagement, TEApproval, TEProcessing,
                QBOInsertApproval, PaymentApproval, ProviderAccountReview,
                InventoryReporting, SalesReporting, InventoryForecasting,
                LabelingOperations, OperationsDashboard, LegalAgreements, ProductCatalog, LinksIndex, Support, Accounting,
                UserAdmin, RoleAdmin
            )
            OUTPUT INSERTED.RoleID AS inserted_id
            VALUES (
                :name, :desc, SYSUTCDATETIME(), :modified_by,
                :po, :po_approval, :te_mgmt, :te_approval, :te_processing,
                :qbo_insert_approval, :payment_approval, :provider_review,
                :inv_rep, :sales_rep, :inv_forecast,
                :labeling, :dashboard, :legal, :catalog, :links, :support, :accounting,
                :user_admin, :role_admin
            )
        SQL);
        $stmt->execute([
            'name'                 => $roleName,
            'desc'                   => $roleDesc !== '' ? $roleDesc : null,
            'modified_by'            => $actorId,
            'po'                     => $permissions['POManagement'],
            'po_approval'            => $permissions['POApproval'],
            'te_mgmt'                => $permissions['TEManagement'],
            'te_approval'            => $permissions['TEApproval'],
            'te_processing'          => $permissions['TEProcessing'],
            'qbo_insert_approval'    => $permissions['QBOInsertApproval'],
            'payment_approval'       => $permissions['PaymentApproval'],
            'provider_review'        => $permissions['ProviderAccountReview'],
            'inv_rep'                => $permissions['InventoryReporting'],
            'sales_rep'              => $permissions['SalesReporting'],
            'inv_forecast'           => $permissions['InventoryForecasting'],
            'labeling'               => $permissions['LabelingOperations'],
            'dashboard'              => $permissions['OperationsDashboard'],
            'legal'                  => $permissions['LegalAgreements'],
            'catalog'                => $permissions['ProductCatalog'],
            'links'                  => $permissions['LinksIndex'],
            'support'                => $permissions['Support'],
            'accounting'             => $permissions['Accounting'],
            'user_admin'             => $permissions['UserAdmin'],
            'role_admin'             => $permissions['RoleAdmin'],
        ]);

        $newId = db_fetch_inserted_int($stmt, 'inserted_id');

        require_once __DIR__ . '/audit.php';
        $saved = admin_get_role($newId);
        if ($saved !== null) {
            audit_log_role_save(null, $saved, true);
        }

        return ['ok' => true, 'error' => null, 'id' => $newId];
    }

    $existing = admin_get_role($roleId);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'Role not found.'];
    }

    $stmt = $pdo->prepare(<<<SQL
        UPDATE dbo.Role
        SET RoleName = :name,
            RoleDesc = :desc,
            ModifiedbyUser = :actor,
            POManagement = :po,
            POApproval = :po_approval,
            TEManagement = :te_mgmt,
            TEApproval = :te_approval,
            TEProcessing = :te_processing,
            QBOInsertApproval = :qbo_insert_approval,
            PaymentApproval = :payment_approval,
            ProviderAccountReview = :provider_review,
            InventoryReporting = :inv_rep,
            SalesReporting = :sales_rep,
            InventoryForecasting = :inv_forecast,
            LabelingOperations = :labeling,
            OperationsDashboard = :dashboard,
            LegalAgreements = :legal,
            ProductCatalog = :catalog,
            LinksIndex = :links,
            Support = :support,
            Accounting = :accounting,
            UserAdmin = :user_admin,
            RoleAdmin = :role_admin
        WHERE RoleID = :id
    SQL);
    $stmt->execute([
        'name'          => $roleName,
        'desc'          => $roleDesc !== '' ? $roleDesc : null,
        'actor'         => $actorId,
        'po'                  => $permissions['POManagement'],
        'po_approval'         => $permissions['POApproval'],
        'te_mgmt'             => $permissions['TEManagement'],
        'te_approval'         => $permissions['TEApproval'],
        'te_processing'       => $permissions['TEProcessing'],
        'qbo_insert_approval' => $permissions['QBOInsertApproval'],
        'payment_approval'    => $permissions['PaymentApproval'],
        'provider_review'     => $permissions['ProviderAccountReview'],
        'inv_rep'             => $permissions['InventoryReporting'],
        'sales_rep'     => $permissions['SalesReporting'],
        'inv_forecast'  => $permissions['InventoryForecasting'],
        'labeling'      => $permissions['LabelingOperations'],
        'dashboard'     => $permissions['OperationsDashboard'],
        'legal'         => $permissions['LegalAgreements'],
        'catalog'       => $permissions['ProductCatalog'],
        'links'         => $permissions['LinksIndex'],
        'support'       => $permissions['Support'],
        'accounting'    => $permissions['Accounting'],
        'user_admin'    => $permissions['UserAdmin'],
        'role_admin'    => $permissions['RoleAdmin'],
        'id'            => $roleId,
    ]);

    require_once __DIR__ . '/audit.php';
    $saved = admin_get_role($roleId);
    if ($saved !== null) {
        audit_log_role_save($existing, $saved, false);
    }

    return ['ok' => true, 'error' => null, 'id' => $roleId];
}

function admin_delete_role(int $roleId): array
{
    $existing = admin_get_role($roleId);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'Role not found.'];
    }

    $pdo = db();
    $users = $pdo->prepare('SELECT COUNT(*) FROM dbo.[User] WHERE UserAssignedRole = :id');
    $users->execute(['id' => $roleId]);

    if ((int) $users->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'This role is assigned to one or more users and cannot be deleted.'];
    }

    $pdo->prepare('DELETE FROM dbo.Role WHERE RoleID = :id')->execute(['id' => $roleId]);

    require_once __DIR__ . '/audit.php';
    audit_log_role_delete($existing);

    return ['ok' => true, 'error' => null];
}

function admin_role_permission_summary(array $role): string
{
    $parts = [];
    foreach (ROLE_PERMISSION_FIELDS as $column => $label) {
        $value = $role[$column] ?? null;
        if ($value !== null && $value !== '') {
            $parts[] = $label . ': ' . $value;
        }
    }

    return $parts === [] ? 'No access' : implode(' · ', $parts);
}
