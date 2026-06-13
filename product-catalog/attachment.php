<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog-attachments.php';

catalog_require_read();

$id = (int) ($_GET['id'] ?? 0);
$attachment = catalog_get_attachment($id);

if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

$sku = catalog_get_sku((int) $attachment['SKUID']);
if ($sku === null) {
    http_response_code(404);
    exit('SKU not found.');
}

header('Content-Type: ' . $attachment['ContentType']);
header('Content-Disposition: attachment; filename="' . basename($attachment['FileName']) . '"');
header('Content-Length: ' . (int) $attachment['FileSizeBytes']);
echo $attachment['FileData'];
exit;
