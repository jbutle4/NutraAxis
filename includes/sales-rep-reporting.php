<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/data-profile.php';
require_once __DIR__ . '/sales-reporting.php';

const SALES_REP_REPORTING_SORT_COLUMNS = [
    'order_id'       => 'Order ID',
    'date'           => 'Date',
    'amount'         => 'Amount',
    'item_subtotal'  => 'Item subtotal',
    'status'         => 'Status',
    'sales_rep'      => 'Sales rep',
    'company_name'   => 'Company name',
    'company_id'     => 'Company ID',
    'company_state'  => 'Company state',
    'company_zip'    => 'Company zip',
    'purchaser'      => 'Purchaser',
    'ship_state'     => 'Ship-to state',
    'ship_zip'       => 'Ship-to zip',
];

const SALES_REP_REPORTING_SORT_NUMERIC = [
    'amount',
    'item_subtotal',
    'company_id',
];

/** @var list<int> */
const SALES_REP_REPORTING_PAGE_SIZES = [25, 50, 100, 250, 500];

const SALES_REP_REPORTING_DEFAULT_PAGE_SIZE = 50;

function sales_rep_reporting_page_size(mixed $value): int
{
    $size = (int) $value;
    if (in_array($size, SALES_REP_REPORTING_PAGE_SIZES, true)) {
        return $size;
    }

    return SALES_REP_REPORTING_DEFAULT_PAGE_SIZE;
}

function sales_rep_reporting_source_environment(): string
{
    return data_profile_is_uat() ? 'stage' : 'production';
}

function sales_rep_reporting_can_refresh(): bool
{
    return auth_can_update(SALES_REPORTING_PERMISSION_COLUMN);
}

function sales_rep_reporting_require_refresh(): void
{
    sales_reporting_require_read();
    if (sales_rep_reporting_can_refresh()) {
        return;
    }
    auth_render_access_denied('You do not have permission to refresh Sales Rep Reporting data.');
}

function sales_rep_reporting_normalize_state(?string $value): string
{
    return strtoupper(trim((string) $value));
}

function sales_rep_reporting_normalize_zip(mixed $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    if (strcasecmp($raw, 'All') === 0) {
        return 'All';
    }
    if (preg_match('/^\d+(\.0+)?$/', $raw) === 1) {
        $digits = (string) (int) $raw;
        return strlen($digits) < 5 ? str_pad($digits, 5, '0', STR_PAD_LEFT) : $digits;
    }

    return $raw;
}

/**
 * @return array<string, array{by_zip: array<string, string>, all_rep: string}>
 */
function sales_rep_reporting_territory_index(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $stmt = db()->query(<<<SQL
        SELECT State, ZipCode, Rep
        FROM dbo.SalesTeamTerritoryAssignments
        ORDER BY State, ZipCode, County
    SQL);

    $index = [];
    while ($row = $stmt->fetch()) {
        $state = sales_rep_reporting_normalize_state($row['State'] ?? '');
        $zip = sales_rep_reporting_normalize_zip($row['ZipCode'] ?? '');
        $rep = trim((string) ($row['Rep'] ?? ''));
        if ($state === '' || $rep === '') {
            continue;
        }
        if (!isset($index[$state])) {
            $index[$state] = ['by_zip' => [], 'all_rep' => ''];
        }
        if ($zip === 'All') {
            if ($index[$state]['all_rep'] === '') {
                $index[$state]['all_rep'] = $rep;
            }
            continue;
        }
        if ($zip !== '' && !isset($index[$state]['by_zip'][$zip])) {
            $index[$state]['by_zip'][$zip] = $rep;
        }
    }

    $cache = $index;

    return $cache;
}

function sales_rep_reporting_lookup_rep(string $state, string $zip): string
{
    $state = sales_rep_reporting_normalize_state($state);
    if ($state === '') {
        return '';
    }
    $index = sales_rep_reporting_territory_index();
    if (!isset($index[$state])) {
        return '';
    }
    $zip = sales_rep_reporting_normalize_zip($zip);
    if ($zip !== '' && $zip !== 'All' && isset($index[$state]['by_zip'][$zip])) {
        return $index[$state]['by_zip'][$zip];
    }

    return $index[$state]['all_rep'] ?? '';
}

function sales_rep_reporting_last_synced_at(?string $sourceEnvironment = null): ?string
{
    $env = $sourceEnvironment ?? sales_rep_reporting_source_environment();
    $stmt = db()->prepare(<<<SQL
        SELECT COALESCE(MAX(LastSyncedAt), MAX(ImportedAt)) AS LastSyncedAt
        FROM dbo.AccsSalesOrderHeader
        WHERE SourceEnvironment = :env
          AND AccsEntityId < 500000
    SQL);
    $stmt->execute(['env' => $env]);
    $value = $stmt->fetchColumn();

    return $value !== false && $value !== null && $value !== '' ? (string) $value : null;
}

