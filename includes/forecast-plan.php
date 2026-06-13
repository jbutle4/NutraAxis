<?php

const FORECAST_PLAN_TIMEZONE = 'America/Chicago';
const FORECAST_PLAN_HORIZON_MONTHS = 12;
const FORECAST_PLAN_TREND_MIN = 0.7;
const FORECAST_PLAN_TREND_MAX = 1.3;

function forecast_plan_nvarchar(string $expression): string
{
    return 'CAST(' . $expression . ' AS NVARCHAR(200))';
}

function forecast_plan_run_step(string $step, callable $callback)
{
    try {
        return $callback();
    } catch (Throwable $e) {
        $driver = 'unknown';
        try {
            $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            // Ignore secondary failures while reporting the original step error.
        }

        throw new RuntimeException('Forecast plan step "' . $step . '" failed (' . $driver . '): ' . $e->getMessage(), 0, $e);
    }
}

function forecast_plan_timezone(): DateTimeZone
{
    return new DateTimeZone(FORECAST_PLAN_TIMEZONE);
}

function forecast_plan_month_weight(int $monthRank): float
{
    return match (true) {
        $monthRank === 1 => 3.0,
        $monthRank === 2 => 2.5,
        $monthRank === 3 => 2.0,
        $monthRank <= 6  => 1.5,
        default          => 1.0,
    };
}

function forecast_plan_month_key(int $year, int $month): string
{
    return sprintf('%04d-%02d', $year, $month);
}

function forecast_plan_parse_month_key(string $key): ?array
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $key, $matches)) {
        return null;
    }

    $year = (int) $matches[1];
    $month = (int) $matches[2];
    if ($month < 1 || $month > 12) {
        return null;
    }

    return ['year' => $year, 'month' => $month];
}

function forecast_plan_current_month_start(?DateTimeImmutable $runAt = null): DateTimeImmutable
{
    $runAt ??= new DateTimeImmutable('now', forecast_plan_timezone());

    return $runAt->setDate(
        (int) $runAt->format('Y'),
        (int) $runAt->format('m'),
        1
    )->setTime(0, 0, 0);
}

function forecast_plan_horizon_months(DateTimeImmutable $startMonth): array
{
    $months = [];
    $cursor = $startMonth;

    for ($i = 0; $i < FORECAST_PLAN_HORIZON_MONTHS; $i++) {
        $months[] = [
            'year'  => (int) $cursor->format('Y'),
            'month' => (int) $cursor->format('m'),
            'start' => $cursor,
        ];
        $cursor = $cursor->modify('+1 month');
    }

    return $months;
}

function forecast_plan_load_monthly_sales(PDO $pdo): array
{
    $stmt = $pdo->query(<<<SQL
        SELECT
            CAST(SKU AS NVARCHAR(200)) AS SKU,
            SaleYear,
            SaleMonth,
            MonthStart,
            TotalQty
        FROM dbo.MonthlySalesSummary
        ORDER BY CAST(SKU AS NVARCHAR(200)), MonthStart DESC
    SQL);

    $bySku = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sku = trim((string) ($row['SKU'] ?? ''));
        if ($sku === '') {
            continue;
        }

        $bySku[$sku][] = [
            'year'      => (int) $row['SaleYear'],
            'month'     => (int) $row['SaleMonth'],
            'month_key' => forecast_plan_month_key((int) $row['SaleYear'], (int) $row['SaleMonth']),
            'qty'       => (float) $row['TotalQty'],
        ];
    }

    return $bySku;
}

function forecast_plan_compute_model(array $history, DateTimeImmutable $currentMonthStart): array
{
    $cutoff = $currentMonthStart->modify('-12 months');
    $filtered = [];

    foreach ($history as $row) {
        $monthStart = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            sprintf('%04d-%02d-01', $row['year'], $row['month']),
            forecast_plan_timezone()
        );

        if ($monthStart === false || $monthStart < $cutoff || $monthStart >= $currentMonthStart) {
            continue;
        }

        $filtered[] = $row;
    }

    usort($filtered, fn(array $a, array $b): int => $b['month_key'] <=> $a['month_key']);

    if ($filtered === []) {
        return [
            'baseline_avg'  => 0.0,
            'trend_factor'  => 1.0,
            'monthly_sales' => 0.0,
        ];
    }

    $weightedSum = 0.0;
    $weightTotal = 0.0;
    $recent = [];
    $prior = [];

    foreach ($filtered as $index => $row) {
        $rank = $index + 1;
        $weight = forecast_plan_month_weight($rank);
        $weightedSum += $row['qty'] * $weight;
        $weightTotal += $weight;

        if ($rank <= 3) {
            $recent[] = $row['qty'];
        } elseif ($rank <= 6) {
            $prior[] = $row['qty'];
        }
    }

    $baseline = $weightTotal > 0 ? $weightedSum / $weightTotal : 0.0;
    $recentAvg = $recent !== [] ? array_sum($recent) / count($recent) : 0.0;
    $priorAvg = $prior !== [] ? array_sum($prior) / count($prior) : 0.0;

    $trend = 1.0;
    if ($priorAvg > 0 && $recentAvg > 0) {
        $trend = $recentAvg / $priorAvg;
        $trend = max(FORECAST_PLAN_TREND_MIN, min(FORECAST_PLAN_TREND_MAX, $trend));
    }

    $monthlySales = $baseline * $trend;

    return [
        'baseline_avg'  => round($baseline, 4),
        'trend_factor'  => round($trend, 4),
        'monthly_sales' => round($monthlySales, 4),
    ];
}

