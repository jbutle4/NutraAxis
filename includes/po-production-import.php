<?php

require_once __DIR__ . '/po-import.php';
require_once __DIR__ . '/po-production.php';

const PO_PRODUCTION_IMPORT_SESSION_KEY = 'po_production_import_pending';

const PO_PRODUCTION_IMPORT_COLUMN_ALIASES = [
    'po_number'               => ['po#', 'po number', 'ponumber', 'po no', 'po no.'],
    'sku'                     => ['skunumber', 'sku number', 'itemsku', 'sku', 'sku code'],
    'product'                 => ['product', 'item description', 'description'],
    'mfg_status'              => ['mfg status', 'manufacturing status', 'mfg'],
    'label_status'            => ['label status', 'label'],
    'bottle_status'           => ['bottle status', 'bottle / packaging', 'bottle/packaging', 'packaging status'],
    'bulk_test_status'        => ['bulk test status', 'bulk test', 'bulk status'],
    'bottle_test_status'      => ['bottle test status', 'bottle test'],
    'target_ship_date'        => ['target ship date', 'target ship', 'ship date'],
    'comments'                => ['comments', 'comment', 'notes'],
    'pallet_count'            => ['pallet count', 'pallets', 'pallet count '],
    'est_weight_lbs'          => ['est weight', 'est. weight (lbs)', 'estimated weight', 'est weight (lbs)'],
];

function po_production_import_normalize_header(string $label): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', $label)));
}

function po_production_import_column_map(array $headerRow): array
{
    $map = [];
    foreach ($headerRow as $index => $label) {
        $normalized = po_production_import_normalize_header((string) $label);
        if ($normalized === '') {
            continue;
        }

        foreach (PO_PRODUCTION_IMPORT_COLUMN_ALIASES as $field => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                $map[$field] = (int) $index;
                break;
            }
        }
    }

    return $map;
}

function po_production_import_find_header_row(array $rows): ?int
{
    foreach ($rows as $index => $row) {
        $map = po_production_import_column_map($row);
        if (isset($map['po_number'], $map['sku'])) {
            return (int) $index;
        }
    }

    return null;
}

function po_production_import_row_value(array $row, array $columnMap, string $field): string
{
    if (!isset($columnMap[$field])) {
        return '';
    }

    return trim((string) ($row[$columnMap[$field]] ?? ''));
}

function po_production_import_parse_rows(array $rows): array
{
    $headerIndex = po_production_import_find_header_row($rows);
    if ($headerIndex === null) {
        return ['ok' => false, 'error' => 'Could not find a header row with PO number and SKU columns.'];
    }

    $columnMap = po_production_import_column_map($rows[$headerIndex]);
    $parsed = [];

    for ($i = $headerIndex + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $poNumber = po_production_import_row_value($row, $columnMap, 'po_number');
        $sku = po_production_import_row_value($row, $columnMap, 'sku');

        if ($poNumber === '' && $sku === '') {
            continue;
        }

        if ($poNumber === '' || $sku === '') {
            return [
                'ok'    => false,
                'error' => 'Row ' . ($i + 1) . ' is missing PO number or SKU.',
            ];
        }

        $mapped = po_production_import_map_row($row, $columnMap);
        if (!$mapped['ok']) {
            return [
                'ok'    => false,
                'error' => 'Row ' . ($i + 1) . ': ' . $mapped['error'],
            ];
        }

        $parsed[] = [
            'row_number' => $i + 1,
            'po_number'  => $poNumber,
            'sku'        => $sku,
            'product'    => po_production_import_row_value($row, $columnMap, 'product'),
            'raw'        => [
                'mfg_status'         => po_production_import_row_value($row, $columnMap, 'mfg_status'),
                'label_status'       => po_production_import_row_value($row, $columnMap, 'label_status'),
                'bottle_status'      => po_production_import_row_value($row, $columnMap, 'bottle_status'),
                'bulk_test_status'   => po_production_import_row_value($row, $columnMap, 'bulk_test_status'),
                'bottle_test_status' => po_production_import_row_value($row, $columnMap, 'bottle_test_status'),
                'target_ship_date'   => po_production_import_row_value($row, $columnMap, 'target_ship_date'),
                'comments'           => po_production_import_row_value($row, $columnMap, 'comments'),
                'pallet_count'       => po_production_import_row_value($row, $columnMap, 'pallet_count'),
                'est_weight_lbs'     => po_production_import_row_value($row, $columnMap, 'est_weight_lbs'),
            ],
            'mapped'     => $mapped['data'],
            'warnings'   => $mapped['warnings'],
        ];
    }

    if ($parsed === []) {
        return ['ok' => false, 'error' => 'No data rows found below the header row.'];
    }

    return ['ok' => true, 'rows' => $parsed];
}

