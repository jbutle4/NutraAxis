<?php

require_once __DIR__ . '/po-receiving.php';
require_once __DIR__ . '/jazz-oms.php';

/**
 * ASN column order follows the CartdotCom field mapping (asn.csv):
 * header fields repeat per line, one row per PORDetail line.
 */
const POR_ASN_COLUMNS = [
    'PO Number',
    'Business Type',
    'Shipment Number',
    'Facility',
    'Carrier Number',
    'Seal Number',
    'Load Number',
    'Shipping Method',
    'Shipped At',
    'Arrival At',
    'Case Barcode',
    'Sku Code',
    'Sku Barcode',
    'Quantity',
    'Country of Origin',
    'On Hold',
];

function por_transmit_token(int $porId): string
{
    $user = auth_user();
    $userId = (int) ($user['UserID'] ?? 0);
    $secret = env_first(['CRON_SECRET', 'DB_PASS'], 'nutraaxis-por-transmit');

    return hash_hmac('sha256', 'por-transmit:' . $porId . ':' . $userId, $secret);
}

function por_transmit_url(int $porId): string
{
    return '/po-receiving/asn.php?id=' . $porId
        . '&v=20260611'
        . '&transmit=1'
        . '&token=' . rawurlencode(por_transmit_token($porId));
}

function por_asn_normalize_datetime_string(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    // sqlsrv/PDO sometimes returns "Jun 17 2026 12:00:00:AM" — fix ":AM"/":PM" suffix.
    $value = preg_replace('/:(AM|PM)$/i', ' $1', $value) ?? $value;

    return $value;
}

function por_asn_format_date(?string $value): string
{
    $value = por_asn_normalize_datetime_string($value);
    if ($value === null) {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('n/j/y');
    } catch (Throwable) {
        return $value;
    }
}

function por_asn_shipment_number(array $receipt): string
{
    $shipment = trim((string) ($receipt['ShipmentNumber'] ?? ''));

    return $shipment !== '' ? $shipment : (string) (int) $receipt['PORID'];
}

/**
 * ASNs already stored in Jazz for a shipment number (tenant-scoped unique).
 * Paginates with an early exit once a match is found; capped to avoid Azure timeouts.
 *
 * @return list<array<string, mixed>>
 */
function jazz_oms_find_asns_by_shipment_number(string $shipmentNumber, int $maxPages = 10): array
{
    $shipmentNumber = trim($shipmentNumber);
    if ($shipmentNumber === '') {
        return [];
    }

    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return [];
    }

    $matches = [];
    $url = jazz_oms_base_url() . por_asn_endpoint();
    $params = ['limit' => jazz_oms_page_size(), 'offset' => 0];
    $pageGuard = 0;

    while ($url !== '' && $pageGuard < max(1, $maxPages)) {
        $pageGuard++;
        $result = jazz_oms_api_get($url, $pageGuard === 1 ? $params : null);
        if (!$result['ok']) {
            break;
        }

        $data = $result['data'] ?? [];
        $records = $data['results'] ?? $data['data'] ?? (array_is_list($data) ? $data : []);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }
                if (trim((string) ($record['shipment_number'] ?? '')) === $shipmentNumber) {
                    $matches[] = $record;
                }
            }
        }

        if ($matches !== []) {
            return $matches;
        }

        $next = $data['next'] ?? null;
        $url = is_string($next) && $next !== '' ? $next : '';
        $params = [];
    }

    return $matches;
}

function por_asn_duplicate_shipment_error(string $shipmentNumber, array $existingAsns): string
{
    $ids = [];
    foreach ($existingAsns as $row) {
        $id = por_asn_jazz_id_from_row($row);
        if ($id !== null) {
            $ids[] = $id;
        }
    }
    $ids = array_values(array_unique($ids));

    $message = 'Jazz already has an ASN with shipment number "' . $shipmentNumber . '"';
    if ($ids !== []) {
        $message .= ' (Jazz ASN id' . (count($ids) === 1 ? '' : 's') . ': ' . implode(', ', $ids) . ')';
    }
    $message .= '. Each ASN must use a unique shipment number in Jazz. '
        . 'Edit this receipt and change Shipment number to a new value, then transmit again. '
        . 'If a prior transmit already succeeded, enter the Jazz ASN number on the receipt edit form instead. '
        . 'Review existing records on the Jazz ASNs page.';

    return $message;
}