/**
 * @param array<string, mixed> $filters
 * @return list<array<string, mixed>>
 */
function sales_rep_reporting_list(array $filters = []): array
{
    $env = sales_rep_reporting_source_environment();
    $rawLimit = (int) ($filters['limit'] ?? SALES_REP_REPORTING_DEFAULT_PAGE_SIZE);
    if (!empty($filters['for_export'])) {
        $limit = max(1, min(5000, $rawLimit > 0 ? $rawLimit : 5000));
    } else {
        $limit = sales_rep_reporting_page_size($rawLimit);
    }
    $q = trim((string) ($filters['q'] ?? ''));
    $repFilter = trim((string) ($filters['rep'] ?? ''));
    $statusFilter = trim((string) ($filters['status'] ?? ''));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));

    $sql = <<<SQL
        SELECT TOP ({$limit})
            h.IncrementId,
            h.AccsEntityId,
            h.OrderCreatedAt,
            h.OrderStatus,
            h.GrandTotal,
            h.Subtotal,
            h.TaxAmount,
            h.ShippingAmount,
            h.DiscountAmount,
            h.CustomerFirstName,
            h.CustomerLastName,
            h.BillCompany,
            h.ShipCompany,
            h.BillRegionCode,
            h.BillPostcode,
            h.ShipRegionCode,
            h.ShipRegion,
            h.ShipPostcode,
            h.LastSyncedAt,
            TRY_CONVERT(int, COALESCE(
                JSON_VALUE(h.RawPayloadJson, '$.extension_attributes.company_order_attributes.company_id'),
                JSON_VALUE(h.RawPayloadJson, '$.extension_attributes.company_id'),
                JSON_VALUE(h.RawPayloadJson, '$.extension_attributes.company.id')
            )) AS CompanyId,
            NULLIF(LTRIM(RTRIM(JSON_VALUE(
                h.RawPayloadJson,
                '$.extension_attributes.company_order_attributes.company_name'
            ))), N'') AS PayloadCompanyName
        FROM dbo.AccsSalesOrderHeader h
        WHERE h.SourceEnvironment = :env
          AND h.AccsEntityId < 500000
    SQL;
    $params = ['env' => $env];

    if ($statusFilter !== '') {
        $sql .= ' AND h.OrderStatus = :status';
        $params['status'] = $statusFilter;
    }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
        $sql .= ' AND h.OrderCreatedAt >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
        $sql .= ' AND h.OrderCreatedAt < DATEADD(day, 1, CAST(:date_to AS datetime2))';
        $params['date_to'] = $dateTo;
    }
    if ($q !== '') {
        // ODBC/SQL Server PDO requires a distinct placeholder per bind (cannot reuse :q).
        $like = '%' . $q . '%';
        $sql .= <<<SQL
          AND (
            h.IncrementId LIKE :q1
            OR h.CustomerFirstName LIKE :q2
            OR h.CustomerLastName LIKE :q3
            OR h.BillCompany LIKE :q4
            OR h.ShipCompany LIKE :q5
            OR h.CustomerEmail LIKE :q6
          )
        SQL;
        $params['q1'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
        $params['q4'] = $like;
        $params['q5'] = $like;
        $params['q6'] = $like;
    }

    $sql .= ' ORDER BY h.OrderCreatedAt DESC, h.AccsEntityId DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $out = [];
    foreach ($rows as $row) {
        $companyId = isset($row['CompanyId']) && $row['CompanyId'] !== null && $row['CompanyId'] !== ''
            ? (int) $row['CompanyId']
            : null;
        if ($companyId !== null && $companyId <= 0) {
            $companyId = null;
        }

        $companyName = trim((string) ($row['BillCompany'] ?? ''));
        if ($companyName === '') {
            $companyName = trim((string) ($row['ShipCompany'] ?? ''));
        }
        if ($companyName === '') {
            $companyName = trim((string) ($row['PayloadCompanyName'] ?? ''));
        }

        // Synced SQL columns only (no live ACCS; no RawPayloadJson transfer to PHP).
        $companyState = sales_rep_reporting_normalize_state($row['BillRegionCode'] ?? '');
        $companyZip = sales_rep_reporting_normalize_zip($row['BillPostcode'] ?? '');

        // Territory match only when the order is tied to an ACCS company.
        $salesRep = $companyId !== null
            ? sales_rep_reporting_lookup_rep($companyState, $companyZip)
            : '';

        if ($repFilter !== '' && strcasecmp($salesRep, $repFilter) !== 0) {
            continue;
        }

        $itemSubtotal = $row['Subtotal'];
        if ($itemSubtotal === null) {
            $itemSubtotal = (float) ($row['GrandTotal'] ?? 0)
                - (float) ($row['TaxAmount'] ?? 0)
                - (float) ($row['ShippingAmount'] ?? 0)
                - (float) ($row['DiscountAmount'] ?? 0);
        }

        $purchaser = trim(
            trim((string) ($row['CustomerFirstName'] ?? '')) . ' '
            . trim((string) ($row['CustomerLastName'] ?? ''))
        );
        $shipState = trim((string) ($row['ShipRegionCode'] ?? ''));
        if ($shipState === '') {
            $shipState = trim((string) ($row['ShipRegion'] ?? ''));
        }

        $out[] = [
            'order_id'      => (string) ($row['IncrementId'] ?? ''),
            'entity_id'     => (int) ($row['AccsEntityId'] ?? 0),
            'date'          => (string) ($row['OrderCreatedAt'] ?? ''),
            'amount'        => (float) ($row['GrandTotal'] ?? 0),
            'item_subtotal' => (float) $itemSubtotal,
            'status'        => (string) ($row['OrderStatus'] ?? ''),
            'purchaser'     => $purchaser,
            'company_name'  => $companyName,
            'company_id'    => $companyId,
            'company_state' => $companyState,
            'company_zip'   => $companyZip,
            'sales_rep'     => $salesRep,
            'ship_state'    => $shipState,
            'ship_zip'      => sales_rep_reporting_normalize_zip($row['ShipPostcode'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @return list<string>
 */
function sales_rep_reporting_distinct_statuses(): array
{
    $env = sales_rep_reporting_source_environment();
    $stmt = db()->prepare(<<<SQL
        SELECT DISTINCT OrderStatus
        FROM dbo.AccsSalesOrderHeader
        WHERE SourceEnvironment = :env
          AND AccsEntityId < 500000
          AND OrderStatus IS NOT NULL
          AND OrderStatus <> N''
        ORDER BY OrderStatus
    SQL);
    $stmt->execute(['env' => $env]);

    return array_values(array_filter(array_map(
        static fn($row): string => trim((string) ($row['OrderStatus'] ?? '')),
        $stmt->fetchAll() ?: []
    )));
}

/**
 * @return list<string>
 */
function sales_rep_reporting_distinct_reps(): array
{
    $stmt = db()->query(<<<SQL
        SELECT DISTINCT Rep
        FROM dbo.SalesTeamTerritoryAssignments
        WHERE Rep IS NOT NULL AND Rep <> N''
        ORDER BY Rep
    SQL);

    return array_values(array_filter(array_map(
        static fn($row): string => trim((string) ($row['Rep'] ?? '')),
        $stmt->fetchAll() ?: []
    )));
}

function sales_rep_reporting_format_money(mixed $amount): string
{
    return '$' . number_format((float) $amount, 2);
}

function sales_rep_reporting_format_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }
    $ts = strtotime($value);

    return $ts === false ? substr($value, 0, 10) : date('Y-m-d', $ts);
}

function sales_rep_reporting_export_filename(?string $sourceEnvironment = null): string
{
    $env = $sourceEnvironment ?? sales_rep_reporting_source_environment();
    $safeEnv = preg_replace('/[^a-z0-9_-]+/i', '-', $env) ?: 'orders';

    return 'sales-rep-reporting-' . strtolower($safeEnv) . '-' . gmdate('Y-m-d') . '.csv';
}

/**
 * @param list<array<string, mixed>> $rows
 */
function sales_rep_reporting_export_csv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        return '';
    }

    fputcsv($handle, [
        'Order ID',
        'Date',
        'Amount',
        'Item subtotal',
        'Status',
        'Sales rep',
        'Company name',
        'Company ID',
        'Company state',
        'Company zip',
        'Purchaser',
        'Ship-to state',
        'Ship-to zip',
    ]);

    foreach ($rows as $row) {
        fputcsv($handle, [
            (string) ($row['order_id'] ?? ''),
            sales_rep_reporting_format_date($row['date'] ?? null),
            number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
            number_format((float) ($row['item_subtotal'] ?? 0), 2, '.', ''),
            (string) ($row['status'] ?? ''),
            (string) ($row['sales_rep'] ?? ''),
            (string) ($row['company_name'] ?? ''),
            $row['company_id'] !== null && $row['company_id'] !== '' ? (string) $row['company_id'] : '',
            (string) ($row['company_state'] ?? ''),
            (string) ($row['company_zip'] ?? ''),
            (string) ($row['purchaser'] ?? ''),
            (string) ($row['ship_state'] ?? ''),
            (string) ($row['ship_zip'] ?? ''),
        ]);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return $csv === false ? '' : $csv;
}
