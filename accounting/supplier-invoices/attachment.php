<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
accounting_bind_qbo_environment();
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice-attachments.php';
require dirname(__DIR__, 2) . '/includes/qbo-insert-approval.php';
require dirname(__DIR__, 2) . '/includes/payment-approval.php';

if (!supplier_invoice_can_read() && !qbo_insert_can_read_queue() && !payment_approval_can_read_queue()) {
    supplier_invoice_require_read();
}

$id = (int) ($_GET['id'] ?? 0);
$attachment = supplier_invoice_get_attachment($id);

if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

if (supplier_invoice_get((int) $attachment['SupplierInvoiceID']) === null) {
    http_response_code(404);
    exit('Invoice not found.');
}

attachment_storage_stream_download($attachment);
