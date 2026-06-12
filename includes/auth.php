<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/permissions.php';

const AUTH_SESSION_KEY = 'nutraaxis_ops_user';

const MODULE_PERMISSION_COLUMNS = [
    'po-management'          => 'POManagement',
    'inventory-reporting'        => 'InventoryReporting',
    'jazz-item-master'           => 'InventoryReporting',
    'accs-inventory-reporting'   => 'InventoryReporting',
    'inventory-reconciliation'   => 'InventoryReporting',
    'sales-reporting'        => 'SalesReporting',
    'accs-order-report'      => 'SalesReporting',
    'sales-daily-summary'    => 'SalesReporting',
    'sales-monthly-summary'  => 'SalesReporting',
    'inventory-forecasting'  => 'InventoryForecasting',
    'labeling-operations'    => 'LabelingOperations',
    'operations-dashboard'          => 'OperationsDashboard',
    'system-performance-dashboard'  => 'OperationsDashboard',
    'process-log'                   => 'OperationsDashboard',
    'site-documentation'            => 'OperationsDashboard',
    'enhancement-log'               => 'OperationsDashboard',
    'legal-agreements'       => 'LegalAgreements',
    'product-catalog'        => 'ProductCatalog',
    'links-index'            => 'LinksIndex',
    'support'                => 'Support',
    'accounting'             => 'Accounting',
    'supplier-management'    => 'POManagement',
    'po-payments'            => 'POManagement',
    'po-receiving'           => 'POManagement',
    'jazz-asns'              => 'POManagement',
    'delivery-scheduling-log'=> 'POManagement',
];

const ADMIN_PERMISSION_COLUMNS = [
    'users' => 'UserAdmin',
    'roles' => 'RoleAdmin',
];

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function auth_permissions_from_role_row(array $row): array
{
    return [
        'POManagement'         => $row['POManagement'],
        'InventoryReporting'   => $row['InventoryReporting'],
        'SalesReporting'       => $row['SalesReporting'],
        'InventoryForecasting' => $row['InventoryForecasting'],
        'LabelingOperations'   => $row['LabelingOperations'],
        'OperationsDashboard'  => $row['OperationsDashboard'],
        'LegalAgreements'      => $row['LegalAgreements'],
        'ProductCatalog'       => $row['ProductCatalog'],
        'LinksIndex'           => $row['LinksIndex'],
        'Support'              => $row['Support'],
        'Accounting'           => $row['Accounting'],
        'UserAdmin'            => $row['UserAdmin'],
        'RoleAdmin'            => $row['RoleAdmin'],
        'POApproval'           => $row['POApproval'],
    ];
}

function auth_refresh_permissions(): void
{
    auth_start_session();
    $user = $_SESSION[AUTH_SESSION_KEY] ?? null;
    if (!is_array($user) || empty($user['UserAssignedRole'])) {
        return;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT
                RoleName,
                POManagement,
                InventoryReporting,
                SalesReporting,
                InventoryForecasting,
                LabelingOperations,
                OperationsDashboard,
                LegalAgreements,
                ProductCatalog,
                LinksIndex,
                Support,
                Accounting,
                UserAdmin,
                RoleAdmin,
                POApproval
            FROM dbo.Role
            WHERE RoleID = :role_id
        SQL);
        $stmt->execute(['role_id' => (int) $user['UserAssignedRole']]);
        $row = $stmt->fetch();

        if ($row === false) {
            return;
        }

        $_SESSION[AUTH_SESSION_KEY]['RoleName'] = (string) $row['RoleName'];
        $_SESSION[AUTH_SESSION_KEY]['permissions'] = auth_permissions_from_role_row($row);
    } catch (Throwable) {
        // Keep cached session permissions when the database is unreachable.
        return;
    }
}

function auth_user(): ?array
{
    auth_start_session();
    $user = $_SESSION[AUTH_SESSION_KEY] ?? null;

    return is_array($user) ? $user : null;
}

function auth_is_logged_in(): bool
{
    return auth_user() !== null;
}

function auth_permission_value(string $column): ?string
{
    $user = auth_user();
    if ($user === null) {
        return null;
    }

    $value = $user['permissions'][$column] ?? null;

    return ($value === null || $value === '') ? null : (string) $value;
}

