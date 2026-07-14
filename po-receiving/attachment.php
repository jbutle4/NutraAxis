<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-receiving-attachments.php';

por_require_read();

$id = (int) ($_GET['id'] ?? 0);
$attachment = por_get_attachment($id);

if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

$receipt = por_get((int) $attachment['PORID']);
if ($receipt === null) {
    http_response_code(404);
    exit('Receipt not found.');
}

attachment_storage_stream_download($attachment);
