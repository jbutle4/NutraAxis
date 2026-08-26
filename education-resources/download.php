<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/education-resources.php';
require dirname(__DIR__) . '/includes/attachment-storage.php';

education_resources_require_read();

$id = (int) ($_GET['id'] ?? 0);
$row = education_resources_get($id);

if ($row === null || strtoupper((string) ($row['Type'] ?? '')) !== 'PDF') {
    http_response_code(404);
    exit('PDF not found.');
}

$blobPath = trim((string) ($row['BlobPath'] ?? ''));
if ($blobPath === '') {
    http_response_code(404);
    exit('PDF file is not available.');
}

$resolved = attachment_storage_resolve_content([
    'BlobPath'    => $blobPath,
    'ContentType' => 'application/pdf',
    'FileName'    => (string) ($row['FileName'] ?? 'education-resource.pdf'),
]);

if (!$resolved['ok']) {
    http_response_code(500);
    exit($resolved['error'] ?? 'Unable to read PDF.');
}

$fileName = trim((string) ($row['FileName'] ?? ''));
if ($fileName === '') {
    $fileName = 'education-resource-' . $id . '.pdf';
}

$contentType = trim((string) ($resolved['content_type'] ?? ''));
if ($contentType === '') {
    $contentType = 'application/pdf';
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $fileName) . '"');
header('Content-Length: ' . strlen((string) $resolved['content']));
header('X-Content-Type-Options: nosniff');
echo $resolved['content'];
exit;