function por_asn_jazz_id_from_row(array $row): ?string
{
    if (isset($row['id']) && (is_string($row['id']) || is_numeric($row['id']))) {
        $value = trim((string) $row['id']);

        return $value !== '' ? $value : null;
    }

    return null;
}

function por_mark_receipt_transmitted(int $porId, ?string $jazzAsn, ?string $jazzAsnStatus = null): void
{
    $pdo = db();
    $modifiedBy = por_actor_name();
    $modifiedBy = $modifiedBy !== '' ? $modifiedBy : null;
    $jazzAsn = $jazzAsn !== null ? trim($jazzAsn) : '';
    $jazzAsnStatus = $jazzAsnStatus !== null ? trim($jazzAsnStatus) : '';

    if ($jazzAsn !== '') {
        $pdo->prepare(<<<SQL
            UPDATE dbo.POReceipt
            SET PORStatus = N'Transmitted',
                JazzASN = :jazz_asn,
                JazzASNStatus = :jazz_asn_status,
                JazzASNModifiedDate = SYSUTCDATETIME(),
                ModifiedBy = :modified_by,
                ModifiedDate = SYSUTCDATETIME()
            WHERE PORID = :id
        SQL)->execute([
            'jazz_asn'         => $jazzAsn,
            'jazz_asn_status'  => $jazzAsnStatus !== '' ? $jazzAsnStatus : null,
            'modified_by'      => $modifiedBy,
            'id'               => $porId,
        ]);

        return;
    }

    $pdo->prepare(<<<SQL
        UPDATE dbo.POReceipt
        SET PORStatus = N'Transmitted',
            JazzASNStatus = :jazz_asn_status,
            JazzASNModifiedDate = SYSUTCDATETIME(),
            ModifiedBy = :modified_by,
            ModifiedDate = SYSUTCDATETIME()
        WHERE PORID = :id
    SQL)->execute([
        'jazz_asn_status' => $jazzAsnStatus !== '' ? $jazzAsnStatus : null,
        'modified_by'     => $modifiedBy,
        'id'              => $porId,
    ]);
}

/**
 * If Jazz already has this shipment number, treat as a successful prior transmit.
 *
 * @param list<array<string, mixed>> $existingAsns
 */
function por_transmit_reconcile_existing_asn(int $porId, string $shipmentNumber, array $existingAsns): ?array
{
    if ($existingAsns === []) {
        return null;
    }

    $jazzAsn = por_asn_jazz_id_from_row($existingAsns[0]);
    $jazzAsnStatus = trim((string) ($existingAsns[0]['status'] ?? ''));
    por_mark_receipt_transmitted($porId, $jazzAsn, $jazzAsnStatus !== '' ? $jazzAsnStatus : null);

    $message = 'This ASN was already in Jazz under shipment number "' . $shipmentNumber . '"';
    if ($jazzAsn !== null) {
        $message .= ' (Jazz ASN id: ' . $jazzAsn . ')';
    }
    $message .= '. The receipt has been marked as transmitted.';

    return [
        'ok'       => true,
        'error'    => null,
        'jazz_asn' => $jazzAsn,
        'warning'  => $message,
    ];
}

function por_asn_is_duplicate_shipment_error(string $error): bool
{
    $haystack = strtolower($error);

    return (str_contains($haystack, 'duplicate') && str_contains($haystack, 'shipment'))
        || str_contains($haystack, 'already has an asn with this shipment number')
        || str_contains($haystack, 'already has an asn with shipment number');
}

