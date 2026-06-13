<?php

require_once __DIR__ . '/po.php';

const PO_IMPORT_HEADER_FIELDS = [
    'PO Number'              => 'po_number',
    'PO Date'                => 'order_date',
    'Supplier Name'          => 'supplier_name',
    'Supplier Address'       => 'supplier_address',
    'Supplier Contact Name'  => 'supplier_contact_name',
    'Supplier Contact Email' => 'supplier_contact_email',
    'Supplier Contact Phone' => 'supplier_contact_phone',
    'Buyer Name'             => 'buyer_name',
    'Buyer Address'          => 'buyer_address',
    'Buyer Contact Name'     => 'buyer_contact_name',
    'Buyer Contact Email'    => 'buyer_contact_email',
    'Buyer Contact Phone'    => 'buyer_contact_phone',
    'Payment Terms'          => 'payment_terms',
    'Delivery Terms'         => 'delivery_terms',
    'Reference Documents'    => 'reference_documents',
    'Shipping & Handling'    => 'shipping_handling',
    'Special Instructions'   => 'special_instructions',
];

function po_import_parse_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Select a CSV or Excel file to import.'];
    }

    if (($file['size'] ?? 0) > PO_MAX_ATTACHMENT_BYTES) {
        return ['ok' => false, 'error' => 'Import file is too large. Maximum size is 15 MB.'];
    }

    $name = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        return po_import_parse_csv($file['tmp_name']);
    }

    if (in_array($ext, ['xlsx', 'xls'], true)) {
        return po_import_parse_xlsx($file['tmp_name']);
    }

    return ['ok' => false, 'error' => 'Unsupported file type. Upload .csv or .xlsx.'];
}

/**
 * Locate an explicit "ItemSKU" column in a lines header row.
 * SKU values are only imported when the upload labels them as such.
 */
function po_import_sku_column(array $headerRow): ?int
{
    foreach ($headerRow as $i => $label) {
        if (strcasecmp(trim((string) $label), 'ItemSKU') === 0) {
            return (int) $i;
        }
    }

    return null;
}

function po_import_parse_csv(string $path): array
{
    $handle = fopen($path, 'r');
    if ($handle === false) {
        return ['ok' => false, 'error' => 'Unable to read CSV file.'];
    }

    $section = null;
    $header = po_default_header();
    $lines = [];
    $skuCol = null;

    while (($row = fgetcsv($handle)) !== false) {
        if ($row === [null] || $row === false) {
            continue;
        }

        $first = trim((string) ($row[0] ?? ''));
        if ($first === '[HEADER]') {
            $section = 'header';
            fgetcsv($handle);
            continue;
        }
        if ($first === '[LINES]') {
            $section = 'lines';
            $linesHeader = fgetcsv($handle);
            $skuCol = po_import_sku_column(is_array($linesHeader) ? $linesHeader : []);
            continue;
        }

        if ($section === 'header') {
            $field = trim((string) ($row[0] ?? ''));
            $value = trim((string) ($row[1] ?? ''));
            if ($field !== '' && isset(PO_IMPORT_HEADER_FIELDS[$field])) {
                $header[PO_IMPORT_HEADER_FIELDS[$field]] = $value;
            }
            continue;
        }

        if ($section === 'lines') {
            $lineNum = (int) ($row[0] ?? 0);
            $title = trim((string) ($row[1] ?? ''));
            $quote = trim((string) ($row[2] ?? ''));
            $price = po_import_parse_money($row[3] ?? '');
            $expDate = po_import_parse_date($row[4] ?? '');
            $qty = po_import_parse_number($row[5] ?? '');

            if ($title === '' && $qty <= 0) {
                continue;
            }

            $lines[] = [
                'line_number'      => $lineNum > 0 ? $lineNum : count($lines) + 1,
                'description'      => $title,
                'quote_number'     => $quote,
                'unit_price'       => $price,
                'expiration_date'  => $expDate,
                'quantity'         => $qty,
                'sku'              => $skuCol !== null ? trim((string) ($row[$skuCol] ?? '')) : '',
            ];
        }
    }

    fclose($handle);

    return po_import_finalize($header, $lines);
}