function forecast_plan_load_planned_receipts(PDO $pdo): array
{
    $stmt = $pdo->query(<<<SQL
        SELECT
            CAST(li.ItemSKU AS NVARCHAR(200)) AS SKU,
            YEAR(po.ExpectedDeliveryDate) AS ReceiptYear,
            MONTH(po.ExpectedDeliveryDate) AS ReceiptMonth,
            SUM(li.Quantity - li.QuantityReceived) AS PlannedQty
        FROM dbo.POLineItem li
        INNER JOIN dbo.PurchaseOrder po ON po.POID = li.POID
        WHERE CAST(po.POStatus AS NVARCHAR(100)) = N'Approved'
          AND po.ExpectedDeliveryDate IS NOT NULL
          AND li.ItemSKU IS NOT NULL
          AND LEN(LTRIM(RTRIM(CAST(li.ItemSKU AS NVARCHAR(200))))) > 0
          AND li.Quantity > li.QuantityReceived
        GROUP BY CAST(li.ItemSKU AS NVARCHAR(200)), YEAR(po.ExpectedDeliveryDate), MONTH(po.ExpectedDeliveryDate)
    SQL);

    $bySku = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sku = trim((string) ($row['SKU'] ?? ''));
        if ($sku === '') {
            continue;
        }

        $key = forecast_plan_month_key((int) $row['ReceiptYear'], (int) $row['ReceiptMonth']);
        $bySku[$sku][$key] = (float) ($row['PlannedQty'] ?? 0);
    }

    return $bySku;
}

function forecast_plan_load_beginning_inventory(PDO $pdo): array
{
    $stmt = $pdo->query(<<<SQL
        WITH LatestSnapshot AS (
            SELECT MAX(SnapshotDateTime) AS SnapshotDateTime
            FROM dbo.JazzInventorySnapshot
        )
        SELECT
            CAST(jis.SKU AS NVARCHAR(200)) AS SKU,
            SUM(jis.AvailableQuantity) AS BeginOH
        FROM dbo.JazzInventorySnapshot jis
        INNER JOIN LatestSnapshot ls ON ls.SnapshotDateTime = jis.SnapshotDateTime
        GROUP BY CAST(jis.SKU AS NVARCHAR(200))
    SQL);

    $inventory = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sku = trim((string) ($row['SKU'] ?? ''));
        if ($sku === '') {
            continue;
        }

        $inventory[$sku] = (float) ($row['BeginOH'] ?? 0);
    }

    return $inventory;
}

function forecast_plan_shortage_flag(float $endOh): int
{
    return $endOh < 0 ? 1 : 0;
}

function forecast_plan_load_locked_keys(PDO $pdo): array
{
    $stmt = $pdo->query(<<<SQL
        SELECT
            CAST(SKU AS NVARCHAR(200)) AS SKU,
            PlanYear,
            PlanMonth
        FROM dbo.ForecastPlan
        WHERE IsLocked = 1
    SQL);

    $locked = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sku = trim((string) ($row['SKU'] ?? ''));
        if ($sku === '') {
            continue;
        }

        $locked[$sku][forecast_plan_month_key((int) $row['PlanYear'], (int) $row['PlanMonth'])] = true;
    }

    return $locked;
}