function po_production_import_map_row(array $row, array $columnMap): array
{
    $warnings = [];
    $rawMfg = po_production_import_row_value($row, $columnMap, 'mfg_status');
    $rawLabel = po_production_import_row_value($row, $columnMap, 'label_status');
    $rawBottle = po_production_import_row_value($row, $columnMap, 'bottle_status');
    $rawBulk = po_production_import_row_value($row, $columnMap, 'bulk_test_status');
    $rawBottleTest = po_production_import_row_value($row, $columnMap, 'bottle_test_status');
    $rawTargetShip = po_production_import_row_value($row, $columnMap, 'target_ship_date');
    $rawComments = po_production_import_row_value($row, $columnMap, 'comments');
    $rawPallets = po_production_import_row_value($row, $columnMap, 'pallet_count');
    $rawWeight = po_production_import_row_value($row, $columnMap, 'est_weight_lbs');

    $mfg = po_production_import_normalize_mfg($rawMfg, $warnings);
    $bottle = po_production_import_normalize_bottle($rawBottle, $warnings);
    $bulk = po_production_import_normalize_test($rawBulk, $warnings, 'Bulk test');
    $bottleTest = po_production_import_normalize_test($rawBottleTest, $warnings, 'Bottle test', 'Not Started');

    if ($mfg === null) {
        return ['ok' => false, 'error' => 'Unable to map MFG status "' . $rawMfg . '".'];
    }

    if ($bottle === null) {
        return ['ok' => false, 'error' => 'Unable to map bottle/packaging status "' . $rawBottle . '".'];
    }

    if ($bulk === null) {
        return ['ok' => false, 'error' => 'Unable to map bulk test status "' . $rawBulk . '".'];
    }

    if ($bottleTest === null) {
        return ['ok' => false, 'error' => 'Unable to map bottle test status "' . $rawBottleTest . '".'];
    }

    $comments = po_production_import_build_comments($rawComments, $rawLabel, $rawBottle, $warnings);
    $targetShip = po_production_import_parse_ship_date($rawTargetShip, $warnings);

    return [
        'ok'       => true,
        'data'     => [
            'mfg_status'              => $mfg,
            'bottle_packaging_status' => $bottle,
            'bulk_test_status'        => $bulk,
            'bottle_test_status'      => $bottleTest,
            'target_ship_date'        => $targetShip,
            'actual_ship_date'        => null,
            'pallet_count'            => po_production_import_parse_int($rawPallets),
            'est_weight_lbs'          => po_production_import_parse_weight($rawWeight),
            'comments'                => $comments,
        ],
        'warnings' => $warnings,
    ];
}

function po_production_import_build_comments(string $comments, string $labelStatus, string $bottleStatus, array &$warnings): ?string
{
    $parts = [];

    if (trim($comments) !== '') {
        $parts[] = trim($comments);
    }

    if (trim($labelStatus) !== '') {
        $parts[] = 'Label status: ' . trim($labelStatus);
    }

    if (trim($bottleStatus) !== '' && po_production_import_is_schedule_text($bottleStatus)) {
        $parts[] = 'Bottle schedule: ' . trim($bottleStatus);
    }

    if ($parts === []) {
        return null;
    }

    return implode("\n", $parts);
}

