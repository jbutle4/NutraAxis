<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/data-profile.php';
require_once __DIR__ . '/jazz-oms.php';

const PO_LEDGER_PROFILE_PRODUCTION = 'production';
const PO_LEDGER_PROFILE_UAT = 'uat';

function po_normalize_ledger_profile(?string $value): string
{
    $normalized = strtolower(trim((string) $value));

    return $normalized === PO_LEDGER_PROFILE_UAT ? PO_LEDGER_PROFILE_UAT : PO_LEDGER_PROFILE_PRODUCTION;
}

function po_ledger_profile_label(string $profile): string
{
    return po_normalize_ledger_profile($profile) === PO_LEDGER_PROFILE_UAT ? 'UAT' : 'Production';
}

function po_ledger_profile_badge_class(string $profile): string
{
    return po_normalize_ledger_profile($profile) === PO_LEDGER_PROFILE_UAT
        ? 'status-badge status-uat'
        : 'status-badge status-production';
}

/**
 * Ledger profile stored on a purchase order row (defaults production when column absent).
 */
function po_order_ledger_profile(array $order): string
{
    return po_normalize_ledger_profile($order['LedgerProfile'] ?? PO_LEDGER_PROFILE_PRODUCTION);
}

function po_has_ledger_profile_column(): bool
{
    return procurement_table_has_ledger_profile('PurchaseOrder');
}

function supplier_invoice_has_ledger_profile_column(): bool
{
    return procurement_table_has_ledger_profile('SupplierInvoice');
}

function po_payment_has_ledger_profile_column(): bool
{
    return procurement_table_has_ledger_profile('POPayment');
}

function procurement_table_has_ledger_profile(string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $allowed = ['PurchaseOrder', 'SupplierInvoice', 'POPayment'];
    if (!in_array($table, $allowed, true)) {
        return false;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT COL_LENGTH('dbo." . $table . "', 'LedgerProfile') AS col_len");
        $row = $stmt->fetch();
        $cache[$table] = $row !== false && $row['col_len'] !== null;
    } catch (Throwable) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function procurement_page_ledger_profile(): string
{
    return data_profile_is_uat() ? PO_LEDGER_PROFILE_UAT : PO_LEDGER_PROFILE_PRODUCTION;
}

function procurement_row_ledger_profile(array $row): string
{
    return po_normalize_ledger_profile($row['LedgerProfile'] ?? PO_LEDGER_PROFILE_PRODUCTION);
}

/**
 * Resolve ledger profile for a new or updated procurement row.
 */
function procurement_resolve_ledger_profile(
    ?string $explicit,
    ?array $purchaseOrder = null,
    ?array $supplierInvoice = null,
    ?array $existing = null,
    ?string $pageFallback = null
): string {
    if ($existing !== null && isset($existing['LedgerProfile'])) {
        return procurement_row_ledger_profile($existing);
    }

    if ($explicit !== null && trim($explicit) !== '') {
        return po_normalize_ledger_profile($explicit);
    }

    if ($purchaseOrder !== null) {
        return po_order_ledger_profile($purchaseOrder);
    }

    if ($supplierInvoice !== null) {
        return procurement_row_ledger_profile($supplierInvoice);
    }

    if ($pageFallback !== null) {
        return po_normalize_ledger_profile($pageFallback);
    }

    return PO_LEDGER_PROFILE_PRODUCTION;
}

/**
 * Append a ledger profile predicate when the column exists.
 */
function procurement_append_ledger_profile_filter(
    string &$sql,
    array &$params,
    array $filters,
    string $columnSql,
    bool $hasColumn,
    ?string $defaultProfile = PO_LEDGER_PROFILE_PRODUCTION
): void {
    if (!$hasColumn) {
        return;
    }

    $ledgerFilter = strtolower(trim((string) ($filters['ledger_profile'] ?? $defaultProfile ?? '')));
    if ($ledgerFilter === 'all') {
        return;
    }

    if ($ledgerFilter === '' && $defaultProfile === null) {
        return;
    }

    if ($ledgerFilter === '') {
        $ledgerFilter = (string) $defaultProfile;
    }

    $sql .= ' AND ' . $columnSql . ' = :ledger_profile';
    $params['ledger_profile'] = po_normalize_ledger_profile($ledgerFilter);
}

/**
 * Bind Jazz OMS, data profile, and QuickBooks to a procurement ledger profile.
 */
function procurement_bind_ledger_profile(string $profile): void
{
    require_once __DIR__ . '/accounting.php';

    $uat = po_normalize_ledger_profile($profile) === PO_LEDGER_PROFILE_UAT;
    data_profile_set($uat ? 'uat' : 'production');
    jazz_oms_use_environment($uat ? 'uat' : 'production');
    accounting_bind_qbo_environment();
}