function por_asn_normalize_jazz_error(string $message, string $responseBody = ''): string
{
    $haystack = strtolower($message . ' ' . $responseBody);
    if (str_contains($haystack, 'duplicate') && str_contains($haystack, 'shipment')) {
        return 'Jazz already has an ASN with this shipment number. Each ASN must use a unique shipment number in Jazz. '
            . 'Edit this receipt and change Shipment number to a new value, then transmit again. '
            . 'If a prior transmit already succeeded, enter the Jazz ASN number on the receipt edit form instead.';
    }

    return $message;
}

/**
 * Receipt lines with expected quantity greater than zero for ASN output.
 *
 * @param list<array<string, mixed>> $lines
 * @return list<array<string, mixed>>
 */
function por_asn_lines_with_quantity(array $lines): array
{
    return array_values(array_filter(
        $lines,
        static fn(array $line): bool => (float) ($line['QuantityExpected'] ?? 0) > 0
    ));
}

function por_asn_rows(array $receipt, array $lines): array
{
    $lines = por_asn_lines_with_quantity($lines);

    $header = [
        'PO Number'       => (string) $receipt['PONumber'],
        'Business Type'   => (string) ($receipt['BusinessType'] ?? ''),
        'Shipment Number' => por_asn_shipment_number($receipt),
        'Facility'        => (string) ($receipt['Facility'] ?? ''),
        'Carrier Number'  => (string) ($receipt['CarrierNumber'] ?? ''),
        'Seal Number'     => (string) ($receipt['SealNumber'] ?? ''),
        'Load Number'     => (string) ($receipt['LoadNumber'] ?? ''),
        'Shipping Method' => (string) ($receipt['ShippingMethod'] ?? ''),
        'Shipped At'      => por_asn_format_date($receipt['ShippedAt'] ?? null),
        'Arrival At'      => por_asn_format_date($receipt['ScheduledReceiptDate'] ?? $receipt['ExpectedDate'] ?? null),
    ];

    $rows = [];
    foreach ($lines as $line) {
        $rows[] = $header + [
            'Case Barcode'      => (string) ($line['CaseBarcode'] ?? ''),
            'Sku Code'          => (string) ($line['ItemSKU'] ?? ''),
            'Sku Barcode'       => (string) ($line['SKUBarcode'] ?? ''),
            'Quantity'          => rtrim(rtrim(number_format((float) $line['QuantityExpected'], 4, '.', ''), '0'), '.'),
            'Country of Origin' => (string) ($line['CountryOfOrigin'] ?? ''),
            'On Hold'           => !empty($line['OnHold']) ? 'TRUE' : 'FALSE',
        ];
    }

    return $rows;
}

function por_asn_csv(array $rows): string
{
    $out = fopen('php://temp', 'r+');
    fputcsv($out, POR_ASN_COLUMNS);
    foreach ($rows as $row) {
        $ordered = [];
        foreach (POR_ASN_COLUMNS as $column) {
            $ordered[] = $row[$column] ?? '';
        }
        fputcsv($out, $ordered);
    }
    rewind($out);
    $csv = (string) stream_get_contents($out);
    fclose($out);

    return $csv;
}

function por_asn_receipt_arrival_date(array $receipt): ?string
{
    foreach (['ScheduledReceiptDate', 'ExpectedDate'] as $field) {
        $iso = por_asn_iso_date($receipt[$field] ?? null);
        if ($iso !== null) {
            return $iso;
        }
    }

    return null;
}