function auth_can_read(string $column): bool
{
    return permission_can_read(auth_permission_value($column));
}

function auth_can_create(string $column): bool
{
    return permission_can_create(auth_permission_value($column));
}

function auth_can_update(string $column): bool
{
    return permission_can_update(auth_permission_value($column));
}

function auth_can_delete(string $column): bool
{
    return permission_can_delete(auth_permission_value($column));
}

function auth_admin_column(string $area): string
{
    return ADMIN_PERMISSION_COLUMNS[$area] ?? '';
}

function auth_require_admin_read(string $area): void
{
    auth_require_login();
    $column = auth_admin_column($area);

    if ($column !== '' && auth_can_read($column)) {
        return;
    }

    auth_render_access_denied('You do not have permission to view this admin area.');
}

function auth_require_admin_create(string $area): void
{
    auth_require_admin_read($area);
    $column = auth_admin_column($area);

    if ($column !== '' && auth_can_create($column)) {
        return;
    }

    auth_render_access_denied('You do not have permission to create records in this admin area.');
}

function auth_require_admin_update(string $area): void
{
    auth_require_admin_read($area);
    $column = auth_admin_column($area);

    if ($column !== '' && auth_can_update($column)) {
        return;
    }

    auth_render_access_denied('You do not have permission to update records in this admin area.');
}

function auth_require_admin_delete(string $area): void
{
    auth_require_admin_read($area);
    $column = auth_admin_column($area);

    if ($column !== '' && auth_can_delete($column)) {
        return;
    }

    auth_render_access_denied('You do not have permission to delete records in this admin area.');
}

function auth_can_read_leaf_module(string $slug): bool
{
    if ($slug === 'po-management') {
        require_once __DIR__ . '/po.php';

        return po_can_access_po_pages();
    }

    $column = MODULE_PERMISSION_COLUMNS[$slug] ?? null;
    if ($column === null) {
        return false;
    }

    return auth_can_read($column);
}

function auth_can_read_module(string $slug): bool
{
    if ($slug === 'inventory-management') {
        if (!function_exists('app_inventory_submodule_slugs')) {
            return false;
        }

        foreach (app_inventory_submodule_slugs() as $child) {
            if (auth_can_read_leaf_module($child)) {
                return true;
            }
        }

        return false;
    }

    if ($slug === 'sales-reporting') {
        if (!function_exists('app_sales_submodule_slugs')) {
            return false;
        }

        foreach (app_sales_submodule_slugs() as $child) {
            if (auth_can_read_leaf_module($child)) {
                return true;
            }
        }

        return false;
    }

    return auth_can_read_leaf_module($slug);
}

function auth_filter_inventory_submodules(array $submodules): array
{
    if (!auth_is_logged_in()) {
        return $submodules;
    }

    return array_values(array_filter(
        $submodules,
        fn(array $item): bool => auth_can_read_leaf_module($item['slug'])
    ));
}

function auth_filter_sales_submodules(array $submodules): array
{
    if (!auth_is_logged_in()) {
        return $submodules;
    }

    return array_values(array_filter(
        $submodules,
        fn(array $item): bool => auth_can_read_leaf_module($item['slug'])
    ));
}

function auth_inventory_nav_active(?string $activeSlug): bool
{
    if ($activeSlug === null || $activeSlug === '') {
        return false;
    }

    if ($activeSlug === 'inventory-management') {
        return true;
    }

    if (!function_exists('app_inventory_submodule_slugs')) {
        return false;
    }

    return in_array($activeSlug, app_inventory_submodule_slugs(), true);
}

function auth_sales_nav_active(?string $activeSlug): bool
{
    if ($activeSlug === null || $activeSlug === '') {
        return false;
    }

    if ($activeSlug === 'sales-reporting') {
        return true;
    }

    if (!function_exists('app_sales_submodule_slugs')) {
        return false;
    }

    return in_array($activeSlug, app_sales_submodule_slugs(), true);
}

function auth_can_access_site_admin(): bool
{
    return auth_can_read(ADMIN_PERMISSION_COLUMNS['users'])
        || auth_can_read(ADMIN_PERMISSION_COLUMNS['roles']);
}

function auth_can_access_my_account(): bool
{
    return auth_is_logged_in();
}

function auth_safe_redirect(string $path): string
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        return '/';
    }

    return $path;
}