function po_import_parse_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'Excel import requires ZipArchive on the server. Use CSV template instead.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['ok' => false, 'error' => 'Unable to open Excel file.'];
    }

    $sharedStrings = po_xlsx_shared_strings($zip);
    $sheets = po_xlsx_sheet_map($zip);
    $header = po_default_header();
    $lines = [];

    if (isset($sheets['Header'])) {
        $rows = po_xlsx_read_sheet($zip, $sheets['Header'], $sharedStrings);
        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue;
            }
            $field = trim((string) ($row[0] ?? ''));
            $value = trim((string) ($row[1] ?? ''));
            if ($field !== '' && isset(PO_IMPORT_HEADER_FIELDS[$field])) {
                $header[PO_IMPORT_HEADER_FIELDS[$field]] = $value;
            }
        }
    }

    if (isset($sheets['Lines'])) {
        $rows = po_xlsx_read_sheet($zip, $sheets['Lines'], $sharedStrings);
        $skuCol = null;
        foreach ($rows as $i => $row) {
            if ($i === 0) {
                $skuCol = po_import_sku_column($row);
                continue;
            }
            $lineNum = (int) ($row[0] ?? 0);
            $title = trim((string) ($row[1] ?? ''));
            $quote = trim((string) ($row[2] ?? ''));
            $price = po_import_parse_money($row[3] ?? '');
            $expDate = po_import_parse_date($row[4] ?? '');
            $qty = po_import_parse_number($row[5] ?? '');

            if ($title === '' && $qty <= 0) {
                continue;
            }

            $lines[] = [
                'line_number'     => $lineNum > 0 ? $lineNum : count($lines) + 1,
                'description'     => $title,
                'quote_number'    => $quote,
                'unit_price'      => $price,
                'expiration_date' => $expDate,
                'quantity'        => $qty,
                'sku'             => $skuCol !== null ? trim((string) ($row[$skuCol] ?? '')) : '',
            ];
        }
    }

    $zip->close();

    return po_import_finalize($header, $lines);
}

const PO_IMPORT_SESSION_KEY = 'po_import_pending';

function po_import_normalize_header(array $header): array
{
    if (($header['order_date'] ?? '') === '') {
        $header['order_date'] = date('Y-m-d');
    } else {
        $header['order_date'] = po_import_parse_date($header['order_date']) ?? date('Y-m-d');
    }

    return $header;
}

function po_import_build_order_input(array $header, array $lines, int $supplierId): array
{
    $header = po_import_normalize_header($header);
    $header['supplier_id'] = (string) $supplierId;
    $header['po_status'] = 'Created';

    return array_merge($header, ['lines' => array_map(fn(array $line): array => [
        'sku'              => $line['sku'] ?? '',
        'description'      => $line['description'],
        'quote_number'     => $line['quote_number'] ?? '',
        'quantity'         => $line['quantity'],
        'unit_price'       => $line['unit_price'],
        'expiration_date'  => $line['expiration_date'] ?? '',
    ], $lines)]);
}

function po_import_finalize(array $header, array $lines): array
{
    if ($lines === []) {
        return ['ok' => false, 'error' => 'No line items found in import file.'];
    }

    $header = po_import_normalize_header($header);

    $supplierId = po_resolve_supplier_id(
        $header['supplier_name'] ?? '',
        $header['supplier_address'] ?? ''
    );

    if ($supplierId === null) {
        return [
            'ok'               => false,
            'supplier_missing' => true,
            'header'           => $header,
            'lines'            => $lines,
        ];
    }

    return [
        'ok'   => true,
        'data' => po_import_build_order_input($header, $lines, $supplierId),
    ];
}

function po_import_staging_dir(): string
{
    $dir = sys_get_temp_dir() . '/nutraaxis-po-import';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    return $dir;
}

function po_import_stage_upload(array $file): array
{
    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Unable to stage import file.'];
    }

    $stagingId = bin2hex(random_bytes(16));
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $path = po_import_staging_dir() . '/' . $stagingId . ($ext !== '' ? '.' . $ext : '');

    if (!move_uploaded_file($tmp, $path)) {
        return ['ok' => false, 'error' => 'Unable to stage import file.'];
    }

    return [
        'ok'         => true,
        'staging_id' => $stagingId,
        'path'       => $path,
        'filename'   => (string) ($file['name'] ?? 'import'),
        'extension'  => $ext,
    ];
}

function po_import_pending_set(array $pending): void
{
    auth_start_session();
    $_SESSION[PO_IMPORT_SESSION_KEY] = $pending;
}

function po_import_pending_get(): ?array
{
    auth_start_session();
    $pending = $_SESSION[PO_IMPORT_SESSION_KEY] ?? null;

    return is_array($pending) ? $pending : null;
}

function po_import_clear_pending(): void
{
    $pending = po_import_pending_get();
    if ($pending !== null && !empty($pending['staging_path']) && is_file($pending['staging_path'])) {
        unlink($pending['staging_path']);
    }

    auth_start_session();
    unset($_SESSION[PO_IMPORT_SESSION_KEY]);
}

