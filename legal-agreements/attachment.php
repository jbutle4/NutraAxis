<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/legal-attachments.php';

legal_require_read();

$id = (int) ($_GET['id'] ?? 0);
$attachment = legal_get_attachment($id);

if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

$contract = legal_get_contract((int) $attachment['ContractID']);
if ($contract === null) {
    http_response_code(404);
    exit('Contract not found.');
}

attachment_storage_stream_download($attachment);
