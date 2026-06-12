<?php

require_once __DIR__ . '/adobe-commerce.php';

const DAILY_SALES_SUMMARY_TIMEZONE = 'America/Chicago';

function daily_sales_summary_timezone(): DateTimeZone
{
    return new DateTimeZone(DAILY_SALES_SUMMARY_TIMEZONE);
}

function daily_sales_summary_default_target_date(?DateTimeImmutable $runAt = null): DateTimeImmutable
{
    $runAt ??= new DateTimeImmutable('now', daily_sales_summary_timezone());

    return $runAt->modify('-1 day')->setTime(0, 0, 0);
}

function daily_sales_summary_parse_target_date(?string $value): ?DateTimeImmutable
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value, daily_sales_summary_timezone());
    if ($date === false) {
        return null;
    }

    return $date->setTime(0, 0, 0);
}

function daily_sales_summary_order_created_on_date(array $order, DateTimeImmutable $summaryDate): bool
{
    $created = trim((string) ($order['created_at'] ?? ''));
    if ($created === '') {
        return false;
    }

    if (strtolower(trim((string) ($order['status'] ?? ''))) === 'canceled') {
        return false;
    }

    try {
        $createdAt = new DateTimeImmutable($created);
    } catch (Throwable) {
        return false;
    }

    $localDate = $createdAt->setTimezone(daily_sales_summary_timezone())->format('Y-m-d');

    return $localDate === $summaryDate->format('Y-m-d');
}

function daily_sales_summary_fetch_orders_for_date(DateTimeImmutable $summaryDate): array
{
    // ACCS date filters are unreliable; fetch all recent orders and match in Central Time.
    $result = adobe_commerce_fetch_paginated_orders([
        'searchCriteria[sortOrders][0][field]'     => 'created_at',
        'searchCriteria[sortOrders][0][direction]' => 'DESC',
    ]);

    if (!$result['ok']) {
        return $result;
    }

    $orders = [];
    foreach ($result['rows'] as $order) {
        if (!is_array($order)) {
            continue;
        }

        if (daily_sales_summary_order_created_on_date($order, $summaryDate)) {
            $orders[] = $order;
        }
    }

    return [
        'ok'    => true,
        'error' => null,
        'rows'  => $orders,
        'total' => count($orders),
    ];
}

function daily_sales_summary_item_description(array $item): string
{
    $description = trim((string) ($item['description'] ?? ''));
    if ($description !== '') {
        return $description;
    }

    $extension = $item['extension_attributes'] ?? [];
    if (is_array($extension)) {
        $description = trim((string) ($extension['description'] ?? ''));
        if ($description !== '') {
            return $description;
        }
    }

    return '';
}

function daily_sales_summary_aggregate_orders(array $orders): array
{
    $bySku = [];

    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }

        foreach ($order['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $qty = (float) ($item['qty_ordered'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            if (!isset($bySku[$sku])) {
                $bySku[$sku] = [
                    'sku'          => $sku,
                    'sku_name'     => trim((string) ($item['name'] ?? '')),
                    'sku_desc'     => daily_sales_summary_item_description($item),
                    'qty_sold'     => 0.0,
                ];
            }

            if ($bySku[$sku]['sku_name'] === '' && !empty($item['name'])) {
                $bySku[$sku]['sku_name'] = trim((string) $item['name']);
            }

            if ($bySku[$sku]['sku_desc'] === '') {
                $bySku[$sku]['sku_desc'] = daily_sales_summary_item_description($item);
            }

            $bySku[$sku]['qty_sold'] += $qty;
        }
    }

    ksort($bySku);

    return array_values($bySku);
}