function por_asn_iso_date(?string $value): ?string
{
    $value = por_asn_normalize_datetime_string($value);
    if ($value === null) {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

function por_asn_iso_datetime(?string $value): ?string
{
    $value = por_asn_normalize_datetime_string($value);
    if ($value === null) {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

/**
 * Builds the POST body for Jazz `/api/v1/shipping/asn`.
 * Required by Jazz: shipment_number, arrival_at, facility_code, details[].sku_code, details[].quantity.
 * Optional fields are omitted when empty.
 */
function por_asn_payload(array $receipt, array $lines): array
{
    $lines = por_asn_lines_with_quantity($lines);

    $details = [];
    foreach ($lines as $line) {
        $detail = [
            'sku_code' => (string) ($line['ItemSKU'] ?? ''),
            'quantity' => (float) $line['QuantityExpected'],
        ];

        foreach ([
            'case_barcode'      => (string) ($line['CaseBarcode'] ?? ''),
            'sku_barcode'       => (string) ($line['SKUBarcode'] ?? ''),
            'country_of_origin' => (string) ($line['CountryOfOrigin'] ?? ''),
        ] as $key => $value) {
            if ($value !== '') {
                $detail[$key] = $value;
            }
        }

        if (!empty($line['OnHold'])) {
            $detail['on_hold'] = true;
        }

        $details[] = $detail;
    }

    $payload = [
        'shipment_number' => por_asn_shipment_number($receipt),
        'arrival_at'      => por_asn_receipt_arrival_date($receipt),
        'details'         => $details,
    ];

    foreach ([
        'po_number'       => (string) $receipt['PONumber'],
        'business_type'   => (string) ($receipt['BusinessType'] ?? ''),
        'facility_code'   => (string) ($receipt['Facility'] ?? ''),
        'carrier_number'  => (string) ($receipt['CarrierNumber'] ?? ''),
        'seal_number'     => (string) ($receipt['SealNumber'] ?? ''),
        'load_number'     => (string) ($receipt['LoadNumber'] ?? ''),
        'shipping_method' => (string) ($receipt['ShippingMethod'] ?? ''),
    ] as $key => $value) {
        if ($value !== '') {
            $payload[$key] = $value;
        }
    }

    $shippedAt = por_asn_iso_datetime($receipt['ShippedAt'] ?? null);
    if ($shippedAt !== null) {
        $payload['shipped_at'] = $shippedAt;
    }

    return $payload;
}

function por_asn_endpoint(): string
{
    $endpoint = trim((string) env('JAZZ_ASN_ENDPOINT', '/api/v1/shipping/asn'));

    return '/' . ltrim($endpoint, '/');
}

function jazz_oms_api_post(string $url, array $payload): array
{
    $tokenResult = jazz_oms_get_token();
    if (!$tokenResult['ok']) {
        return $tokenResult + ['data' => null, 'status' => 0];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required to connect to Jazz OMS.', 'data' => null, 'status' => 0];
    }

    try {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to encode the ASN payload for Jazz OMS.', 'data' => null, 'status' => 0];
    }

    $headers = jazz_oms_request_headers($tokenResult['token']);
    $headers[] = 'User-Agent: NutraAxis-Operations/1.0 (+https://nutraaxisweb.azurewebsites.net)';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => '',
    ]);

    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($responseBody === false) {
        $detail = 'Unable to reach Jazz OMS at ' . $url . '.';
        if ($curlError !== '') {
            $detail .= ' cURL: ' . $curlError;
        }

        return ['ok' => false, 'error' => $detail, 'data' => null, 'status' => $status];
    }

    $responseBody = (string) $responseBody;

    if ($status === 403 && jazz_oms_is_cloudflare_block($responseBody)) {
        return ['ok' => false, 'error' => jazz_oms_cloudflare_error_message(), 'data' => null, 'status' => $status];
    }

    $data = null;
    if (trim($responseBody) !== '') {
        try {
            $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            if ($status < 400) {
                return ['ok' => false, 'error' => 'Jazz OMS returned a non-JSON acknowledgement (HTTP ' . $status . ').', 'data' => null, 'status' => $status];
            }
            $data = null;
        }
    }

    if ($status >= 400) {
        $message = null;
        if (is_array($data)) {
            $message = $data['detail'] ?? $data['message'] ?? $data['error'] ?? null;
            if (!is_string($message)) {
                $message = jazz_oms_flatten_validation_errors($data);
            }
        }
        if (!is_string($message) || $message === '') {
            $preview = trim(preg_replace('/\s+/', ' ', $responseBody) ?? '');
            if (strlen($preview) > 160) {
                $preview = substr($preview, 0, 160) . '…';
            }
            $message = 'Jazz OMS ASN request failed (HTTP ' . $status . ').' . ($preview !== '' ? ' Response: ' . $preview : '');
        }

        $message = por_asn_normalize_jazz_error($message, $responseBody);

        return ['ok' => false, 'error' => 'Jazz rejected the ASN: ' . $message, 'data' => $data, 'status' => $status];
    }

    return ['ok' => true, 'error' => null, 'data' => $data, 'status' => $status];
}

/**
 * Flatten DRF-style validation errors ({field: ["msg"], details: [{field: "msg"}]})
 * into a readable one-line message.
 */
function jazz_oms_flatten_validation_errors(array $data, string $prefix = ''): ?string
{
    $parts = [];
    foreach ($data as $field => $messages) {
        $label = $prefix !== '' ? $prefix . '.' . $field : (string) $field;
        if (is_string($messages)) {
            $parts[] = $label . ': ' . $messages;
        } elseif (is_array($messages)) {
            foreach ($messages as $idx => $item) {
                if (is_string($item)) {
                    $parts[] = $label . ': ' . $item;
                } elseif (is_array($item)) {
                    $nested = jazz_oms_flatten_validation_errors($item, $label . '[' . $idx . ']');
                    if ($nested !== null) {
                        $parts[] = $nested;
                    }
                }
            }
        }
    }

    return $parts !== [] ? implode(' ', $parts) : null;
}

/**
 * Pull the Jazz ASN number out of the acknowledgement, tolerating
 * different key names and a nested data/result wrapper.
 */
function por_asn_extract_ack($data): ?string
{
    if (!is_array($data)) {
        return null;
    }

    $candidates = ['asn_number', 'asnNumber', 'asn_no', 'asn', 'shipment_notice_number', 'number', 'id'];
    foreach ($candidates as $key) {
        if (isset($data[$key]) && (is_string($data[$key]) || is_numeric($data[$key]))) {
            $value = trim((string) $data[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    foreach (['data', 'result', 'results'] as $wrapper) {
        if (isset($data[$wrapper]) && is_array($data[$wrapper])) {
            $nested = $data[$wrapper];
            if (array_is_list($nested) && $nested !== [] && is_array($nested[0])) {
                $nested = $nested[0];
            }
            $found = por_asn_extract_ack($nested);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function jazz_oms_list_asns(): array
{
    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'rows' => []];
    }

    $url = jazz_oms_base_url() . por_asn_endpoint();
    $params = ['limit' => jazz_oms_page_size(), 'offset' => 0];
    $rows = [];
    $pageGuard = 0;

    while ($url !== '' && $pageGuard < 200) {
        $pageGuard++;
        $result = jazz_oms_api_get($url, $pageGuard === 1 ? $params : null);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error'], 'rows' => $rows];
        }

        $data = $result['data'] ?? [];

        // Tolerate both paginated ({results: [...], next: ...}) and plain-list responses.
        $records = $data['results'] ?? $data['data'] ?? (array_is_list($data) ? $data : []);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (is_array($record)) {
                    $rows[] = $record;
                }
            }
        }

        $next = $data['next'] ?? null;
        $url = is_string($next) && $next !== '' ? $next : '';
        $params = [];
    }

    return ['ok' => true, 'error' => null, 'rows' => $rows];
}

function jazz_oms_asn_detail_url(int|string $id): string
{
    return data_profile_page_path('/po-receiving/jazz-asn.php') . '?id=' . rawurlencode(trim((string) $id));
}

/**
 * Fetch one ASN from Jazz by numeric id.
 *
 * @return array{ok: bool, error: ?string, row: ?array<string, mixed>}
 */
function jazz_oms_get_asn(int|string $id): array
{
    $id = trim((string) $id);
    if ($id === '') {
        return ['ok' => false, 'error' => 'ASN id is required.', 'row' => null];
    }

    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'row' => null];
    }

    $directUrl = jazz_oms_base_url() . por_asn_endpoint() . '/' . rawurlencode($id) . '/';
    $direct = jazz_oms_api_get($directUrl);
    if ($direct['ok'] && is_array($direct['data']) && isset($direct['data']['id'])) {
        return ['ok' => true, 'error' => null, 'row' => $direct['data']];
    }

    $url = jazz_oms_base_url() . por_asn_endpoint();
    $params = ['limit' => jazz_oms_page_size(), 'offset' => 0];
    $pageGuard = 0;

    while ($url !== '' && $pageGuard < 200) {
        $pageGuard++;
        $result = jazz_oms_api_get($url, $pageGuard === 1 ? $params : null);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error'], 'row' => null];
        }

        $data = $result['data'] ?? [];
        $records = $data['results'] ?? $data['data'] ?? (array_is_list($data) ? $data : []);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }
                if (trim((string) ($record['id'] ?? '')) === $id) {
                    return ['ok' => true, 'error' => null, 'row' => $record];
                }
            }
        }

        $next = $data['next'] ?? null;
        $url = is_string($next) && $next !== '' ? $next : '';
        $params = [];
    }

    return ['ok' => false, 'error' => 'ASN ' . $id . ' was not found in Jazz OMS.', 'row' => null];
}

