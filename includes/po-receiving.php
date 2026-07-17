<?php

require_once __DIR__ . '/po.php';
require_once __DIR__ . '/facility.php';

const POR_STATUSES = ['Draft', 'Scheduled', 'Transmitted', 'Complete', 'Cancelled'];

const POR_MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

const POR_LIST_SORT_COLUMNS = [
    'po_number'      => 'PO number',
    'jazz_asn'       => 'Jazz ASN',
    'supplier'       => 'Supplier',
    'status'         => 'Status',
    'scheduled'      => 'Scheduled',
    'actual_receipt' => 'Actual receipt',
    'appointment'    => 'Appointment',
];

const POR_LIST_SORT_SQL = [
    'po_number'      => 'r.PONumber',
    'jazz_asn'       => 'r.JazzASN',
    'supplier'       => 's.SupplierName',
    'status'         => 'r.PORStatus',
    'scheduled'      => 'r.ScheduledReceiptDate',
    'actual_receipt' => 'r.ActualReceiptDate',
    'appointment'    => 'r.AppointmentMade',
];

function por_can_read(): bool
{
    return po_can_access_po_pages();
}

function por_can_create(): bool
{
    return po_can_update();
}

function por_can_update(): bool
{
    return po_can_update();
}

function por_can_delete(): bool
{
    return po_can_delete();
}

function por_require_read(): void
{
    po_require_read();
}

function por_require_create(): void
{
    po_require_update();
}

function por_require_update(): void
{
    po_require_update();
}

function por_require_delete(): void
{
    po_require_delete();
}

function por_status_class(string $status): string
{
    return match ($status) {
        'Draft'       => 'status-draft',
        'Scheduled'   => 'status-submitted',
        'Transmitted' => 'status-submitted',
        'Complete'    => 'status-received',
        'Cancelled'   => 'status-cancelled',
        default       => 'status-draft',
    };
}

function por_format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return $value;
    }
}

function por_date_input(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d');
    } catch (Throwable) {
        return '';
    }
}

function por_time_input(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (preg_match('/^\d{2}:\d{2}/', $value, $m)) {
        return substr($m[0], 0, 5);
    }

    return '';
}

function por_datetime_input(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function por_format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y g:i A');
    } catch (Throwable) {
        return $value;
    }
}

function por_parse_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function por_sql_time_value(string $time): ?string
{
    $time = por_time_input($time);
    if ($time === '') {
        return null;
    }

    return strlen($time) === 5 ? $time . ':00' : $time;
}

function por_actor_name(): string
{
    $user = auth_user();

    return (string) ($user['UserName'] ?? '');
}