function daily_sales_summary_run(?DateTimeImmutable $summaryDate = null, ?DateTimeImmutable $capturedAt = null): array
{
    $capturedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $captureSql = $capturedAt->format('Y-m-d H:i:s');

    $summaryDate ??= daily_sales_summary_default_target_date(
        new DateTimeImmutable('now', daily_sales_summary_timezone())
    );
    $summaryDate = $summaryDate->setTime(0, 0, 0);
    $summaryDateSql = $summaryDate->format('Y-m-d');

    $configError = adobe_commerce_config_error();
    if ($configError !== null) {
        return [
            'ok'                   => false,
            'error'                => $configError,
            'inserted'             => 0,
            'summary_date'         => $summaryDateSql,
            'summary_capture_date' => $captureSql,
            'orders'               => 0,
        ];
    }

    $ordersResult = daily_sales_summary_fetch_orders_for_date($summaryDate);
    if (!$ordersResult['ok']) {
        return [
            'ok'                   => false,
            'error'                => $ordersResult['error'],
            'inserted'             => 0,
            'summary_date'         => $summaryDateSql,
            'summary_capture_date' => $captureSql,
            'orders'               => 0,
        ];
    }

    $orders = $ordersResult['rows'] ?? [];
    $rows = daily_sales_summary_aggregate_orders($orders);

    $pdo = db();

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        $pdo->prepare('DELETE FROM dbo.DailySalesSummary WHERE SummaryDate = :summary_date')
            ->execute(['summary_date' => $summaryDateSql]);

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.DailySalesSummary (
                SummaryDate, SKU, SKUName, SKUDescription, QtySold, SummaryCaptureDate
            )
            VALUES (
                :summary_date, :sku, :sku_name, :sku_desc, :qty_sold, :capture_date
            )
        SQL);

        $inserted = 0;
        foreach ($rows as $row) {
            $stmt->execute([
                'summary_date' => $summaryDateSql,
                'sku'          => $row['sku'],
                'sku_name'     => $row['sku_name'] !== '' ? $row['sku_name'] : null,
                'sku_desc'     => $row['sku_desc'] !== '' ? $row['sku_desc'] : null,
                'qty_sold'     => $row['qty_sold'],
                'capture_date' => $captureSql,
            ]);
            $inserted++;
        }

        $pdo->commit();

        return [
            'ok'                   => true,
            'error'                => null,
            'inserted'             => $inserted,
            'summary_date'         => $summaryDateSql,
            'summary_capture_date' => $captureSql,
            'orders'               => count($orders),
            'message'              => $inserted === 0 ? 'No SKU sales found for the summary date.' : null,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('daily_sales_summary_run: ' . $e->getMessage());

        return [
            'ok'                   => false,
            'error'                => $e->getMessage(),
            'inserted'             => 0,
            'summary_date'         => $summaryDateSql,
            'summary_capture_date' => $captureSql,
            'orders'               => count($orders),
        ];
    }
}

function daily_sales_summary_format_qty($value): string
{
    if ($value === null || $value === '') {
        return '0';
    }

    return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
}

function daily_sales_summary_list(array $filters = []): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $sql = <<<SQL
        SELECT TOP (:limit)
            DailySalesSummaryID,
            SummaryDate,
            SKU,
            SKUName,
            SKUDescription,
            QtySold,
            SummaryCaptureDate
        FROM dbo.DailySalesSummary
        WHERE 1 = 1
    SQL;

    $params = [
        'limit' => max(1, min(1000, (int) ($filters['limit'] ?? 500))),
    ];

    $summaryDate = trim((string) ($filters['summary_date'] ?? ''));
    if ($summaryDate !== '') {
        $sql .= ' AND SummaryDate = :summary_date';
        $params['summary_date'] = $summaryDate;
    }

    $sku = trim((string) ($filters['sku'] ?? ''));
    if ($sku !== '') {
        $sql .= ' AND SKU LIKE :sku';
        $params['sku'] = '%' . $sku . '%';
    }

    $sql .= ' ORDER BY SummaryDate DESC, SKU ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function daily_sales_summary_distinct_dates(int $limit = 30): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $stmt = $pdo->prepare(<<<SQL
        SELECT DISTINCT TOP (:limit) SummaryDate
        FROM dbo.DailySalesSummary
        ORDER BY SummaryDate DESC
    SQL);
    $stmt->execute(['limit' => max(1, min(100, $limit))]);

    return array_map(
        fn(array $row): string => (string) $row['SummaryDate'],
        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
    );
}