function jazz_oms_asn_header_fields(array $row): array
{
    $skip = ['detail', 'details', 'cases', 'attributes', 'sku_attributes'];
    $fields = [];

    foreach ($row as $key => $value) {
        if (in_array($key, $skip, true) || is_array($value)) {
            continue;
        }
        $fields[$key] = $value;
    }

    $preferred = [
        'id', 'status', 'po_number', 'shipment_number', 'facility', 'facility_code',
        'carrier_number', 'shipping_method', 'shipment_type', 'shipped_at', 'arrival_at',
        'load_number', 'seal_number', 'on_hold', 'created_at', 'updated_at',
        'door_number', 'trailer_number', 'arrived_at', 'putaway_at',
    ];

    $ordered = [];
    foreach ($preferred as $key) {
        if (array_key_exists($key, $fields)) {
            $ordered[$key] = $fields[$key];
            unset($fields[$key]);
        }
    }

    return array_merge($ordered, $fields);
}

/**
 * @param list<array<string, mixed>> $details
 */
function jazz_oms_asn_detail_columns(array $details): array
{
    $columns = [];
    foreach ($details as $row) {
        foreach (array_keys($row) as $key) {
            if ($key !== 'sku_attributes') {
                $columns[$key] = true;
            }
        }
    }

    $preferred = [
        'sku_code', 'item_code', 'description', 'barcode', 'quantity', 'received',
        'status', 'facility', 'case_barcode', 'size_desc', 'line_number', 'on_hold',
    ];

    $ordered = [];
    foreach ($preferred as $key) {
        if (isset($columns[$key])) {
            $ordered[] = $key;
            unset($columns[$key]);
        }
    }

    return array_merge($ordered, array_keys($columns));
}