function por_list(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            r.PORID,
            r.POID,
            r.PONumber,
            r.JazzASN,
            r.PORStatus,
            r.ScheduledReceiptDate,
            r.ScheduledReceiptTime,
            r.ActualReceiptDate,
            r.AppointmentMade,
            r.CreateDate,
            s.SupplierName
        FROM dbo.POReceipt r
        INNER JOIN dbo.PurchaseOrder po ON po.POID = r.POID
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        WHERE 1 = 1
    SQL;
    $params = [];

    if (!empty($filters['po_id'])) {
        $sql .= ' AND r.POID = :po_id';
        $params['po_id'] = (int) $filters['po_id'];
    }

    if (!empty($filters['status'])) {
        $sql .= ' AND r.PORStatus = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (
            r.PONumber LIKE :q OR
            r.JazzASN LIKE :q OR
            s.SupplierName LIKE :q OR
            r.DeliveryAddress LIKE :q
        )';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sortState = table_sort_state(POR_LIST_SORT_COLUMNS, 'scheduled', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(POR_LIST_SORT_SQL, $sortState, 'scheduled', 'po_number');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function por_get(int $porId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            r.*,
            po.SupplierID,
            s.SupplierName,
            po.ExpectedDeliveryDate AS POExpectedDeliveryDate
        FROM dbo.POReceipt r
        INNER JOIN dbo.PurchaseOrder po ON po.POID = r.POID
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        WHERE r.PORID = :id
    SQL);
    $stmt->execute(['id' => $porId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function por_get_lines(int $porId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            d.PORDID,
            d.PORID,
            d.POLineID,
            d.ItemSKU,
            d.ItemDescription,
            d.QuantityExpected,
            d.QuantityReceived,
            d.LINote,
            d.CaseBarcode,
            d.SKUBarcode,
            d.CountryOfOrigin,
            d.OnHold,
            li.LineNumber,
            li.Quantity AS QuantityOrdered
        FROM dbo.PORDetail d
        INNER JOIN dbo.POLineItem li ON li.POLineID = d.POLineID
        WHERE d.PORID = :id
        ORDER BY li.LineNumber
    SQL);
    $stmt->execute(['id' => $porId]);

    return $stmt->fetchAll();
}

/**
 * Sum of QuantityExpected already scheduled on other (non-cancelled) receipts
 * for each line of a PO. Keyed by POLineID.
 */
function por_scheduled_quantities_for_po(int $poId, ?int $excludePorId = null): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT d.POLineID, SUM(d.QuantityExpected) AS ScheduledQty
        FROM dbo.PORDetail d
        INNER JOIN dbo.POReceipt r ON r.PORID = d.PORID
        WHERE r.POID = :po
          AND r.PORStatus <> N'Cancelled'
    SQL;
    $params = ['po' => $poId];

    if ($excludePorId !== null) {
        $sql .= ' AND r.PORID <> :exclude';
        $params['exclude'] = $excludePorId;
    }

    $sql .= ' GROUP BY d.POLineID';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['POLineID']] = (float) $row['ScheduledQty'];
    }

    return $map;
}

function por_format_qty($value): string
{
    return po_format_qty($value);
}

/**
 * UPC values from SKUMaster keyed by SKUCode.
 *
 * @param list<string> $skus
 * @return array<string, string>
 */
function por_sku_upc_map(array $skus): array
{
    $skus = array_values(array_unique(array_filter(array_map(
        static fn($sku): string => trim((string) $sku),
        $skus
    ))));

    if ($skus === []) {
        return [];
    }

    $pdo = db();
    $placeholders = implode(',', array_fill(0, count($skus), '?'));
    $stmt = $pdo->prepare(<<<SQL
        SELECT SKUCode, UPC
        FROM dbo.SKUMaster
        WHERE SKUCode IN ($placeholders)
    SQL);
    $stmt->execute($skus);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $upc = trim((string) ($row['UPC'] ?? ''));
        if ($upc !== '') {
            $map[(string) $row['SKUCode']] = $upc;
        }
    }

    return $map;
}

/**
 * Fill sku_barcode from SKUMaster.UPC when ItemSKU is present.
 *
 * @param list<array<string, mixed>> $lines
 * @param bool $overwrite When true, master UPC replaces an existing sku_barcode.
 * @return list<array<string, mixed>>
 */
function por_enrich_lines_sku_barcode(array $lines, bool $overwrite = false): array
{
    $upcMap = por_sku_upc_map(array_map(
        static fn(array $line): string => (string) ($line['item_sku'] ?? ''),
        $lines
    ));

    foreach ($lines as $index => $line) {
        $sku = trim((string) ($line['item_sku'] ?? ''));
        if ($sku === '' || !isset($upcMap[$sku])) {
            continue;
        }

        if (!$overwrite && trim((string) ($line['sku_barcode'] ?? '')) !== '') {
            continue;
        }

        $lines[$index]['sku_barcode'] = $upcMap[$sku];
    }

    return $lines;
}

function por_po_options(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT po.POID, po.PONumber, s.SupplierName, po.POStatus
        FROM dbo.PurchaseOrder po
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        WHERE po.POStatus NOT IN (N'Cancelled')
        ORDER BY po.OrderDate DESC, po.POID DESC
    SQL);

    $options = [];
    foreach ($stmt->fetchAll() as $row) {
        $options[] = [
            'id'    => (int) $row['POID'],
            'label' => $row['PONumber'] . ' · ' . $row['SupplierName'] . ' (' . $row['POStatus'] . ')',
        ];
    }

    return $options;
}

