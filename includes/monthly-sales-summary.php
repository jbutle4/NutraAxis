<?php

const MONTHLY_SALES_SUMMARY_TIMEZONE = 'America/Chicago';

function monthly_sales_summary_timezone(): DateTimeZone
{
    return new DateTimeZone(MONTHLY_SALES_SUMMARY_TIMEZONE);
}

function monthly_sales_summary_run(?DateTimeImmutable $updatedAt = null): array
{
    $updatedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $updatedSql = $updatedAt->format('Y-m-d H:i:s');

    $pdo = db();

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        $pdo->exec('DELETE FROM dbo.MonthlySalesSummary');

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.MonthlySalesSummary (
                SKU, SaleYear, SaleMonth, MonthStart, TotalQty, LastUpdatedAt
            )
            SELECT
                dss.SKU,
                YEAR(dss.SummaryDate) AS SaleYear,
                MONTH(dss.SummaryDate) AS SaleMonth,
                DATEFROMPARTS(YEAR(dss.SummaryDate), MONTH(dss.SummaryDate), 1) AS MonthStart,
                SUM(dss.QtySold) AS TotalQty,
                :updated_at AS LastUpdatedAt
            FROM dbo.DailySalesSummary dss
            GROUP BY
                dss.SKU,
                YEAR(dss.SummaryDate),
                MONTH(dss.SummaryDate),
                DATEFROMPARTS(YEAR(dss.SummaryDate), MONTH(dss.SummaryDate), 1)
        SQL);
        $stmt->execute(['updated_at' => $updatedSql]);
        $inserted = $stmt->rowCount();

        $pdo->commit();

        return [
            'ok'          => true,
            'error'       => null,
            'inserted'    => $inserted,
            'updated_at'  => $updatedSql,
            'message'     => $inserted === 0 ? 'No daily sales rows found to roll up.' : null,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('monthly_sales_summary_run: ' . $e->getMessage());

        return [
            'ok'         => false,
            'error'      => $e->getMessage(),
            'inserted'   => 0,
            'updated_at' => $updatedSql,
        ];
    }
}

function monthly_sales_summary_format_qty($value): string
{
    if ($value === null || $value === '') {
        return '0';
    }

    return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
}

function monthly_sales_summary_list(array $filters = []): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $sql = <<<SQL
        SELECT TOP (:limit)
            MonthlySalesSummaryID,
            SKU,
            SaleYear,
            SaleMonth,
            MonthStart,
            TotalQty,
            LastUpdatedAt
        FROM dbo.MonthlySalesSummary
        WHERE 1 = 1
    SQL;

    $params = [
        'limit' => max(1, min(1000, (int) ($filters['limit'] ?? 500))),
    ];

    $saleYear = (int) ($filters['sale_year'] ?? 0);
    if ($saleYear > 0) {
        $sql .= ' AND SaleYear = :sale_year';
        $params['sale_year'] = $saleYear;
    }

    $saleMonth = (int) ($filters['sale_month'] ?? 0);
    if ($saleMonth >= 1 && $saleMonth <= 12) {
        $sql .= ' AND SaleMonth = :sale_month';
        $params['sale_month'] = $saleMonth;
    }

    $sku = trim((string) ($filters['sku'] ?? ''));
    if ($sku !== '') {
        $sql .= ' AND SKU LIKE :sku';
        $params['sku'] = '%' . $sku . '%';
    }

    $sql .= ' ORDER BY MonthStart DESC, SKU ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function monthly_sales_summary_distinct_months(int $limit = 24): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $stmt = $pdo->prepare(<<<SQL
        SELECT DISTINCT TOP (:limit)
            SaleYear,
            SaleMonth,
            MonthStart
        FROM dbo.MonthlySalesSummary
        ORDER BY MonthStart DESC
    SQL);
    $stmt->execute(['limit' => max(1, min(100, $limit))]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
