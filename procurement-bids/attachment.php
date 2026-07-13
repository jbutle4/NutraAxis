<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/bid-initiative.php';

bid_require_read();

$attachmentId = (int) ($_GET['id'] ?? 0);
$attachment = $attachmentId > 0 ? bid_estimate_get_attachment($attachmentId) : null;
if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

$content = bid_estimate_attachment_content($attachment);
if (!$content['ok']) {
    http_response_code(404);
    exit(htmlspecialchars($content['error'] ?? 'Attachment unavailable.'));
}

$fileName = (string) ($attachment['FileName'] ?? 'attachment');
$contentType = (string) ($content['content_type'] ?: ($attachment['ContentType'] ?? 'application/octet-stream'));

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $fileName) . '"');
header('Content-Length: ' . strlen((string) $content['content']));
echo $content['content'];
exit;