function auth_login_url(?string $redirect = null): string
{
    $url = '/login/';
    if ($redirect !== null && $redirect !== '' && $redirect !== '/') {
        $url .= '?redirect=' . rawurlencode(auth_safe_redirect($redirect));
    }

    return $url;
}

function auth_require_login(): void
{
    if (auth_is_logged_in()) {
        return;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    $query = parse_url($requestUri, PHP_URL_QUERY);
    $redirect = $path . ($query ? '?' . $query : '');

    header('Location: ' . auth_login_url($redirect), true, 302);
    exit;
}

function auth_require_module_read(string $slug): void
{
    auth_require_login();

    if (auth_can_read_module($slug)) {
        return;
    }

    auth_render_access_denied('You do not have permission to view this module.');
}

function auth_require_site_admin(): void
{
    auth_require_login();

    if (auth_can_access_site_admin()) {
        return;
    }

    auth_render_access_denied('You do not have permission to access Site Admin.');
}

function auth_require_my_account(): void
{
    auth_require_login();
}

function auth_render_access_denied(string $message): void
{
    http_response_code(403);
    $pageTitle = 'Access Denied | NutraAxis Operations';
    $pageDescription = $message;
    $accessDeniedMessage = $message;

    require __DIR__ . '/head.php';
    require __DIR__ . '/header.php';
    require __DIR__ . '/access-denied.php';
    require __DIR__ . '/footer.php';
    exit;
}

function auth_filter_modules(array $modules): array
{
    if (!auth_is_logged_in()) {
        return $modules;
    }

    return array_values(array_filter(
        $modules,
        fn(array $item): bool => auth_can_read_module($item['slug'])
    ));
}

function auth_filter_account_links(array $links): array
{
    if (!auth_is_logged_in()) {
        return array_values(array_filter(
            $links,
            fn(array $link): bool => ($link['class'] ?? '') !== 'nav-logout'
        ));
    }

    $filtered = [];
    foreach ($links as $link) {
        if ($link['title'] === 'Site Admin' && !auth_can_access_site_admin()) {
            continue;
        }
        $filtered[] = $link;
    }

    return $filtered;
}

function auth_attempt_login(string $login, string $password): array
{
    $login = trim($login);
    if ($login === '' || $password === '') {
        return ['ok' => false, 'error' => 'Enter your email and password.'];
    }

    try {
        $pdo = db();
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Unable to connect to the database. Please try again later.'];
    }

    $sql = <<<SQL
        SELECT
            u.UserID,
            u.UserName,
            u.UserLogin,
            u.UserPassword,
            u.UserAssignedRole,
            r.RoleID,
            r.RoleName,
            r.POManagement,
            r.InventoryReporting,
            r.SalesReporting,
            r.InventoryForecasting,
            r.LabelingOperations,
            r.OperationsDashboard,
            r.LegalAgreements,
            r.ProductCatalog,
            r.LinksIndex,
            r.Support,
            r.Accounting,
            r.UserAdmin,
            r.RoleAdmin,
            r.POApproval
        FROM dbo.[User] u
        INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
        WHERE u.UserLogin = :login
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['login' => $login]);
    $row = $stmt->fetch();

    if ($row === false || !hash_equals((string) $row['UserPassword'], $password)) {
        return ['ok' => false, 'error' => 'Invalid email or password.'];
    }

    auth_start_session();
    session_regenerate_id(true);

    $_SESSION[AUTH_SESSION_KEY] = [
        'UserID'          => (int) $row['UserID'],
        'UserName'        => (string) $row['UserName'],
        'UserLogin'       => (string) $row['UserLogin'],
        'UserAssignedRole'=> (int) $row['UserAssignedRole'],
        'RoleName'        => (string) $row['RoleName'],
        'permissions'     => auth_permissions_from_role_row($row),
    ];

    $update = $pdo->prepare('UPDATE dbo.[User] SET LastLoginDate = SYSUTCDATETIME() WHERE UserID = :id');
    $update->execute(['id' => (int) $row['UserID']]);

    return ['ok' => true, 'error' => null];
}

function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function auth_module_permission_label(string $slug): string
{
    $column = MODULE_PERMISSION_COLUMNS[$slug] ?? null;
    if ($column === null) {
        return 'No access';
    }

    return permission_label(auth_permission_value($column));
}
