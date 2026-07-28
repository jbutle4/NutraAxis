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
    static $has = null;
    if ($has !== null) {
        return $has;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT COL_LENGTH('dbo.PurchaseOrder', 'LedgerProfile') AS col_len");
        $row = $stmt->fetch();
        $has = $row !== false && $row['col_len'] !== null;
    } catch (Throwable) {
        $has = false;
    }

    return $has;
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
