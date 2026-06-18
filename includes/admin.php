<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';

const ROLE_PERMISSION_FIELDS = [
    'POManagement'         => 'PO Management',
    'POApproval'           => 'PO Approval',
    'TEManagement'         => 'Travel & Expense',
    'TEApproval'           => 'T&E Approval',
    'InventoryReporting'   => 'Jazz Current Inventory',
    'SalesReporting'       => 'Sales Reporting',
    'InventoryForecasting' => 'Inventory Forecasting',
    'LabelingOperations'   => 'Labeling Operations',
    'OperationsDashboard'  => 'Operations Dashboard',
    'LegalAgreements'      => 'Legal Agreements & Contracts',
    'ProductCatalog'       => 'Product Catalog / SKU Master',
    'LinksIndex'           => 'Links Index',
    'ContactsList'         => 'Contacts List',
    'Support'              => 'Support',
    'Accounting'           => 'Accounting',
    'UserAdmin'            => 'User Administration',
    'RoleAdmin'            => 'Role Administration',
];

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

function admin_format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($value);

        return $dt->format('M j, Y g:i A');
    } catch (Throwable) {
        return $value;
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
            u.LastPasswordReset,
            u.IsPOApprover,
            u.IsTEApprover,
            u.IsPOProcessor
        FROM dbo.[User] u
        INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
        WHERE u.UserID = :id
    SQL);
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
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
    $isPoApprover = !empty($input['is_po_approver']);
    $isTeApprover = !empty($input['is_te_approver']);
    $isPoProcessor = !empty($input['is_po_processor']);
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
                IsPOApprover, IsTEApprover, IsPOProcessor,
                CreateDate, ModifiedDate, LastPasswordReset, Modifiedbyuser
            )
            OUTPUT INSERTED.UserID AS inserted_id
            VALUES (
                :name, :login, :password, :role,
                :is_po_approver, :is_te_approver, :is_po_processor,
                SYSUTCDATETIME(), SYSUTCDATETIME(), SYSUTCDATETIME(), :modified_by
            )
        SQL);
        $stmt->execute([
            'name'            => $userName,
            'login'           => $userLogin,
            'password'        => $password,
            'role'            => $roleId,
            'is_po_approver'  => $isPoApprover ? 1 : 0,
            'is_te_approver'  => $isTeApprover ? 1 : 0,
            'is_po_processor' => $isPoProcessor ? 1 : 0,
            'modified_by'     => $actorId,
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
        'name'            => $userName,
        'login'           => $userLogin,
        'role'            => $roleId,
        'is_po_approver'  => $isPoApprover ? 1 : 0,
        'is_te_approver'  => $isTeApprover ? 1 : 0,
        'is_po_processor' => $isPoProcessor ? 1 : 0,
        'actor'           => $actorId,
        'id'              => $userId,
    ];

    if ($password !== '') {
        $sql = <<<SQL
            UPDATE dbo.[User]
            SET UserName = :name,
                UserLogin = :login,
                UserPassword = :password,
                UserAssignedRole = :role,
                IsPOApprover = :is_po_approver,
                IsTEApprover = :is_te_approver,
                IsPOProcessor = :is_po_processor,
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
                IsPOApprover = :is_po_approver,
                IsTEApprover = :is_te_approver,
                IsPOProcessor = :is_po_processor,
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

    $refs = $pdo->prepare('SELECT COUNT(*) FROM dbo.[User] WHERE Modifiedbyuser = :id');
    $refs->execute(['id' => $userId]);
    if ((int) $refs->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'This user is referenced as a modifier on other accounts and cannot be deleted.'];
    }

    $roleRefs = $pdo->prepare('SELECT COUNT(*) FROM dbo.Role WHERE ModifiedbyUser = :id');
    $roleRefs->execute(['id' => $userId]);
    if ((int) $roleRefs->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'This user is referenced on role records and cannot be deleted.'];
    }

    $pdo->prepare('DELETE FROM dbo.[User] WHERE UserID = :id')->execute(['id' => $userId]);

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
                POManagement, POApproval, TEManagement, TEApproval,
                InventoryReporting, SalesReporting, InventoryForecasting,
                LabelingOperations, OperationsDashboard, LegalAgreements, ProductCatalog, LinksIndex, ContactsList, Support, Accounting,
                UserAdmin, RoleAdmin
            )
            OUTPUT INSERTED.RoleID AS inserted_id
            VALUES (
                :name, :desc, SYSUTCDATETIME(), :modified_by,
                :po, :po_approval, :te_mgmt, :te_approval,
                :inv_rep, :sales_rep, :inv_forecast,
                :labeling, :dashboard, :legal, :catalog, :links, :contacts, :support, :accounting,
                :user_admin, :role_admin
            )
        SQL);
        $stmt->execute([
            'name'          => $roleName,
            'desc'          => $roleDesc !== '' ? $roleDesc : null,
            'modified_by'   => $actorId,
            'po'            => $permissions['POManagement'],
            'po_approval'   => $permissions['POApproval'],
            'te_mgmt'       => $permissions['TEManagement'],
            'te_approval'   => $permissions['TEApproval'],
            'inv_rep'       => $permissions['InventoryReporting'],
            'sales_rep'     => $permissions['SalesReporting'],
            'inv_forecast'  => $permissions['InventoryForecasting'],
            'labeling'      => $permissions['LabelingOperations'],
            'dashboard'     => $permissions['OperationsDashboard'],
            'legal'         => $permissions['LegalAgreements'],
            'catalog'       => $permissions['ProductCatalog'],
            'links'         => $permissions['LinksIndex'],
            'contacts'      => $permissions['ContactsList'],
            'support'       => $permissions['Support'],
            'accounting'    => $permissions['Accounting'],
            'user_admin'    => $permissions['UserAdmin'],
            'role_admin'    => $permissions['RoleAdmin'],
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
            InventoryReporting = :inv_rep,
            SalesReporting = :sales_rep,
            InventoryForecasting = :inv_forecast,
            LabelingOperations = :labeling,
            OperationsDashboard = :dashboard,
            LegalAgreements = :legal,
            ProductCatalog = :catalog,
            LinksIndex = :links,
            ContactsList = :contacts,
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
        'po'            => $permissions['POManagement'],
        'po_approval'   => $permissions['POApproval'],
        'inv_rep'       => $permissions['InventoryReporting'],
        'sales_rep'     => $permissions['SalesReporting'],
        'inv_forecast'  => $permissions['InventoryForecasting'],
        'labeling'      => $permissions['LabelingOperations'],
        'dashboard'     => $permissions['OperationsDashboard'],
        'legal'         => $permissions['LegalAgreements'],
        'catalog'       => $permissions['ProductCatalog'],
        'links'         => $permissions['LinksIndex'],
        'contacts'      => $permissions['ContactsList'],
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
