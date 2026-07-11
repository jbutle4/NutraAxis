<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

provider_signup_require_read();

$attachmentId = (int) ($_GET['id'] ?? 0);
$attachment = provider_signup_get_attachment($attachmentId);

if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

$application = provider_signup_get((int) ($attachment['ApplicationID'] ?? 0));
if ($application === null) {
    http_response_code(404);
    exit('Application not found.');
}

$content = provider_signup_attachment_bytes($attachment);
if ($content === '') {
    http_response_code(404);
    exit('Attachment file is empty.');
}

header('Content-Type: ' . (string) ($attachment['ContentType'] ?? 'application/octet-stream'));
header('Content-Length: ' . strlen($content));
header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) ($attachment['FileName'] ?? 'attachment')) . '"');
echo $content;
exit;
