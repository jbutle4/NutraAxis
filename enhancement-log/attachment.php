<?php

require dirname(__DIR__) . '/includes/init-attachment.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';
require dirname(__DIR__) . '/includes/enhancement-log-attachments.php';

enhancement_log_require_read();

$id = (int) ($_GET['id'] ?? 0);
$logId = (int) ($_GET['log_id'] ?? 0);
$attachment = $id > 0 ? enh_log_get_attachment($id) : null;

if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

$entry = enhancement_log_get((int) $attachment['EnhancementLogID']);
if ($entry === null || ($logId > 0 && (int) $attachment['EnhancementLogID'] !== $logId)) {
    http_response_code(404);
    exit('Backlog item not found.');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$bytes = enh_log_read_file_bytes($id);
if ($bytes === '') {
    http_response_code(404);
    exit('Attachment data is missing.');
}

$contentType = enh_log_attachment_content_type($attachment);
$fileName = basename((string) $attachment['FileName']);
$forceDownload = !empty($_GET['download']);
$disposition = ($forceDownload || !str_starts_with(strtolower($contentType), 'image/')) ? 'attachment' : 'inline';

header('Content-Type: ' . $contentType);
header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

echo $bytes;
exit;