function jazz_oms_asn_format_field_value($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if (is_scalar($value)) {
        $text = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $text) === 1) {
            try {
                return (new DateTimeImmutable($text))->format('M j, Y g:i A T');
            } catch (Throwable) {
                return $text;
            }
        }

        return $text;
    }

    return jazz_oms_asn_cell_value($value);
}

function jazz_oms_asn_cell_value($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    $json = json_encode($value, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return '—';
    }

    return strlen($json) > 80 ? substr($json, 0, 77) . '…' : $json;
}

function jazz_oms_asn_columns(array $rows): array
{
    $columns = [];
    foreach ($rows as $row) {
        foreach (array_keys($row) as $key) {
            $columns[$key] = true;
        }
    }

    // Surface the most useful identifiers first when present.
    $preferred = [
        'id', 'asn_number', 'asnNumber', 'asn', 'po_number', 'shipment_number',
        'status', 'facility', 'carrier_number', 'shipping_method',
        'shipped_at', 'arrival_at', 'created_at', 'updated_at',
    ];

    $ordered = [];
    foreach ($preferred as $key) {
        if (isset($columns[$key])) {
            $ordered[] = $key;
            unset($columns[$key]);
        }
    }

    return array_merge($ordered, array_keys($columns));
}

function jazz_oms_asn_column_label(string $key): string
{
    return ucwords(str_replace(['_', '-'], ' ', preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $key) ?? $key));
}

