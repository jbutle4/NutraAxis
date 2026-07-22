<?php

require_once __DIR__ . '/auth.php';

const ACCOUNTING_PERMISSION_COLUMN = 'Accounting';

const ACCOUNTING_SECTIONS = [
    'overview'              => ['title' => 'Overview', 'href' => '/accounting/'],
    'invoices'              => ['title' => 'Supplier Invoices', 'href' => '/accounting/supplier-invoices/'],
    'invoice-payments'      => ['title' => 'Invoice Payments', 'href' => '/accounting/invoice-payments/'],
    'ap'                    => ['title' => 'Accounts Payable', 'href' => '/accounting/ap.php'],
    'ar'                    => ['title' => 'Accounts Receivable', 'href' => '/accounting/ar.php'],
    'pos'                   => ['title' => 'Purchase Orders', 'href' => '/accounting/pos.php'],
    'inventory'             => ['title' => 'QBO SKU Master', 'href' => '/accounting/inventory.php'],
    'suppliers'             => ['title' => 'Suppliers', 'href' => '/accounting/suppliers.php'],
    'accounts'              => ['title' => 'Chart of Accounts', 'href' => '/accounting/chart-of-accounts.php'],
    'procurement-approvals' => ['title' => 'Approvals Queue', 'href' => '/procurement-approvals/'],
];

function accounting_permission_value(): ?string
{
    return auth_permission_value(ACCOUNTING_PERMISSION_COLUMN);
}

function accounting_can_read(): bool
{
    return auth_can_read(ACCOUNTING_PERMISSION_COLUMN);
}

function accounting_can_create(): bool
{
    return auth_can_create(ACCOUNTING_PERMISSION_COLUMN);
}

function accounting_can_update(): bool
{
    return auth_can_update(ACCOUNTING_PERMISSION_COLUMN);
}

function accounting_can_delete(): bool
{
    return auth_can_delete(ACCOUNTING_PERMISSION_COLUMN);
}

function accounting_require_create(): void
{
    accounting_require_read();
    if (accounting_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create Accounting records.');
}

function accounting_require_update(): void
{
    accounting_require_read();
    if (accounting_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to manage Accounting records.');
}

function accounting_require_delete(): void
{
    accounting_require_read();
    if (accounting_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete Accounting records.');
}

function accounting_require_read(): void
{
    auth_require_login();
    if (accounting_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Accounting.');
}

function accounting_format_money($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return '$' . number_format((float) $value, 2);
}

/**
 * Base path for Accounting modules that have Production + UAT twins.
 */
function accounting_module_base(string $module): string
{
    $module = trim($module, '/');
    $production = '/accounting/' . $module;
    if (!function_exists('data_profile_page_path')) {
        return $production . '/';
    }

    return rtrim(data_profile_page_path($production . '/'), '/') . '/';
}

/**
 * Profile-aware Accounting URL (Production path in, UAT twin out when on a UAT page).
 * Absolute routes outside /accounting/ (e.g. /procurement-approvals/) are left unchanged.
 */
function accounting_path(string $path): string
{
    $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
    if ($path !== '/accounting' && !str_starts_with($path, '/accounting/')) {
        // Top-level app folders must not be rewritten under /accounting/.
        $top = explode('/', trim($path, '/'), 2)[0] ?? '';
        if ($top !== '' && is_dir(dirname(__DIR__) . '/' . $top)) {
            if (!function_exists('data_profile_page_path')) {
                return $path;
            }

            return data_profile_page_path($path);
        }
        $path = '/accounting/' . ltrim($path, '/');
    }
    if (!function_exists('data_profile_page_path')) {
        return $path;
    }

    return data_profile_page_path($path);
}

/**
 * Bind the request to the QBO company matching the page data profile.
 */
function accounting_bind_qbo_environment(): void
{
    require_once __DIR__ . '/quickbooks.php';
    require_once __DIR__ . '/data-profile.php';

    qbo_use_environment(data_profile_is_uat() ? QBO_ENV_SANDBOX : QBO_ENV_PRODUCTION);
}

function accounting_format_date(DateTimeInterface|string|null $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        if ($value instanceof DateTimeInterface) {
            return $value->format('M j, Y');
        }

        return (new DateTimeImmutable((string) $value))->format('M j, Y');
    } catch (Throwable) {
        return is_scalar($value) ? (string) $value : '—';
    }
}

function accounting_ref_name(?array $ref): string
{
    if ($ref === null) {
        return '—';
    }

    $name = trim((string) ($ref['name'] ?? ''));

    return $name !== '' ? $name : '—';
}

function accounting_skumaster_qbo_links(): array
{
    try {
        $pdo = db();
        $stmt = $pdo->query(<<<SQL
            SELECT SKUID, SKUCode, ProductName, QBO_ItemID, QBO_SyncStatus
            FROM dbo.SKUMaster
        SQL);
        $bySkuCode = [];
        $byQboItemId = [];
        foreach ($stmt->fetchAll() as $row) {
            $skuCode = trim((string) ($row['SKUCode'] ?? ''));
            if ($skuCode !== '') {
                $bySkuCode[strtoupper($skuCode)] = $row;
            }

            $qboItemId = trim((string) ($row['QBO_ItemID'] ?? ''));
            if ($qboItemId !== '') {
                $byQboItemId[$qboItemId] = $row;
            }
        }

        return ['by_sku_code' => $bySkuCode, 'by_qbo_item_id' => $byQboItemId];
    } catch (Throwable) {
        return ['by_sku_code' => [], 'by_qbo_item_id' => []];
    }
}

function accounting_match_skumaster_for_qbo_item(array $item, array $links): ?array
{
    $qboItemId = trim((string) ($item['Id'] ?? ''));
    if ($qboItemId !== '' && isset($links['by_qbo_item_id'][$qboItemId])) {
        return $links['by_qbo_item_id'][$qboItemId];
    }

    $skuCode = trim((string) ($item['Sku'] ?? ''));
    if ($skuCode !== '') {
        $match = $links['by_sku_code'][strtoupper($skuCode)] ?? null;

        return is_array($match) ? $match : null;
    }

    return null;
}
