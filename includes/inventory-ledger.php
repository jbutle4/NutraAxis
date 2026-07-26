<?php

require_once __DIR__ . '/inventory-reporting.php';
require_once __DIR__ . '/jazz-oms.php';

const INVENTORY_LEDGER_LIST_SORT_COLUMNS = [
    'sku'           => 'SKU',
    'facility'      => 'Facility',
    'qty_ok'        => 'OK',
    'qty_quarantine'=> 'Quarantine',
    'qty_on_hold'   => 'On hold',
    'qty_destroy'   => 'Destroy',
    'qty_reserved'  => 'Reserved',
    'qty_on_hand'   => 'On hand',
    'qty_available' => 'Available',
];

const INVENTORY_LEDGER_LIST_SORT_SQL = [
    'sku'           => 'b.SKUCode',
    'facility'      => 'b.FacilityCode',
    'qty_ok'        => 'b.QtyOK',
    'qty_quarantine'=> 'b.QtyQuarantine',
    'qty_on_hold'   => 'b.QtyOnHold',
    'qty_destroy'   => 'b.QtyDestroy',
    'qty_reserved'  => 'b.QtyReserved',
    'qty_on_hand'   => 'b.QtyOnHand',
    'qty_available' => 'b.QtyAvailable',
];

const INVENTORY_LEDGER_LIST_SORT_NUMERIC = [
    'qty_ok',
    'qty_quarantine',
    'qty_on_hold',
    'qty_destroy',
    'qty_reserved',
    'qty_on_hand',
    'qty_available',
];

function inventory_ledger_permission_value(): ?string
{
    return inventory_reporting_permission_value();
}

function inventory_ledger_can_read(): bool
{
    return inventory_reporting_can_read();
}

function inventory_ledger_require_read(): void
{
    auth_require_login();
    if (inventory_ledger_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Inventory Balances.');
}

function inventory_ledger_list_facilities(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT
            FacilityID,
            FacilityCode,
            FacilityName,
            FacilityType,
            IsActive,
            IsMothership,
            ReceivesPurchaseOrders,
            IntegrationMode
        FROM dbo.Facility
        ORDER BY IsActive DESC, FacilityCode ASC
    SQL);

    return $stmt->fetchAll();
}

function inventory_ledger_list_balances(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            b.BalanceID,
            b.SKUCode,
            b.FacilityCode,
            f.FacilityName,
            f.IsActive AS FacilityIsActive,
            b.QtyOK,
            b.QtyQuarantine,
            b.QtyOnHold,
            b.QtyDestroy,
            b.QtyReserved,
            b.QtyOnHand,
            b.QtyAvailable,
            b.LastTransactionID,
            b.LastCountDate,
            b.LastUpdated
        FROM dbo.InvCurrentBalance b
        INNER JOIN dbo.Facility f ON f.FacilityCode = b.FacilityCode
    SQL;

    $params = [];
    $where = [];

    $facility = trim((string) ($filters['facility'] ?? ''));
    if ($facility !== '') {
        $where[] = 'b.FacilityCode = :facility';
        $params['facility'] = $facility;
    }

    $sku = trim((string) ($filters['sku'] ?? ''));
    if ($sku !== '') {
        $where[] = 'b.SKUCode LIKE :sku';
        $params['sku'] = '%' . str_replace(['%', '_'], ['[%]', '[_]'], $sku) . '%';
    }

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sortState = table_sort_state(
        INVENTORY_LEDGER_LIST_SORT_COLUMNS,
        'sku',
        'asc',
        $filters
    );
    $sql .= ' ORDER BY ' . table_sort_sql_clause(
        INVENTORY_LEDGER_LIST_SORT_SQL,
        $sortState,
        'sku',
        'b.SKUCode'
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function inventory_ledger_qty_on_hand(array $row): float
{
    if (isset($row['QtyOnHand'])) {
        return (float) $row['QtyOnHand'];
    }

    return (float) ($row['QtyOK'] ?? 0)
        + (float) ($row['QtyQuarantine'] ?? 0)
        + (float) ($row['QtyOnHold'] ?? 0)
        + (float) ($row['QtyDestroy'] ?? 0);
}

function inventory_ledger_qty_available(array $row): float
{
    if (isset($row['QtyAvailable'])) {
        return (float) $row['QtyAvailable'];
    }

    return (float) ($row['QtyOK'] ?? 0) - (float) ($row['QtyReserved'] ?? 0);
}

function inventory_ledger_qbo_qty_for_sku(string $skuCode): float
{
    static $cache = [];

    $skuCode = trim($skuCode);
    if ($skuCode === '') {
        return 0.0;
    }

    if (array_key_exists($skuCode, $cache)) {
        return $cache[$skuCode];
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            SUM(b.QtyOK + b.QtyQuarantine + b.QtyOnHold) AS QtyForQbo
        FROM dbo.InvCurrentBalance b
        WHERE b.SKUCode = :sku
    SQL);
    $stmt->execute(['sku' => $skuCode]);
    $row = $stmt->fetch();

    $qty = $row === false ? 0.0 : (float) ($row['QtyForQbo'] ?? 0);
    $cache[$skuCode] = $qty;

    return $qty;
}

function inventory_ledger_format_quantity($value): string
{
    return jazz_oms_format_quantity($value);
}