/**
 * Locate the Jazz ASN record for a PO receipt.
 *
 * @return array<string, mixed>|null
 */
function por_resolve_jazz_asn_for_receipt(array $receipt): ?array
{
    $jazzAsnId = trim((string) ($receipt['JazzASN'] ?? ''));
    if ($jazzAsnId !== '') {
        $result = jazz_oms_get_asn($jazzAsnId);
        if ($result['ok'] && is_array($result['row'])) {
            return $result['row'];
        }
    }

    $shipmentNumber = por_asn_shipment_number($receipt);
    $matches = jazz_oms_find_asns_by_shipment_number($shipmentNumber);
    if ($matches === []) {
        return null;
    }

    $poNumber = trim((string) ($receipt['PONumber'] ?? ''));
    if ($poNumber !== '') {
        foreach ($matches as $match) {
            if (trim((string) ($match['po_number'] ?? '')) === $poNumber) {
                return $match;
            }
        }
    }

    return $matches[0];
}

/**
 * @param list<array<string, mixed>> $details
 * @return array<string, float>
 */
function por_jazz_received_by_sku(array $details): array
{
    $receivedBySku = [];

    foreach ($details as $detail) {
        if (!is_array($detail)) {
            continue;
        }

        $sku = trim((string) ($detail['sku_code'] ?? $detail['item_code'] ?? ''));
        if ($sku === '') {
            continue;
        }

        $receivedBySku[$sku] = ($receivedBySku[$sku] ?? 0.0) + (float) ($detail['received'] ?? 0);
    }

    return $receivedBySku;
}

/**
 * Refresh Jazz ASN header fields and line received quantities from Jazz OMS.
 *
 * @return array{ok: bool, error: ?string, updated: bool, warning: ?string}
 */
function por_sync_jazz_asn_from_integration(int $porId): array
{
    $receipt = por_get($porId);
    if ($receipt === null) {
        return ['ok' => false, 'error' => 'Receipt not found.', 'updated' => false, 'warning' => null];
    }

    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'updated' => false, 'warning' => null];
    }

    $hasLookupKey = trim((string) ($receipt['JazzASN'] ?? '')) !== ''
        || trim((string) ($receipt['ShipmentNumber'] ?? '')) !== ''
        || ($receipt['PORStatus'] ?? '') === 'Transmitted';

    if (!$hasLookupKey) {
        return ['ok' => true, 'error' => null, 'updated' => false, 'warning' => null];
    }

    $asn = por_resolve_jazz_asn_for_receipt($receipt);
    if ($asn === null) {
        return [
            'ok'      => true,
            'error'   => null,
            'updated' => false,
            'warning' => 'No matching Jazz ASN was found for this receipt.',
        ];
    }

    $jazzAsnId = por_asn_jazz_id_from_row($asn);
    $jazzStatus = trim((string) ($asn['status'] ?? ''));
    $details = is_array($asn['detail'] ?? null)
        ? $asn['detail']
        : (is_array($asn['details'] ?? null) ? $asn['details'] : []);
    $receivedBySku = por_jazz_received_by_sku($details);
    $lines = por_get_lines($porId);
    $modifiedBy = por_actor_name();
    $modifiedBy = $modifiedBy !== '' ? $modifiedBy : null;

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        $pdo->prepare(<<<SQL
            UPDATE dbo.POReceipt
            SET JazzASN = :jazz_asn,
                JazzASNStatus = :jazz_asn_status,
                JazzASNModifiedDate = SYSUTCDATETIME(),
                ModifiedBy = :modified_by,
                ModifiedDate = SYSUTCDATETIME()
            WHERE PORID = :id
        SQL)->execute([
            'jazz_asn'        => $jazzAsnId,
            'jazz_asn_status' => $jazzStatus !== '' ? $jazzStatus : null,
            'modified_by'     => $modifiedBy,
            'id'              => $porId,
        ]);

        if ($receivedBySku !== []) {
            $updateLine = $pdo->prepare(<<<SQL
                UPDATE dbo.PORDetail
                SET QuantityReceived = :received
                WHERE PORDID = :id
            SQL);

            foreach ($lines as $line) {
                $sku = trim((string) ($line['ItemSKU'] ?? ''));
                if ($sku === '' || !array_key_exists($sku, $receivedBySku)) {
                    continue;
                }

                $updateLine->execute([
                    'received' => $receivedBySku[$sku],
                    'id'       => (int) $line['PORDID'],
                ]);
            }
        }

        $pdo->commit();

        return ['ok' => true, 'error' => null, 'updated' => true, 'warning' => null];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'ok'      => false,
            'error'   => por_format_exception_message($e, 'sync Jazz ASN data'),
            'updated' => false,
            'warning' => null,
        ];
    }
}

