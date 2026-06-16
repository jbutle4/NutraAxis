<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/forecast-plan.php';

const INVENTORY_FORECASTING_LIST_SORT_COLUMNS = [
    'sku'       => 'SKU',
    'month'     => 'Month',
    'begin_oh'  => 'Begin OH',
    'receipts'  => 'Receipts',
    'sales'     => 'Sales',
    'end_oh'    => 'End OH',
    'shortage'  => 'Shortage',
    'baseline'  => 'Baseline',
    'trend'     => 'Trend',
    'status'    => 'Status',
];

const INVENTORY_FORECASTING_LIST_SORT_SQL = [
    'sku'      => 'SKU',
    'month'    => 'PlanYear',
    'begin_oh' => 'ForecastBeginOH',
    'receipts' => 'ForecastReceipts',
    'sales'    => 'ForecastSales',
    'end_oh'   => 'ForecastEndOH',
    'shortage' => 'ShortageFlag',
    'baseline' => 'BaselineAvg',
    'trend'    => 'TrendFactor',
    'status'   => 'IsLocked',
];

const INVENTORY_FORECASTING_LIST_SORT_NUMERIC = ['begin_oh', 'receipts', 'sales', 'end_oh', 'baseline', 'trend'];

function inventory_forecasting_require_read(): void
{
    auth_require_module_read('inventory-forecasting');
}

function inventory_forecasting_permission_value(): ?string
{
    return auth_permission_value(MODULE_PERMISSION_COLUMNS['inventory-forecasting'] ?? '');
}

function inventory_forecasting_format_qty($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $qty = (float) $value;
    $formatted = number_format($qty, 2, '.', ',');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function inventory_forecasting_format_month(int $year, int $month): string
{
    $date = DateTimeImmutable::createFromFormat('Y-n-j', "{$year}-{$month}-1", forecast_plan_timezone());

    return $date !== false ? $date->format('M Y') : sprintf('%02d/%04d', $month, $year);
}

function inventory_forecasting_format_generated_at(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));

        return $dt->setTimezone(forecast_plan_timezone())->format('M j, Y g:i A T');
    } catch (Throwable) {
        return $value;
    }
}

function inventory_forecasting_list_skus(): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $stmt = $pdo->query(<<<SQL
        SELECT DISTINCT SKU
        FROM dbo.ForecastPlan
        ORDER BY SKU
    SQL);

    return array_map(
        fn(array $row): string => trim((string) ($row['SKU'] ?? '')),
        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
    );
}

function inventory_forecasting_meta(): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $stmt = $pdo->query(<<<SQL
        SELECT
            COUNT(*) AS TotalRows,
            COUNT(DISTINCT SKU) AS SkuCount,
            MAX(GeneratedAt) AS LastGeneratedAt
        FROM dbo.ForecastPlan
    SQL);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'row_count'          => (int) ($row['TotalRows'] ?? 0),
        'sku_count'          => (int) ($row['SkuCount'] ?? 0),
        'last_generated_at'  => $row['LastGeneratedAt'] ?? null,
    ];
}

function inventory_forecasting_normalize_shortage_filter(?string $value): ?int
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    return $value === '1' ? 1 : 0;
}

function inventory_forecasting_list_plan_rows(array $filters = []): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $sql = <<<SQL
        SELECT
            SKU,
            PlanYear,
            PlanMonth,
            ActualBeginOH,
            ActualReceipts,
            ActualSales,
            ActualEndOH,
            ForecastBeginOH,
            ForecastReceipts,
            ForecastSales,
            ForecastEndOH,
            BaselineAvg,
            TrendFactor,
            GeneratedAt,
            IsLocked,
            ShortageFlag
        FROM dbo.ForecastPlan
        WHERE 1 = 1
    SQL;

    $params = [];
    $sku = trim((string) ($filters['sku'] ?? ''));
    if ($sku !== '') {
        $sql .= ' AND SKU = :sku';
        $params['sku'] = $sku;
    }

    $shortage = inventory_forecasting_normalize_shortage_filter($filters['shortage'] ?? null);
    if ($shortage !== null) {
        $sql .= ' AND ShortageFlag = :shortage_flag';
        $params['shortage_flag'] = $shortage;
    }

    $sortState = table_sort_state(INVENTORY_FORECASTING_LIST_SORT_COLUMNS, 'sku', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(INVENTORY_FORECASTING_LIST_SORT_SQL, $sortState, 'sku', 'sku');
    if (($sortState['sort'] ?? 'sku') !== 'month') {
        $sql .= ', PlanYear ASC, PlanMonth ASC';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function inventory_forecasting_shortage_label(array $row): string
{
    return !empty($row['ShortageFlag']) ? 'Yes' : 'No';
}

function inventory_forecasting_export_filename(): string
{
    return 'sku-demand-summary-' . gmdate('Y-m-d') . '.csv';
}

function inventory_forecasting_export_csv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        return '';
    }

    fputcsv($handle, [
        'SKU',
        'Month',
        'Begin OH',
        'Receipts',
        'Sales',
        'End OH',
        'Shortage',
        'Baseline',
        'Trend',
        'Status',
    ]);

    foreach ($rows as $row) {
        fputcsv($handle, [
            (string) ($row['SKU'] ?? ''),
            inventory_forecasting_format_month((int) ($row['PlanYear'] ?? 0), (int) ($row['PlanMonth'] ?? 0)),
            inventory_forecasting_display_qty($row, 'ForecastBeginOH', 'ActualBeginOH'),
            inventory_forecasting_display_qty($row, 'ForecastReceipts', 'ActualReceipts'),
            inventory_forecasting_display_qty($row, 'ForecastSales', 'ActualSales'),
            inventory_forecasting_display_qty($row, 'ForecastEndOH', 'ActualEndOH'),
            inventory_forecasting_shortage_label($row),
            inventory_forecasting_format_qty($row['BaselineAvg'] ?? null),
            inventory_forecasting_format_qty($row['TrendFactor'] ?? null),
            !empty($row['IsLocked']) ? 'Actual' : 'Projected',
        ]);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return "\xEF\xBB\xBF" . (string) $csv;
}

function inventory_forecasting_export_url(?string $sku = null, ?string $shortageFilter = null): string
{
    $query = [];
    $sku = trim((string) $sku);
    if ($sku !== '') {
        $query['sku'] = $sku;
    }

    $shortage = trim((string) $shortageFilter);
    if ($shortage !== '') {
        $query['shortage'] = $shortage;
    }

    $suffix = $query === [] ? '' : '?' . http_build_query($query);

    return '/inventory-demand/export.php' . $suffix;
}

function inventory_forecasting_display_qty(array $row, string $forecastField, string $actualField): string
{
    if (!empty($row['IsLocked'])) {
        return inventory_forecasting_format_qty($row[$actualField] ?? null);
    }

    return inventory_forecasting_format_qty($row[$forecastField] ?? null);
}