function forecast_plan_lock_completed_months(PDO $pdo, DateTimeImmutable $currentMonthStart): int
{
    $priorMonth = $currentMonthStart->modify('-1 month');
    $year = (int) $priorMonth->format('Y');
    $month = (int) $priorMonth->format('m');

    $salesStmt = $pdo->prepare(<<<SQL
        SELECT CAST(SKU AS NVARCHAR(200)) AS SKU, TotalQty
        FROM dbo.MonthlySalesSummary
        WHERE SaleYear = :year AND SaleMonth = :month
    SQL);
    $salesStmt->execute(['year' => $year, 'month' => $month]);
    $salesBySku = [];
    foreach ($salesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sku = trim((string) ($row['SKU'] ?? ''));
        if ($sku !== '') {
            $salesBySku[$sku] = (float) ($row['TotalQty'] ?? 0);
        }
    }

    $receiptsStmt = $pdo->prepare(<<<SQL
        SELECT
            CAST(pd.ItemSKU AS NVARCHAR(200)) AS SKU,
            SUM(pd.QuantityReceived) AS ActualReceipts
        FROM dbo.PORDetail pd
        INNER JOIN dbo.POReceipt pr ON pr.PORID = pd.PORID
        WHERE CAST(pr.PORStatus AS NVARCHAR(60)) = N'Complete'
          AND pr.ActualReceiptDate IS NOT NULL
          AND YEAR(pr.ActualReceiptDate) = :year
          AND MONTH(pr.ActualReceiptDate) = :month
          AND pd.ItemSKU IS NOT NULL
        GROUP BY CAST(pd.ItemSKU AS NVARCHAR(200))
    SQL);
    $receiptsStmt->execute(['year' => $year, 'month' => $month]);
    $receiptsBySku = [];
    foreach ($receiptsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sku = trim((string) ($row['SKU'] ?? ''));
        if ($sku !== '') {
            $receiptsBySku[$sku] = (float) ($row['ActualReceipts'] ?? 0);
        }
    }

    $inventory = forecast_plan_load_beginning_inventory($pdo);
    $allSkus = array_unique(array_merge(array_keys($salesBySku), array_keys($receiptsBySku), array_keys($inventory)));

    $updateStmt = $pdo->prepare(<<<SQL
        UPDATE dbo.ForecastPlan
        SET
            ActualSales = :actual_sales,
            ActualReceipts = :actual_receipts,
            ActualBeginOH = :actual_begin_oh,
            ActualEndOH = :actual_end_oh,
            ShortageFlag = :shortage_flag,
            IsLocked = 1,
            GeneratedAt = SYSUTCDATETIME()
        WHERE CAST(SKU AS NVARCHAR(200)) = CAST(:sku AS NVARCHAR(200))
          AND PlanYear = :year
          AND PlanMonth = :month
          AND IsLocked = 0
    SQL);

    $insertStmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.ForecastPlan (
            SKU, PlanYear, PlanMonth,
            ActualBeginOH, ActualReceipts, ActualSales, ActualEndOH,
            ShortageFlag, IsLocked, GeneratedAt
        )
        SELECT
            CAST(:sku AS NVARCHAR(200)), :year, :month,
            :actual_begin_oh, :actual_receipts, :actual_sales, :actual_end_oh,
            :shortage_flag, 1, SYSUTCDATETIME()
        WHERE NOT EXISTS (
            SELECT 1
            FROM dbo.ForecastPlan
            WHERE CAST(SKU AS NVARCHAR(200)) = CAST(:sku_exists AS NVARCHAR(200))
              AND PlanYear = :year_exists
              AND PlanMonth = :month_exists
        )
    SQL);

    $locked = 0;
    foreach ($allSkus as $sku) {
        $actualSales = $salesBySku[$sku] ?? 0.0;
        $actualReceipts = $receiptsBySku[$sku] ?? 0.0;
        $actualBegin = $inventory[$sku] ?? 0.0;
        $actualEnd = $actualBegin + $actualReceipts - $actualSales;

        $updateStmt->execute([
            'actual_sales'    => $actualSales,
            'actual_receipts' => $actualReceipts,
            'actual_begin_oh' => $actualBegin,
            'actual_end_oh'   => $actualEnd,
            'shortage_flag'   => forecast_plan_shortage_flag($actualEnd),
            'sku'             => $sku,
            'year'            => $year,
            'month'           => $month,
        ]);
        $locked += $updateStmt->rowCount();

        if ($updateStmt->rowCount() === 0) {
            $insertStmt->execute([
                'sku'             => $sku,
                'year'            => $year,
                'month'           => $month,
                'actual_begin_oh' => $actualBegin,
                'actual_receipts' => $actualReceipts,
                'actual_sales'    => $actualSales,
                'actual_end_oh'   => $actualEnd,
                'shortage_flag'   => forecast_plan_shortage_flag($actualEnd),
                'sku_exists'      => $sku,
                'year_exists'     => $year,
                'month_exists'    => $month,
            ]);
            $locked += $insertStmt->rowCount();
        }
    }

    return $locked;
}