function por_default_header_from_po(int $poId): ?array
{
    $order = po_get_order($poId);
    if ($order === null) {
        return null;
    }

    $lines = po_get_lines($poId);
    $scheduledByLine = por_scheduled_quantities_for_po($poId);
    $detailLines = [];
    foreach ($lines as $line) {
        $detailLines[] = por_default_line_from_po_line($line, $scheduledByLine[(int) $line['POLineID']] ?? 0.0);
    }

    return array_merge(por_default_header_fields(), [
        'po_id'                  => (string) $poId,
        'po_number'              => (string) $order['PONumber'],
        'expected_date'          => por_date_input($order['ExpectedDeliveryDate'] ?? null),
        'delivery_address'       => (string) ($order['DeliveryAddress'] ?? $order['SupplierAddress'] ?? $order['SupplierTableAddress'] ?? ''),
        'lines'                  => $detailLines,
    ]);
}

function por_default_header_fields(): array
{
    return [
        'por_id'                 => '',
        'po_id'                  => '',
        'po_number'              => '',
        'expected_date'          => '',
        'scheduled_receipt_date' => '',
        'scheduled_receipt_time' => '',
        'appointment_made'       => false,
        'actual_receipt_date'    => '',
        'delivery_address'       => '',
        'por_status'             => 'Draft',
        'jazz_asn'               => '',
        'por_notes'              => '',
        'business_type'          => '',
        'shipment_number'        => '',
        'facility'               => facility_default_po_receipt_code(),
        'carrier_number'         => '',
        'seal_number'            => '',
        'load_number'            => '',
        'shipping_method'        => '',
        'shipped_at'             => '',
        'lines'                  => [],
    ];
}

function por_default_line_from_po_line(array $line, float $scheduled = 0.0): array
{
    $ordered = (float) $line['Quantity'];
    $remaining = max(0.0, $ordered - $scheduled);

    return por_enrich_lines_sku_barcode([[
        'po_line_id'         => (int) $line['POLineID'],
        'line_number'        => (int) $line['LineNumber'],
        'item_sku'           => (string) ($line['ItemSKU'] ?? ''),
        'item_description'   => (string) $line['ItemDescription'],
        'quantity_ordered'   => por_format_qty($ordered),
        'quantity_scheduled' => por_format_qty($scheduled),
        'quantity_expected'  => por_format_qty($remaining),
        'quantity_received'  => '0',
        'li_note'            => '',
        'case_barcode'       => '',
        'sku_barcode'        => '',
        'country_of_origin'  => '',
        'on_hold'            => false,
    ]])[0];
}

function por_to_form(array $receipt, array $lines): array
{
    $scheduledByLine = por_scheduled_quantities_for_po((int) $receipt['POID'], (int) $receipt['PORID']);

    $detailLines = [];
    foreach ($lines as $line) {
        $detailLines[] = [
            'pord_id'            => (int) $line['PORDID'],
            'po_line_id'         => (int) $line['POLineID'],
            'line_number'        => (int) $line['LineNumber'],
            'item_sku'           => (string) ($line['ItemSKU'] ?? ''),
            'item_description'   => (string) $line['ItemDescription'],
            'quantity_ordered'   => por_format_qty($line['QuantityOrdered'] ?? null),
            'quantity_scheduled' => por_format_qty($scheduledByLine[(int) $line['POLineID']] ?? 0.0),
            'quantity_expected'  => por_format_qty($line['QuantityExpected'] ?? null),
            'quantity_received'  => por_format_qty($line['QuantityReceived'] ?? null),
            'li_note'            => (string) ($line['LINote'] ?? ''),
            'case_barcode'       => (string) ($line['CaseBarcode'] ?? ''),
            'sku_barcode'        => (string) ($line['SKUBarcode'] ?? ''),
            'country_of_origin'  => (string) ($line['CountryOfOrigin'] ?? ''),
            'on_hold'            => !empty($line['OnHold']),
        ];
    }

    return array_merge(por_default_header_fields(), [
        'por_id'                 => (int) $receipt['PORID'],
        'po_id'                  => (string) $receipt['POID'],
        'po_number'              => (string) $receipt['PONumber'],
        'expected_date'          => por_date_input($receipt['ExpectedDate'] ?? null),
        'scheduled_receipt_date' => por_date_input($receipt['ScheduledReceiptDate'] ?? null),
        'scheduled_receipt_time' => por_time_input($receipt['ScheduledReceiptTime'] ?? null),
        'appointment_made'       => !empty($receipt['AppointmentMade']),
        'actual_receipt_date'    => por_date_input($receipt['ActualReceiptDate'] ?? null),
        'delivery_address'       => (string) ($receipt['DeliveryAddress'] ?? ''),
        'por_status'             => (string) $receipt['PORStatus'],
        'jazz_asn'               => (string) ($receipt['JazzASN'] ?? ''),
        'por_notes'              => (string) ($receipt['PORNotes'] ?? ''),
        'business_type'          => (string) ($receipt['BusinessType'] ?? ''),
        'shipment_number'        => (string) ($receipt['ShipmentNumber'] ?? ''),
        'facility'               => (string) ($receipt['Facility'] ?? facility_default_po_receipt_code()),
        'carrier_number'         => (string) ($receipt['CarrierNumber'] ?? ''),
        'seal_number'            => (string) ($receipt['SealNumber'] ?? ''),
        'load_number'            => (string) ($receipt['LoadNumber'] ?? ''),
        'shipping_method'        => (string) ($receipt['ShippingMethod'] ?? ''),
        'shipped_at'             => por_datetime_input($receipt['ShippedAt'] ?? null),
        'lines'                  => por_enrich_lines_sku_barcode($detailLines),
    ]);
}