function po_production_import_is_schedule_text(string $value): bool
{
    $key = strtolower(trim($value));

    return str_contains($key, 'scheduled')
        || str_contains($key, 'eta')
        || preg_match('/\d{1,2}\/\d{1,2}/', $key) === 1;
}

function po_production_import_parse_ship_date(string $value, array &$warnings): ?string
{
    $value = trim($value);
    if ($value === '' || strcasecmp($value, 'TBD') === 0) {
        return null;
    }

    $parsed = po_import_parse_date($value);
    if ($parsed === null) {
        $warnings[] = 'Target ship date "' . $value . '" was not recognized and was skipped.';

        return null;
    }

    return $parsed;
}

function po_production_import_parse_int(string $value): ?string
{
    $value = trim(str_replace(',', '', $value));
    if ($value === '') {
        return null;
    }

    if (!ctype_digit($value)) {
        return null;
    }

    return $value;
}

function po_production_import_parse_weight(string $value): ?string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return null;
    }

    $value = preg_replace('/\s*(lb|lbs|pounds?)\.?\s*$/', '', $value);
    $value = str_replace(',', '', $value);

    if (!is_numeric($value)) {
        return null;
    }

    return $value;
}

function po_production_import_normalize_mfg(string $value, array &$warnings): ?string
{
    $value = trim($value);
    if ($value === '') {
        return 'Not Started';
    }

    if (in_array($value, PO_MFG_STATUSES, true)) {
        return $value;
    }

    $mapped = po_production_import_match_status($value, [
        'not started'   => 'Not Started',
        'in production' => 'In Production',
        'in process'    => 'In Production',
        'in progress'   => 'In Production',
        'complete'      => 'Complete',
        'completed'     => 'Complete',
        'complrete'     => 'Complete',
        'on hold'       => 'On Hold',
        'issue'         => 'Issue',
    ]);

    if ($mapped !== null) {
        if ($mapped !== $value && !in_array($value, PO_MFG_STATUSES, true)) {
            $warnings[] = 'MFG status "' . $value . '" mapped to "' . $mapped . '".';
        }

        return $mapped;
    }

    $key = strtolower($value);
    if (str_contains($key, 'complete')) {
        $warnings[] = 'MFG status "' . $value . '" mapped to "Complete".';

        return 'Complete';
    }
    if (str_contains($key, 'process') || str_contains($key, 'progress')) {
        $warnings[] = 'MFG status "' . $value . '" mapped to "In Production".';

        return 'In Production';
    }
    if (str_contains($key, 'pending')) {
        $warnings[] = 'MFG status "' . $value . '" mapped to "Not Started".';

        return 'Not Started';
    }
    if (str_contains($key, 'hold')) {
        $warnings[] = 'MFG status "' . $value . '" mapped to "On Hold".';

        return 'On Hold';
    }
    if (str_contains($key, 'issue')) {
        $warnings[] = 'MFG status "' . $value . '" mapped to "Issue".';

        return 'Issue';
    }

    return null;
}

