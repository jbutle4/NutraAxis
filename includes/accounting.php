<?php

require_once __DIR__ . '/auth.php';

const ACCOUNTING_PERMISSION_COLUMN = 'Accounting';

const ACCOUNTING_SECTIONS = [
    'overview' => ['title' => 'Overview', 'href' => '/accounting/'],
    'ap'       => ['title' => 'Accounts Payable', 'href' => '/accounting/ap.php'],
    'ar'       => ['title' => 'Accounts Receivable', 'href' => '/accounting/ar.php'],
    'pos'      => ['title' => 'Purchase Orders', 'href' => '/accounting/pos.php'],
    'inventory'=> ['title' => 'Inventory', 'href' => '/accounting/inventory.php'],
    'suppliers'=> ['title' => 'Suppliers', 'href' => '/accounting/suppliers.php'],
    'accounts' => ['title' => 'Chart of Accounts', 'href' => '/accounting/chart-of-accounts.php'],
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

function accounting_require_read(): void
{
    auth_require_login();
    if (accounting_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Accounting.');
}

function accounting_require_update(): void
{
    accounting_require_read();
    if (accounting_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to manage Accounting settings.');
}

function accounting_format_money($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return '$' . number_format((float) $value, 2);
}

function accounting_format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return $value;
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
