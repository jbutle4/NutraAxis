<?php

require_once __DIR__ . '/file-storage.php';

function attachment_storage_save(
    string $domain,
    int $entityId,
    int $attachmentId,
    string $fileName,
    string $contentType,
    string $content
): array {
    if (!file_storage_is_configured()) {
        return ['ok' => false, 'error' => 'Azure Blob Storage is not configured.', 'blob_path' => null];
    }

    if ($entityId <= 0 || $attachmentId <= 0) {
        return ['ok' => false, 'error' => 'Invalid attachment identifiers.', 'blob_path' => null];
    }

    $blobPath = file_storage_build_blob_path($domain, $entityId, $attachmentId, $fileName);
    $upload = file_storage_upload($blobPath, $content, $contentType);
    if (!$upload['ok']) {
        return [
            'ok'        => false,
            'error'     => $upload['error'] ?? 'Unable to upload attachment to blob storage.',
            'blob_path' => null,
        ];
    }

    return [
        'ok'        => true,
        'error'     => null,
        'blob_path' => (string) ($upload['blob_path'] ?? $blobPath),
    ];
}

function attachment_storage_read(string $blobPath): array
{
    $blobPath = trim($blobPath);
    if ($blobPath === '') {
        return ['ok' => false, 'error' => 'Blob path is required.', 'content' => '', 'content_type' => ''];
    }

    $result = file_storage_read($blobPath);
    if (!$result['ok']) {
        return [
            'ok'           => false,
            'error'        => $result['error'] ?? 'Unable to read attachment from blob storage.',
            'content'      => '',
            'content_type' => '',
        ];
    }

    return [
        'ok'           => true,
        'error'        => null,
        'content'      => (string) ($result['content'] ?? ''),
        'content_type' => (string) ($result['content_type'] ?? 'application/octet-stream'),
    ];
}

function attachment_storage_delete(string $blobPath): array
{
    $blobPath = trim($blobPath);
    if ($blobPath === '') {
        return ['ok' => true, 'error' => null];
    }

    return file_storage_delete($blobPath);
}

function attachment_storage_row_file_bytes(array $attachmentRow): string
{
    $fileData = $attachmentRow['FileData'] ?? null;
    if ($fileData === null || $fileData === '') {
        return '';
    }

    if (is_resource($fileData)) {
        $content = stream_get_contents($fileData);

        return $content === false ? '' : $content;
    }

    return (string) $fileData;
}

function attachment_storage_resolve_content(array $attachmentRow): array
{
    $blobPath = trim((string) ($attachmentRow['BlobPath'] ?? ''));
    if ($blobPath !== '') {
        $blob = attachment_storage_read($blobPath);
        if (!$blob['ok']) {
            return [
                'ok'           => false,
                'error'        => $blob['error'] ?? 'Unable to read attachment from blob storage.',
                'content'      => '',
                'content_type' => (string) ($attachmentRow['ContentType'] ?? 'application/octet-stream'),
            ];
        }

        $contentType = trim((string) ($blob['content_type'] ?? ''));
        if ($contentType === '' || $contentType === 'application/octet-stream') {
            $contentType = trim((string) ($attachmentRow['ContentType'] ?? ''));
        }

        return [
            'ok'           => true,
            'error'        => null,
            'content'      => (string) $blob['content'],
            'content_type' => $contentType !== '' ? $contentType : 'application/octet-stream',
        ];
    }

    $content = attachment_storage_row_file_bytes($attachmentRow);
    if ($content === '') {
        return [
            'ok'           => false,
            'error'        => 'Attachment content not found.',
            'content'      => '',
            'content_type' => (string) ($attachmentRow['ContentType'] ?? 'application/octet-stream'),
        ];
    }

    return [
        'ok'           => true,
        'error'        => null,
        'content'      => $content,
        'content_type' => (string) ($attachmentRow['ContentType'] ?? 'application/octet-stream'),
    ];
}

function attachment_storage_delete_row_blob(array $attachmentRow): void
{
    $blobPath = trim((string) ($attachmentRow['BlobPath'] ?? ''));
    if ($blobPath === '') {
        return;
    }

    attachment_storage_delete($blobPath);
}

function attachment_storage_stream_download(array $attachmentRow, string $downloadFileName = ''): void
{
    $resolved = attachment_storage_resolve_content($attachmentRow);
    if (!$resolved['ok']) {
        http_response_code(404);
        exit('Attachment data is missing.');
    }

    $fileName = $downloadFileName !== ''
        ? $downloadFileName
        : basename((string) ($attachmentRow['FileName'] ?? 'attachment'));

    header('Content-Type: ' . $resolved['content_type']);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($resolved['content']));
    echo $resolved['content'];
    exit;
}
