<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';

po_require_read();

$type = $_GET['type'] ?? 'xlsx';
$base = dirname(__DIR__) . '/assets/templates/';

if ($type === 'csv') {
    $path = $base . 'NutraAxis_PO_Import_Template.csv';
    $name = 'NutraAxis_PO_Import_Template.csv';
    $contentType = 'text/csv';
} else {
    $path = $base . 'NutraAxis_PO_Import_Template.xlsx';
    $name = 'NutraAxis_PO_Import_Template.xlsx';
    $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
}

if (!is_readable($path)) {
    http_response_code(404);
    echo 'Template file not found.';
    exit;
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