function por_form_from_post(array $input, ?int $excludePorId = null): array
{
    $form = por_from_input($input);
    $poId = (int) ($form['po_id'] ?? 0);
    if ($poId <= 0) {
        return $form;
    }

    if ($form['lines'] === []) {
        $default = por_default_header_from_po($poId);
        if ($default !== null) {
            $form['lines'] = $default['lines'];
        }
    }

    $poLines = [];
    foreach (po_get_lines($poId) as $line) {
        $poLines[(int) $line['POLineID']] = $line;
    }

    $scheduledByLine = por_scheduled_quantities_for_po($poId, $excludePorId);

    foreach ($form['lines'] as $index => $line) {
        $poLineId = (int) ($line['po_line_id'] ?? 0);
        if (!isset($poLines[$poLineId])) {
            continue;
        }

        $poLine = $poLines[$poLineId];
        $form['lines'][$index]['line_number'] = (int) $poLine['LineNumber'];
        $form['lines'][$index]['quantity_ordered'] = por_format_qty($poLine['Quantity']);
        $form['lines'][$index]['quantity_scheduled'] = por_format_qty($scheduledByLine[$poLineId] ?? 0.0);
        if (($form['lines'][$index]['item_sku'] ?? '') === '') {
            $form['lines'][$index]['item_sku'] = (string) ($poLine['ItemSKU'] ?? '');
        }
        if (($form['lines'][$index]['item_description'] ?? '') === '') {
            $form['lines'][$index]['item_description'] = (string) $poLine['ItemDescription'];
        }
    }

    $form['lines'] = por_enrich_lines_sku_barcode($form['lines']);

    return $form;
}

