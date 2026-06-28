<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-attachments.php';

po_require_read();

$id = (int) ($_GET['id'] ?? 0);
$attachment = po_get_attachment($id);

if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

$order = po_get_order((int) $attachment['POID']);
if ($order === null) {
    http_response_code(404);
    exit('Purchase order not found.');
}

attachment_storage_stream_download($attachment);
