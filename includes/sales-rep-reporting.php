<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/adobe-commerce.php';
require_once __DIR__ . '/data-profile.php';
require_once __DIR__ . '/sales-reporting.php';

const SALES_REP_REPORTING_SORT_COLUMNS = [
    'order_id'       => 'Order ID',
    'date'           => 'Date',
    'amount'         => 'Amount',
    'item_subtotal'  => 'Item subtotal',
    'status'         => 'Status',
    'purchaser'      => 'Purchaser',
    'company_name'   => 'Company name',
    'company_id'     => 'Company ID',
    'company_state'  => 'Company state',
    'company_zip'    => 'Company zip',
    'sales_rep'      => 'Sales rep',
    'ship_state'     => 'Ship-to state',
    'ship_zip'       => 'Ship-to zip',
];

const SALES_REP_REPORTING_SORT_NUMERIC = [
    'amount',
    'item_subtotal',
    'company_id',
];

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
 * @return array<int|string, string> region_id => state code
 */
function sales_rep_reporting_us_region_map(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }

    $map = [];
    $result = adobe_commerce_api_request('GET', '/directory/countries/US');
    if (empty($result['ok']) || !is_array($result['data'] ?? null)) {
        return $map;
    }
    foreach ($result['data']['available_regions'] ?? [] as $region) {
        if (!is_array($region)) {
            continue;
        }
        $id = (string) ($region['id'] ?? '');
        $code = trim((string) ($region['code'] ?? ''));
        if ($id !== '' && $code !== '') {
            $map[$id] = strtoupper($code);
        }
    }

    return $map;
}

/**
 * @return array{state: string, zip: string, name: string}
 */
function sales_rep_reporting_company_address(int $companyId): array
{
    static $cache = [];
    if (isset($cache[$companyId])) {
        return $cache[$companyId];
    }

    $empty = ['state' => '', 'zip' => '', 'name' => ''];
    if ($companyId <= 0) {
        return $empty;
    }

    $result = adobe_commerce_api_request('GET', '/company/' . $companyId);
    if (empty($result['ok']) || !is_array($result['data'] ?? null)) {
        $cache[$companyId] = $empty;

        return $empty;
    }

    $company = $result['data'];
    $state = '';
    $region = $company['region'] ?? null;
    if (is_string($region)) {
        $state = sales_rep_reporting_normalize_state($region);
    } elseif (is_array($region)) {
        $state = sales_rep_reporting_normalize_state(
            (string) ($region['region_code'] ?? $region['code'] ?? $region['region'] ?? '')
        );
    }
    if ($state === '' && !empty($company['region_id'])) {
        $regions = sales_rep_reporting_us_region_map();
        $state = $regions[(string) $company['region_id']] ?? '';
    }

    $cache[$companyId] = [
        'state' => $state,
        'zip'   => sales_rep_reporting_normalize_zip($company['postcode'] ?? ''),
        'name'  => trim((string) ($company['company_name'] ?? '')),
    ];

    return $cache[$companyId];
}

function sales_rep_reporting_extract_company_id(?string $rawPayloadJson): ?int
{
    if ($rawPayloadJson === null || $rawPayloadJson === '') {
        return null;
    }
    try {
        $payload = json_decode($rawPayloadJson, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return null;
    }
    if (!is_array($payload)) {
        return null;
    }
    $ext = $payload['extension_attributes'] ?? [];
    if (!is_array($ext)) {
        return null;
    }
    $companyAttrs = $ext['company_order_attributes'] ?? null;
    $candidates = [
        is_array($companyAttrs) ? ($companyAttrs['company_id'] ?? null) : null,
        $ext['company_id'] ?? null,
        is_array($ext['company'] ?? null) ? ($ext['company']['id'] ?? null) : null,
    ];
    foreach ($candidates as $candidate) {
        $id = (int) $candidate;
        if ($id > 0) {
            return $id;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $filters
 * @return list<array<string, mixed>>
 */
function sales_rep_reporting_list(array $filters = []): array
{
    $env = sales_rep_reporting_source_environment();
    $limit = max(1, min(2000, (int) ($filters['limit'] ?? 500)));
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
            h.RawPayloadJson,
            h.LastSyncedAt
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
        $sql .= <<<SQL
          AND (
            h.IncrementId LIKE :q
            OR h.CustomerFirstName LIKE :q
            OR h.CustomerLastName LIKE :q
            OR h.BillCompany LIKE :q
            OR h.ShipCompany LIKE :q
            OR h.CustomerEmail LIKE :q
          )
        SQL;
        $params['q'] = '%' . $q . '%';
    }

    $sql .= ' ORDER BY h.OrderCreatedAt DESC, h.AccsEntityId DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $out = [];
    foreach ($rows as $row) {
        $companyId = sales_rep_reporting_extract_company_id(
            isset($row['RawPayloadJson']) ? (string) $row['RawPayloadJson'] : null
        );
        $companyName = trim((string) ($row['BillCompany'] ?? ''));
        if ($companyName === '') {
            $companyName = trim((string) ($row['ShipCompany'] ?? ''));
        }

        $companyState = '';
        $companyZip = '';
        if ($companyId !== null) {
            $company = sales_rep_reporting_company_address($companyId);
            if ($companyName === '' && $company['name'] !== '') {
                $companyName = $company['name'];
            }
            $companyState = $company['state'] !== ''
                ? $company['state']
                : sales_rep_reporting_normalize_state($row['BillRegionCode'] ?? '');
            $companyZip = $company['zip'] !== ''
                ? $company['zip']
                : sales_rep_reporting_normalize_zip($row['BillPostcode'] ?? '');
        }

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