function po_production_import_normalize_bottle(string $value, array &$warnings): ?string
{
    $value = trim($value);
    if ($value === '') {
        return 'Not Started';
    }

    if (in_array($value, PO_BOTTLE_PACKAGING_STATUSES, true)) {
        return $value;
    }

    $mapped = po_production_import_match_status($value, [
        'not started' => 'Not Started',
        'in progress' => 'In Progress',
        'in process'  => 'In Progress',
        'complete'    => 'Complete',
        'completed'   => 'Complete',
        'complrete'   => 'Complete',
        'issue'       => 'Issue',
    ]);

    if ($mapped !== null) {
        if ($mapped !== $value && !in_array($value, PO_BOTTLE_PACKAGING_STATUSES, true)) {
            $warnings[] = 'Bottle status "' . $value . '" mapped to "' . $mapped . '".';
        }

        return $mapped;
    }

    $key = strtolower($value);
    if (str_contains($key, 'label shipped')) {
        $warnings[] = 'Bottle status "' . $value . '" mapped to "In Progress".';

        return 'In Progress';
    }
    if (str_contains($key, 'scheduled') || str_contains($key, 'eta')) {
        $warnings[] = 'Bottle status "' . $value . '" mapped to "In Progress".';

        return 'In Progress';
    }
    if (str_contains($key, 'complete')) {
        $warnings[] = 'Bottle status "' . $value . '" mapped to "Complete".';

        return 'Complete';
    }
    if (str_contains($key, 'process') || str_contains($key, 'progress')) {
        $warnings[] = 'Bottle status "' . $value . '" mapped to "In Progress".';

        return 'In Progress';
    }
    if (str_contains($key, 'issue')) {
        $warnings[] = 'Bottle status "' . $value . '" mapped to "Issue".';

        return 'Issue';
    }

    return null;
}

function po_production_import_normalize_test(string $value, array &$warnings, string $label, string $emptyDefault = 'Not Started'): ?string
{
    $value = trim($value);
    if ($value === '') {
        return $emptyDefault;
    }

    if (in_array($value, PO_BULK_TEST_STATUSES, true)) {
        return $value;
    }

    $mapped = po_production_import_match_status($value, [
        'not started' => 'Not Started',
        'submitted'   => 'Submitted',
        'passed'      => 'Passed',
        'failed'      => 'Failed',
        'on hold'     => 'On Hold',
        'complete'    => 'Passed',
        'completed'   => 'Passed',
        'complrete'   => 'Passed',
        'in process'  => 'Submitted',
        'in progress' => 'Submitted',
    ]);

    if ($mapped !== null) {
        if ($mapped !== $value) {
            $warnings[] = $label . ' status "' . $value . '" mapped to "' . $mapped . '".';
        }

        return $mapped;
    }

    $key = strtolower($value);
    if (str_contains($key, 'complete')) {
        $warnings[] = $label . ' status "' . $value . '" mapped to "Passed".';

        return 'Passed';
    }
    if (str_contains($key, 'process') || str_contains($key, 'progress')) {
        $warnings[] = $label . ' status "' . $value . '" mapped to "Submitted".';

        return 'Submitted';
    }
    if (str_contains($key, 'fail')) {
        $warnings[] = $label . ' status "' . $value . '" mapped to "Failed".';

        return 'Failed';
    }
    if (str_contains($key, 'pass')) {
        $warnings[] = $label . ' status "' . $value . '" mapped to "Passed".';

        return 'Passed';
    }
    if (str_contains($key, 'submit')) {
        $warnings[] = $label . ' status "' . $value . '" mapped to "Submitted".';

        return 'Submitted';
    }
    if (str_contains($key, 'hold')) {
        $warnings[] = $label . ' status "' . $value . '" mapped to "On Hold".';

        return 'On Hold';
    }

    return null;
}

function po_production_import_match_status(string $value, array $map): ?string
{
    $key = strtolower(trim($value));

    return $map[$key] ?? null;
}

function po_production_import_parse_upload(array $file): array
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
        return po_production_import_parse_csv($file['tmp_name']);
    }

    if (in_array($ext, ['xlsx', 'xls'], true)) {
        return po_production_import_parse_xlsx($file['tmp_name']);
    }

    return ['ok' => false, 'error' => 'Unsupported file type. Upload .csv or .xlsx.'];
}

function po_production_import_parse_csv(string $path): array
{
    $handle = fopen($path, 'r');
    if ($handle === false) {
        return ['ok' => false, 'error' => 'Unable to read CSV file.'];
    }

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        if ($row === [null] || $row === false) {
            continue;
        }
        $rows[] = $row;
    }
    fclose($handle);

    return po_production_import_parse_rows($rows);
}

