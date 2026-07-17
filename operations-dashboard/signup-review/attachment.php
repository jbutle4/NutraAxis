<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/provider-signup.php';

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

$resolved = attachment_storage_resolve_content($attachment);
if (!$resolved['ok']) {
    http_response_code(404);
    exit('Attachment file is empty.');
}

$fileName = str_replace('"', '', (string) ($attachment['FileName'] ?? 'attachment'));

header('Content-Type: ' . $resolved['content_type']);
header('Content-Length: ' . strlen($resolved['content']));
header('Content-Disposition: inline; filename="' . $fileName . '"');
echo $resolved['content'];
exit;