function por_from_input(array $input): array
{
    $lines = [];
    if (isset($input['lines']) && is_array($input['lines'])) {
        foreach ($input['lines'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = [
                'pord_id'            => trim($row['pord_id'] ?? ''),
                'po_line_id'         => trim($row['po_line_id'] ?? ''),
                'item_sku'           => trim($row['item_sku'] ?? ''),
                'item_description'   => trim($row['item_description'] ?? ''),
                'quantity_expected'  => trim($row['quantity_expected'] ?? ''),
                'quantity_received'  => trim($row['quantity_received'] ?? ''),
                'li_note'            => trim($row['li_note'] ?? ''),
                'case_barcode'       => trim($row['case_barcode'] ?? ''),
                'sku_barcode'        => trim($row['sku_barcode'] ?? ''),
                'country_of_origin'  => trim($row['country_of_origin'] ?? ''),
                'on_hold'            => !empty($row['on_hold']),
            ];
        }
    }

    return array_merge(por_default_header_fields(), [
        'po_id'                  => trim($input['po_id'] ?? ''),
        'expected_date'          => trim($input['expected_date'] ?? ''),
        'scheduled_receipt_date' => trim($input['scheduled_receipt_date'] ?? ''),
        'scheduled_receipt_time' => trim($input['scheduled_receipt_time'] ?? ''),
        'appointment_made'       => (string) ($input['appointment_made'] ?? '0') === '1',
        'actual_receipt_date'    => trim($input['actual_receipt_date'] ?? ''),
        'delivery_address'       => trim($input['delivery_address'] ?? ''),
        'por_status'             => trim($input['por_status'] ?? 'Draft'),
        'jazz_asn'               => trim($input['jazz_asn'] ?? ''),
        'por_notes'              => trim($input['por_notes'] ?? ''),
        'business_type'          => trim($input['business_type'] ?? ''),
        'shipment_number'        => trim($input['shipment_number'] ?? ''),
        'facility'               => trim($input['facility'] ?? ''),
        'carrier_number'         => trim($input['carrier_number'] ?? ''),
        'seal_number'            => trim($input['seal_number'] ?? ''),
        'load_number'            => trim($input['load_number'] ?? ''),
        'shipping_method'        => trim($input['shipping_method'] ?? ''),
        'shipped_at'             => trim($input['shipped_at'] ?? ''),
        'lines'                  => $lines,
    ]);
}

function por_parse_lines(array $lines, int $poId): array
{
    $parsed = [];
    $pdo = db();

    foreach ($lines as $line) {
        $poLineId = (int) ($line['po_line_id'] ?? 0);
        if ($poLineId <= 0) {
            continue;
        }

        $check = $pdo->prepare('SELECT POLineID, ItemSKU, ItemDescription, Quantity FROM dbo.POLineItem WHERE POLineID = :id AND POID = :po');
        $check->execute(['id' => $poLineId, 'po' => $poId]);
        $poLine = $check->fetch();
        if ($poLine === false) {
            continue;
        }

        $qtyExpected = $line['quantity_expected'] !== '' ? (float) $line['quantity_expected'] : (float) $poLine['Quantity'];
        $qtyReceived = $line['quantity_received'] !== '' ? (float) $line['quantity_received'] : 0.0;

        if ($qtyExpected < 0 || $qtyReceived < 0) {
            return ['ok' => false, 'error' => 'Line quantities cannot be negative.'];
        }

        $parsed[] = [
            'pord_id'           => $line['pord_id'] !== '' ? (int) $line['pord_id'] : null,
            'po_line_id'        => $poLineId,
            'item_sku'          => $line['item_sku'] !== '' ? $line['item_sku'] : ($poLine['ItemSKU'] ?? null),
            'item_description'  => $line['item_description'] !== '' ? $line['item_description'] : $poLine['ItemDescription'],
            'quantity_expected' => $qtyExpected,
            'quantity_received' => $qtyReceived,
            'li_note'           => $line['li_note'] !== '' ? $line['li_note'] : null,
            'case_barcode'      => ($line['case_barcode'] ?? '') !== '' ? $line['case_barcode'] : null,
            'sku_barcode'       => ($line['sku_barcode'] ?? '') !== '' ? $line['sku_barcode'] : null,
            'country_of_origin' => ($line['country_of_origin'] ?? '') !== '' ? $line['country_of_origin'] : null,
            'on_hold'           => !empty($line['on_hold']) ? 1 : 0,
        ];
    }

    if ($parsed === []) {
        $poLines = po_get_lines($poId);
        if ($poLines !== []) {
            foreach ($poLines as $poLine) {
                $parsed[] = [
                    'pord_id'           => null,
                    'po_line_id'        => (int) $poLine['POLineID'],
                    'item_sku'          => $poLine['ItemSKU'] ?? null,
                    'item_description'  => $poLine['ItemDescription'],
                    'quantity_expected' => (float) $poLine['Quantity'],
                    'quantity_received' => 0.0,
                    'li_note'           => null,
                    'case_barcode'      => null,
                    'sku_barcode'       => null,
                    'country_of_origin' => null,
                    'on_hold'           => 0,
                ];
            }
        }
    }

    if ($parsed === []) {
        return ['ok' => false, 'error' => 'Add at least one receipt line from the purchase order.'];
    }

    $parsed = por_enrich_lines_sku_barcode($parsed, true);

    return ['ok' => true, 'error' => null, 'lines' => $parsed];
}

function por_save(array $input, ?int $porId = null): array
{
    $data = por_from_input($input);
    $actor = por_actor_name();

    $poId = (int) ($data['po_id'] ?? 0);
    if ($poId <= 0) {
        return ['ok' => false, 'error' => 'Select a purchase order.'];
    }

    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    if (!in_array($data['por_status'], POR_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Select a valid receipt status.'];
    }

    $facilityError = facility_validate_po_receipt_destination($data['facility'] ?? '');
    if ($facilityError !== null) {
        return ['ok' => false, 'error' => $facilityError];
    }

    $lineResult = por_parse_lines($data['lines'], $poId);
    if (!$lineResult['ok']) {
        return $lineResult;
    }

    $shippedAt = por_parse_datetime($data['shipped_at']);
    if ($data['shipped_at'] !== '' && $shippedAt === null) {
        return ['ok' => false, 'error' => 'Shipped date/time is not valid.'];
    }

    $pdo = db();

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        $params = [
            'po'              => $poId,
            'po_number'       => $order['PONumber'],
            'expected'        => $data['expected_date'] !== '' ? $data['expected_date'] : null,
            'scheduled'       => $data['scheduled_receipt_date'] !== '' ? $data['scheduled_receipt_date'] : null,
            'scheduled_tm'    => por_sql_time_value($data['scheduled_receipt_time']),
            'appointment'     => $data['appointment_made'] ? 1 : 0,
            'actual'          => $data['actual_receipt_date'] !== '' ? $data['actual_receipt_date'] : null,
            'address'         => $data['delivery_address'] !== '' ? $data['delivery_address'] : null,
            'status'          => $data['por_status'],
            'jazz_asn'        => $data['jazz_asn'] !== '' ? $data['jazz_asn'] : null,
            'notes'           => $data['por_notes'] !== '' ? $data['por_notes'] : null,
            'business_type'   => $data['business_type'] !== '' ? $data['business_type'] : null,
            'shipment_number' => $data['shipment_number'] !== '' ? $data['shipment_number'] : null,
            'facility'        => $data['facility'] !== '' ? $data['facility'] : null,
            'carrier_number'  => $data['carrier_number'] !== '' ? $data['carrier_number'] : null,
            'seal_number'     => $data['seal_number'] !== '' ? $data['seal_number'] : null,
            'load_number'     => $data['load_number'] !== '' ? $data['load_number'] : null,
            'shipping_method' => $data['shipping_method'] !== '' ? $data['shipping_method'] : null,
            'shipped_at'      => $shippedAt,
            'modified_by'     => $actor !== '' ? $actor : null,
        ];

        if ($porId === null) {
            $params['created_by'] = $actor !== '' ? $actor : null;
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.POReceipt (
                    POID, PONumber, ExpectedDate, ScheduledReceiptDate, ScheduledReceiptTime,
                    AppointmentMade, ActualReceiptDate, DeliveryAddress, PORStatus, JazzASN, PORNotes,
                    BusinessType, ShipmentNumber, Facility, CarrierNumber, SealNumber, LoadNumber,
                    ShippingMethod, ShippedAt,
                    CreatedBy, ModifiedBy
                )
                OUTPUT INSERTED.PORID AS inserted_id
                VALUES (
                    :po, :po_number, :expected, :scheduled, :scheduled_tm,
                    :appointment, :actual, :address, :status, :jazz_asn, :notes,
                    :business_type, :shipment_number, :facility, :carrier_number, :seal_number, :load_number,
                    :shipping_method, :shipped_at,
                    :created_by, :modified_by
                )
            SQL);
            $stmt->execute($params);
            $porId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            if (por_get($porId) === null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Receipt not found.'];
            }

            $params['id'] = $porId;
            unset($params['po'], $params['po_number']);
            $pdo->prepare(<<<SQL
                UPDATE dbo.POReceipt
                SET ExpectedDate = :expected,
                    ScheduledReceiptDate = :scheduled,
                    ScheduledReceiptTime = :scheduled_tm,
                    AppointmentMade = :appointment,
                    ActualReceiptDate = :actual,
                    DeliveryAddress = :address,
                    PORStatus = :status,
                    JazzASN = :jazz_asn,
                    PORNotes = :notes,
                    BusinessType = :business_type,
                    ShipmentNumber = :shipment_number,
                    Facility = :facility,
                    CarrierNumber = :carrier_number,
                    SealNumber = :seal_number,
                    LoadNumber = :load_number,
                    ShippingMethod = :shipping_method,
                    ShippedAt = :shipped_at,
                    ModifiedBy = :modified_by,
                    ModifiedDate = SYSUTCDATETIME()
                WHERE PORID = :id
            SQL)->execute($params);

            $pdo->prepare('DELETE FROM dbo.PORDetail WHERE PORID = :id')->execute(['id' => $porId]);
        }

        $lineStmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.PORDetail (
                PORID, POLineID, ItemSKU, ItemDescription,
                QuantityExpected, QuantityReceived, LINote,
                CaseBarcode, SKUBarcode, CountryOfOrigin, OnHold
            )
            VALUES (
                :por, :line, :sku, :item_desc,
                :expected, :received, :note,
                :case_barcode, :sku_barcode, :country_of_origin, :on_hold
            )
        SQL);

        foreach ($lineResult['lines'] as $line) {
            $lineStmt->execute([
                'por'               => $porId,
                'line'              => $line['po_line_id'],
                'sku'               => $line['item_sku'],
                'item_desc'         => $line['item_description'],
                'expected'          => $line['quantity_expected'],
                'received'          => $line['quantity_received'],
                'note'              => $line['li_note'],
                'case_barcode'      => $line['case_barcode'],
                'sku_barcode'       => $line['sku_barcode'],
                'country_of_origin' => $line['country_of_origin'],
                'on_hold'           => $line['on_hold'],
            ]);
        }

        $pdo->commit();

        return ['ok' => true, 'error' => null, 'id' => $porId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => po_format_exception_message($e, 'save this receipt')];
    }
}