function po_production_import_parse_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'Excel import requires ZipArchive on the server. Use CSV instead.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['ok' => false, 'error' => 'Unable to open Excel file.'];
    }

    $sharedStrings = po_xlsx_shared_strings($zip);
    $sheets = po_xlsx_sheet_map($zip);
    $rows = [];

    foreach ($sheets as $sheetPath) {
        $sheetRows = po_xlsx_read_sheet($zip, $sheetPath, $sharedStrings);
        if ($rows === [] || count($sheetRows) > count($rows)) {
            $rows = $sheetRows;
        }
    }

    $zip->close();

    return po_production_import_parse_rows($rows);
}

function po_production_import_resolve_rows(array $rows, bool $requireUpdatePermission = true): array
{
    $resolved = [];

    foreach ($rows as $row) {
        $match = po_find_line_by_po_and_sku($row['po_number'], $row['sku']);
        $item = $row;

        if ($match === null) {
            $item['match'] = [
                'found'    => false,
                'editable' => false,
                'error'    => 'No PO line found for PO ' . $row['po_number'] . ' and SKU ' . $row['sku'] . '.',
            ];
        } else {
            $statusEligible = in_array((string) $match['POStatus'], PO_PRODUCTION_EDITABLE_STATUSES, true);
            $editable = $statusEligible && (!$requireUpdatePermission || po_can_update());
            $error = null;
            if (!$statusEligible) {
                $error = 'PO ' . $row['po_number'] . ' is ' . $match['POStatus'] . ' and is not eligible for production status updates.';
            } elseif ($requireUpdatePermission && !po_can_update()) {
                $error = 'You do not have permission to update production status.';
            }

            $item['match'] = [
                'found'            => true,
                'editable'         => $editable,
                'po_id'            => (int) $match['POID'],
                'po_line_id'       => (int) $match['POLineID'],
                'line_number'      => (int) $match['LineNumber'],
                'item_description' => (string) $match['ItemDescription'],
                'po_status'        => (string) $match['POStatus'],
                'error'            => $error,
            ];
        }

        $resolved[] = $item;
    }

    return $resolved;
}

function po_production_import_apply(array $rows, ?int $actorId = null, bool $requireUpdatePermission = true): array
{
    $resolved = po_production_import_resolve_rows($rows, $requireUpdatePermission);
    $updated = 0;
    $skipped = 0;
    $errors = [];

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        foreach ($resolved as $row) {
            $match = $row['match'];
            if (!$match['found'] || !$match['editable']) {
                $skipped++;
                if (!empty($match['error'])) {
                    $errors[] = 'Row ' . $row['row_number'] . ': ' . $match['error'];
                }
                continue;
            }

            try {
                po_upsert_production_line(
                    (int) $match['po_id'],
                    (int) $match['po_line_id'],
                    $row['mapped'],
                    $actorId,
                    $pdo
                );
                $updated++;
            } catch (InvalidArgumentException $e) {
                $pdo->rollBack();

                return [
                    'ok'      => false,
                    'error'   => 'Row ' . $row['row_number'] . ': ' . $e->getMessage(),
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors'  => $errors,
                ];
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'ok'      => false,
            'error'   => po_format_exception_message($e, 'import production status'),
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];
    }

    return [
        'ok'       => true,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'errors'   => $errors,
        'resolved' => $resolved,
    ];
}

function po_production_import_pending_set(array $pending): void
{
    auth_start_session();
    $_SESSION[PO_PRODUCTION_IMPORT_SESSION_KEY] = $pending;
}

function po_production_import_pending_get(): ?array
{
    auth_start_session();
    $pending = $_SESSION[PO_PRODUCTION_IMPORT_SESSION_KEY] ?? null;

    return is_array($pending) ? $pending : null;
}

function po_production_import_clear_pending(): void
{
    $pending = po_production_import_pending_get();
    if ($pending !== null && !empty($pending['staging_path']) && is_file($pending['staging_path'])) {
        unlink($pending['staging_path']);
    }

    auth_start_session();
    unset($_SESSION[PO_PRODUCTION_IMPORT_SESSION_KEY]);
}