function por_transmit_to_jazz(int $porId): array
{
    $receipt = por_get($porId);
    if ($receipt === null) {
        return ['ok' => false, 'error' => 'Receipt not found.', 'jazz_asn' => null];
    }

    if (!por_can_transmit($receipt)) {
        return ['ok' => false, 'error' => 'This receipt cannot be transmitted.', 'jazz_asn' => null];
    }

    $lines = por_asn_lines_with_quantity(por_get_lines($porId));
    if ($lines === []) {
        return ['ok' => false, 'error' => 'This receipt has no line items with expected quantity to transmit.', 'jazz_asn' => null];
    }

    if (por_asn_receipt_arrival_date($receipt) === null) {
        return ['ok' => false, 'error' => 'Jazz requires an arrival date. Set the Scheduled receipt date on this receipt before transmitting.', 'jazz_asn' => null];
    }

    if (trim((string) ($receipt['Facility'] ?? '')) === '') {
        return ['ok' => false, 'error' => 'Jazz requires a facility code. Set Facility on this receipt before transmitting.', 'jazz_asn' => null];
    }

    foreach ($lines as $line) {
        if (trim((string) ($line['ItemSKU'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'Jazz requires a SKU code on every detail line. Line ' . (int) $line['LineNumber'] . ' has no SKU.', 'jazz_asn' => null];
        }
    }

    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'jazz_asn' => null];
    }

    $shipmentNumber = por_asn_shipment_number($receipt);
    $payload = por_asn_payload($receipt, $lines);
    $result = jazz_oms_api_post(jazz_oms_base_url() . por_asn_endpoint(), $payload);
    if (!$result['ok']) {
        $error = (string) ($result['error'] ?? '');
        if (por_asn_is_duplicate_shipment_error($error)) {
            $existingAsns = jazz_oms_find_asns_by_shipment_number($shipmentNumber);
            $reconciled = por_transmit_reconcile_existing_asn($porId, $shipmentNumber, $existingAsns);
            if ($reconciled !== null) {
                return $reconciled;
            }

            return [
                'ok'       => false,
                'error'    => por_asn_duplicate_shipment_error($shipmentNumber, $existingAsns),
                'jazz_asn' => null,
            ];
        }

        return ['ok' => false, 'error' => $result['error'], 'jazz_asn' => null];
    }

    $jazzAsn = por_asn_extract_ack($result['data']);
    $jazzAsnStatus = null;
    if ($jazzAsn !== null) {
        $asnResult = jazz_oms_get_asn($jazzAsn);
        if ($asnResult['ok'] && is_array($asnResult['row'])) {
            $jazzAsnStatus = trim((string) ($asnResult['row']['status'] ?? ''));
        }
    }

    por_mark_receipt_transmitted(
        $porId,
        $jazzAsn,
        $jazzAsnStatus !== '' ? $jazzAsnStatus : null
    );

    return [
        'ok'       => true,
        'error'    => null,
        'jazz_asn' => $jazzAsn,
        'warning'  => $jazzAsn === null
            ? 'Jazz accepted the ASN but did not return an ASN number. Check Jazz OMS and enter the ASN number manually if needed.'
            : null,
    ];
}
