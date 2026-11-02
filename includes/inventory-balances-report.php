<?php
/**
 * Inventory Balances Excel export + weekly subscriber email.
 */

declare(strict_types=1);

require_once __DIR__ . '/inventory-ledger.php';
require_once __DIR__ . '/xlsx-simple.php';
require_once __DIR__ . '/alert-messages.php';
require_once __DIR__ . '/mail.php';

/**
 * @return list<array<string, mixed>>
 */
function inventory_balances_report_rows(array $filters = [], ?string $ledgerProfile = null): array
{
    $pdo = db();
    $profile = $ledgerProfile !== null && trim($ledgerProfile) !== ''
        ? trim($ledgerProfile)
        : inventory_ledger_profile();

    $sql = <<<SQL
        SELECT
            b.SKUCode,
            ISNULL(s.ProductName, N'') AS ProductName,
            b.FacilityCode,
            f.FacilityName,
            b.QtyOK,
            b.QtyQuarantine,
            b.QtyOnHold,
            b.QtyDestroy,
            b.QtyReserved,
            b.QtyOnHand,
            b.QtyAvailable,
            q.QtyForQbo,
            s.COGS,
            CASE
                WHEN s.COGS IS NULL THEN NULL
                ELSE ROUND(s.COGS * b.QtyOnHand, 2)
            END AS ExtendedCOGS
        FROM dbo.InvCurrentBalance b
        INNER JOIN dbo.Facility f ON f.FacilityCode = b.FacilityCode
        LEFT JOIN dbo.SKUMaster s ON s.SKUCode = b.SKUCode
        OUTER APPLY (
            SELECT SUM(b2.QtyOK + b2.QtyQuarantine + b2.QtyOnHold) AS QtyForQbo
            FROM dbo.InvCurrentBalance b2
            WHERE b2.SKUCode = b.SKUCode
              AND b2.LedgerProfile = b.LedgerProfile
        ) q
        WHERE b.LedgerProfile = :ledger_profile
    SQL;

    $params = ['ledger_profile' => $profile];
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
        $sql .= ' AND ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY b.SKUCode ASC, b.FacilityCode ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array{0: list<string>, 1: list<list<scalar|null>>}
 */
function inventory_balances_report_sheet_data(array $rows): array
{
    $headers = [
        'SKU',
        'Product Name',
        'Facility',
        'Facility Name',
        'OK',
        'Quarantine',
        'On Hold',
        'Destroy',
        'Reserved',
        'On Hand',
        'Available',
        'QBO Qty',
        'COGS',
        'Extended COGS (On Hand)',
    ];

    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            (string) ($row['SKUCode'] ?? ''),
            (string) ($row['ProductName'] ?? ''),
            (string) ($row['FacilityCode'] ?? ''),
            (string) ($row['FacilityName'] ?? ''),
            inventory_balances_report_num($row['QtyOK'] ?? null),
            inventory_balances_report_num($row['QtyQuarantine'] ?? null),
            inventory_balances_report_num($row['QtyOnHold'] ?? null),
            inventory_balances_report_num($row['QtyDestroy'] ?? null),
            inventory_balances_report_num($row['QtyReserved'] ?? null),
            inventory_balances_report_num($row['QtyOnHand'] ?? null),
            inventory_balances_report_num($row['QtyAvailable'] ?? null),
            inventory_balances_report_num($row['QtyForQbo'] ?? null),
            inventory_balances_report_num($row['COGS'] ?? null),
            inventory_balances_report_num($row['ExtendedCOGS'] ?? null),
        ];
    }

    return [$headers, $data];
}

function inventory_balances_report_num(mixed $value): float|int|null
{
    if ($value === null || $value === '') {
        return null;
    }

    $n = (float) $value;
    if (abs($n - round($n)) < 0.0000001) {
        return (int) round($n);
    }

    return round($n, 4);
}

function inventory_balances_report_filename(?DateTimeInterface $at = null): string
{
    $stamp = ($at ?? new DateTimeImmutable('now', new DateTimeZone('America/Chicago')))->format('Y-m-d');

    return 'inventory-balances-' . $stamp . '.xlsx';
}

/**
 * @param list<array<string, mixed>>|null $rows
 */
function inventory_balances_report_xlsx_bytes(?array $rows = null, array $filters = [], ?string $ledgerProfile = null): string
{
    $rows ??= inventory_balances_report_rows($filters, $ledgerProfile);
    [$headers, $data] = inventory_balances_report_sheet_data($rows);

    return xlsx_simple_build($headers, $data, 'Inventory Balances');
}

/**
 * Send the production inventory balances workbook to alert subscribers.
 *
 * @return array{
 *   ok: bool,
 *   smtp_configured: bool,
 *   recipients: list<string>,
 *   sent: list<string>,
 *   failed: list<string>,
 *   skipped_reason: ?string,
 *   error: ?string,
 *   row_count: int,
 *   filename: string
 * }
 */
function inventory_balances_report_send_now(?string $ledgerProfile = null): array
{
    $profile = $ledgerProfile !== null && trim($ledgerProfile) !== ''
        ? trim($ledgerProfile)
        : INVENTORY_LEDGER_PROFILE_PRODUCTION;

    $result = [
        'ok'               => false,
        'smtp_configured'  => mail_smtp_is_configured(),
        'recipients'       => [],
        'sent'             => [],
        'failed'           => [],
        'skipped_reason'   => null,
        'error'            => null,
        'row_count'        => 0,
        'filename'         => inventory_balances_report_filename(),
    ];

    if (!$result['smtp_configured']) {
        $result['skipped_reason'] = 'smtp_not_configured';
        $result['error'] = 'SMTP is not configured.';

        return $result;
    }

    $recipients = alert_message_recipients(ALERT_NAME_INVENTORY_BALANCES_WEEKLY);
    $to = $recipients['to'];
    $cc = $recipients['cc'];
    $result['recipients'] = array_keys($to + $cc);

    if ($to === [] && $cc === []) {
        $result['skipped_reason'] = 'no_subscribers';
        $result['error'] = 'No subscribers for ' . ALERT_NAME_INVENTORY_BALANCES_WEEKLY . '.';

        return $result;
    }

    $rows = inventory_balances_report_rows([], $profile);
    $result['row_count'] = count($rows);
    $filename = inventory_balances_report_filename();
    $result['filename'] = $filename;

    try {
        $bytes = inventory_balances_report_xlsx_bytes($rows, [], $profile);
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();

        return $result;
    }

    $chicago = new DateTimeImmutable('now', new DateTimeZone('America/Chicago'));
    $subject = 'Inventory Balances — ' . $chicago->format('Y-m-d');
    $body = "Attached is the NutraAxis Inventory Balances workbook ({$result['row_count']} row"
        . ($result['row_count'] === 1 ? '' : 's')
        . ") for ledger profile {$profile}.\n\n"
        . "Generated {$chicago->format('Y-m-d g:i A T')}.\n"
        . "COGS values come from SKU Master.\n\n"
        . "Manage subscribers under Site Admin → Users → Alert subscriptions.\n";

    $send = mail_send_multi_attachments_result(
        $to,
        $cc,
        $subject,
        $body,
        null,
        [[
            'filename'     => $filename,
            'content'      => $bytes,
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]]
    );

    if (!empty($send['ok'])) {
        $result['ok'] = true;
        $result['sent'] = $result['recipients'];

        return $result;
    }

    $result['error'] = (string) ($send['error'] ?? 'Send failed.');
    $result['failed'] = $result['recipients'];

    return $result;
}
