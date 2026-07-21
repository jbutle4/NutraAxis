<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-payment.php';
require dirname(__DIR__) . '/includes/po-payment-attachments.php';

po_payment_require_read();

$id = (int) ($_GET['id'] ?? 0);
$attachment = po_payment_get_attachment($id);

if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

if (po_payment_get((int) $attachment['PaymentID']) === null) {
    http_response_code(404);
    exit('Payment not found.');
}

attachment_storage_stream_download($attachment);