function po_import_staged_file_array(array $pending): ?array
{
    $path = $pending['staging_path'] ?? '';
    if ($path === '' || !is_file($path)) {
        return null;
    }

    return [
        'name'     => (string) ($pending['staging_filename'] ?? 'import'),
        'tmp_name' => $path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($path),
    ];
}

function po_import_supplier_form_from_header(array $header): array
{
    return [
        'supplier_code'  => '',
        'supplier_name'  => (string) ($header['supplier_name'] ?? ''),
        'address'        => (string) ($header['supplier_address'] ?? ''),
        'contact_name'   => (string) ($header['supplier_contact_name'] ?? ''),
        'contact_email'  => (string) ($header['supplier_contact_email'] ?? ''),
        'contact_phone'  => (string) ($header['supplier_contact_phone'] ?? ''),
        'is_active'      => true,
    ];
}

function po_import_finish(array $pending, int $supplierId): array
{
    require_once __DIR__ . '/po-attachments.php';

    $input = po_import_build_order_input($pending['header'], $pending['lines'], $supplierId);
    $result = po_save_order($input);
    if (!$result['ok']) {
        return $result;
    }

    $staged = po_import_staged_file_array($pending);
    if ($staged !== null) {
        $ext = strtolower(pathinfo((string) $pending['staging_filename'], PATHINFO_EXTENSION));
        $kind = $ext === 'csv' ? 'ImportCSV' : 'ImportExcel';
        $attachment = po_save_attachment($result['id'], $staged, $kind);
        if (!$attachment['ok']) {
            $result['warning'] = $attachment['error'];
        }
    }

    po_import_clear_pending();

    return $result;
}

function po_import_parse_money($value): float
{
    $value = preg_replace('/[^0-9.\-]/', '', (string) $value);

    return (float) ($value !== '' ? $value : 0);
}

function po_import_parse_number($value): float
{
    $value = str_replace(',', '', (string) $value);

    return (float) ($value !== '' ? $value : 0);
}

function po_import_parse_date($value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return null;
}

function po_xlsx_shared_strings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return [];
    }

    $strings = [];
    $doc = simplexml_load_string($xml);
    if ($doc === false) {
        return [];
    }

    foreach ($doc->si as $si) {
        if (isset($si->t)) {
            $strings[] = (string) $si->t;
        } else {
            $parts = [];
            foreach ($si->r as $run) {
                $parts[] = (string) $run->t;
            }
            $strings[] = implode('', $parts);
        }
    }

    return $strings;
}

function po_xlsx_sheet_map(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($xml === false || $relsXml === false) {
        return [];
    }

    $workbook = simplexml_load_string($xml);
    $rels = simplexml_load_string($relsXml);
    if ($workbook === false || $rels === false) {
        return [];
    }

    $relMap = [];
    foreach ($rels->Relationship as $rel) {
        $relMap[(string) $rel['Id']] = (string) $rel['Target'];
    }

    $map = [];
    $index = 1;
    foreach ($workbook->sheets->sheet as $sheet) {
        $name = (string) $sheet['name'];
        $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $target = $relMap[$rid] ?? ('worksheets/sheet' . $index . '.xml');
        $map[$name] = 'xl/' . ltrim(str_replace('../', '', $target), '/');
        $index++;
    }

    return $map;
}

function po_xlsx_read_sheet(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
{
    $xml = $zip->getFromName($sheetPath);
    if ($xml === false) {
        return [];
    }

    $doc = simplexml_load_string($xml);
    if ($doc === false) {
        return [];
    }

    $rows = [];
    foreach ($doc->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            preg_match('/[A-Z]+/', $ref, $m);
            $colLetters = $m[0] ?? 'A';
            $col = po_xlsx_col_index($colLetters);
            $type = (string) $cell['t'];
            $value = '';
            if ($type === 's') {
                $idx = (int) $cell->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif (isset($cell->v)) {
                $value = (string) $cell->v;
            }
            $cells[$col] = $value;
        }

        if ($cells === []) {
            continue;
        }

        $max = max(array_keys($cells));
        $line = [];
        for ($i = 0; $i <= $max; $i++) {
            $line[] = $cells[$i] ?? '';
        }
        $rows[] = $line;
    }

    return $rows;
}

function po_xlsx_col_index(string $letters): int
{
    $index = 0;
    foreach (str_split($letters) as $char) {
        $index = $index * 26 + (ord($char) - 64);
    }

    return $index - 1;
}