function por_delete(int $porId): array
{
    if (por_get($porId) === null) {
        return ['ok' => false, 'error' => 'Receipt not found.'];
    }

    $pdo = db();
    $pdo->prepare('DELETE FROM dbo.POReceipt WHERE PORID = :id')->execute(['id' => $porId]);

    return ['ok' => true, 'error' => null];
}

function por_format_scheduled(?string $date, ?string $time): string
{
    if ($date === null || $date === '') {
        return '—';
    }

    $label = por_format_date($date);
    $timeLabel = por_time_input($time ?? '');
    if ($timeLabel !== '') {
        $label .= ' ' . $timeLabel;
    }

    return $label;
}

/**
 * Header and detail can be edited until the receipt has been transmitted to Jazz.
 */
function por_can_edit(array $receipt): bool
{
    return por_can_update()
        && in_array((string) ($receipt['PORStatus'] ?? ''), ['Draft', 'Scheduled'], true);
}

function por_can_transmit(array $receipt): bool
{
    if (!por_can_update()) {
        return false;
    }

    $status = (string) ($receipt['PORStatus'] ?? '');
    return in_array($status, ['Draft', 'Scheduled'], true);
}

function por_transmit(int $porId): array
{
    $receipt = por_get($porId);
    if ($receipt === null) {
        return ['ok' => false, 'error' => 'Receipt not found.'];
    }

    if (!por_can_transmit($receipt)) {
        return ['ok' => false, 'error' => 'This receipt cannot be transmitted.'];
    }

    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.POReceipt
        SET PORStatus = N'Transmitted',
            ModifiedBy = :modified_by,
            ModifiedDate = SYSUTCDATETIME()
        WHERE PORID = :id
    SQL)->execute([
        'modified_by' => por_actor_name() ?: null,
        'id'          => $porId,
    ]);

    return ['ok' => true, 'error' => null];
}
