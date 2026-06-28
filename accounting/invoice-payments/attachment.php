<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
require dirname(__DIR__, 2) . '/includes/po-payment.php';
require dirname(__DIR__, 2) . '/includes/po-payment-attachments.php';

accounting_require_read();

$id = (int) ($_GET['id'] ?? 0);
$attachment = po_payment_get_attachment($id);

if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

$payment = po_payment_get((int) $attachment['PaymentID']);
if ($payment === null || empty($payment['SupplierInvoiceID'])) {
    http_response_code(404);
    exit('Payment not found.');
}

attachment_storage_stream_download($attachment);