function forecast_plan_run(?DateTimeImmutable $runAt = null): array
{
    $runAt ??= new DateTimeImmutable('now', forecast_plan_timezone());
    $currentMonthStart = forecast_plan_current_month_start($runAt);
    $horizon = forecast_plan_horizon_months($currentMonthStart);
    $generatedSql = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    $pdo = db();

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        $lockedMonths = forecast_plan_run_step('lock_completed_months', fn() => forecast_plan_lock_completed_months($pdo, $currentMonthStart));
        $monthlySales = forecast_plan_run_step('load_monthly_sales', fn() => forecast_plan_load_monthly_sales($pdo));
        $plannedReceipts = forecast_plan_run_step('load_planned_receipts', fn() => forecast_plan_load_planned_receipts($pdo));
        $beginInventory = forecast_plan_run_step('load_beginning_inventory', fn() => forecast_plan_load_beginning_inventory($pdo));
        $lockedKeys = forecast_plan_run_step('load_locked_keys', fn() => forecast_plan_load_locked_keys($pdo));

        $skuSet = array_unique(array_merge(
            array_keys($monthlySales),
            array_keys($plannedReceipts),
            array_keys($beginInventory)
        ));
        sort($skuSet);

        $deleteStmt = $pdo->prepare(<<<SQL
            DELETE FROM dbo.ForecastPlan
            WHERE CAST(SKU AS NVARCHAR(200)) = CAST(:sku AS NVARCHAR(200))
              AND PlanYear = :year
              AND PlanMonth = :month
              AND IsLocked = 0
        SQL);

        $insertStmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.ForecastPlan (
                SKU, PlanYear, PlanMonth,
                ForecastBeginOH, ForecastReceipts, ForecastSales, ForecastEndOH,
                BaselineAvg, TrendFactor, ShortageFlag, GeneratedAt, IsLocked
            )
            VALUES (
                CAST(:sku AS NVARCHAR(200)), :year, :month,
                :forecast_begin_oh, :forecast_receipts, :forecast_sales, :forecast_end_oh,
                :baseline_avg, :trend_factor, :shortage_flag, :generated_at, 0
            )
        SQL);

        $inserted = 0;
        foreach ($skuSet as $sku) {
            $history = $monthlySales[$sku] ?? [];
            $model = forecast_plan_compute_model($history, $currentMonthStart);
            $endingOh = $beginInventory[$sku] ?? 0.0;

            foreach ($horizon as $monthInfo) {
                $year = $monthInfo['year'];
                $month = $monthInfo['month'];
                $monthKey = forecast_plan_month_key($year, $month);

                if (!empty($lockedKeys[$sku][$monthKey])) {
                    continue;
                }

                forecast_plan_run_step('delete_forecast_row', function () use ($deleteStmt, $sku, $year, $month): void {
                    $deleteStmt->execute([
                        'sku'   => $sku,
                        'year'  => $year,
                        'month' => $month,
                    ]);
                });

                $forecastSales = $model['monthly_sales'];
                $forecastReceipts = $plannedReceipts[$sku][$monthKey] ?? 0.0;
                $forecastBegin = $endingOh;
                $forecastEnd = $forecastBegin + $forecastReceipts - $forecastSales;

                forecast_plan_run_step('insert_forecast_row', function () use (
                    $insertStmt,
                    $sku,
                    $year,
                    $month,
                    $forecastBegin,
                    $forecastReceipts,
                    $forecastSales,
                    $forecastEnd,
                    $model,
                    $generatedSql
                ): void {
                    $insertStmt->execute([
                        'sku'               => $sku,
                        'year'              => $year,
                        'month'             => $month,
                        'forecast_begin_oh' => $forecastBegin,
                        'forecast_receipts' => $forecastReceipts,
                        'forecast_sales'    => $forecastSales,
                        'forecast_end_oh'   => $forecastEnd,
                        'baseline_avg'      => $model['baseline_avg'],
                        'trend_factor'      => $model['trend_factor'],
                        'shortage_flag'     => forecast_plan_shortage_flag($forecastEnd),
                        'generated_at'      => $generatedSql,
                    ]);
                });
                $inserted++;
                $endingOh = $forecastEnd;
            }
        }

        $pdo->commit();

        return [
            'ok'            => true,
            'error'         => null,
            'inserted'      => $inserted,
            'skus'          => count($skuSet),
            'locked_months' => $lockedMonths,
            'generated_at'  => $generatedSql,
            'horizon_start' => $currentMonthStart->format('Y-m-d'),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('forecast_plan_run: ' . $e->getMessage());

        return [
            'ok'    => false,
            'error' => $e->getMessage(),
            'inserted' => 0,
            'skus'  => 0,
        ];
    }
}
