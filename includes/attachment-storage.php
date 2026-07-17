<?php

require_once __DIR__ . '/file-storage.php';
require_once __DIR__ . '/file-crypto.php';

/**
 * Save attachment bytes to Azure Blob.
 *
 * @param array{encrypt?: bool} $options Set encrypt=true for sensitive documents (ciphertext at rest in blob).
 */
function attachment_storage_save(
    string $domain,
    int $entityId,
    int $attachmentId,
    string $fileName,
    string $contentType,
    string $content,
    array $options = []
): array {
    if (!file_storage_is_configured()) {
        return ['ok' => false, 'error' => 'Azure Blob Storage is not configured.', 'blob_path' => null, 'encrypted' => false];
    }

    if ($entityId <= 0 || $attachmentId <= 0) {
        return ['ok' => false, 'error' => 'Invalid attachment identifiers.', 'blob_path' => null, 'encrypted' => false];
    }

    $encrypt = (bool) ($options['encrypt'] ?? false);
    $payload = $content;
    $blobContentType = $contentType;

    if ($encrypt) {
        try {
            $payload = file_crypto_encrypt_bytes($content);
        } catch (Throwable $e) {
            return [
                'ok'        => false,
                'error'     => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to encrypt attachment.',
                'blob_path' => null,
                'encrypted' => false,
            ];
        }
        $blobContentType = 'application/octet-stream';
    }

    $blobPath = file_storage_build_blob_path($domain, $entityId, $attachmentId, $fileName);
    $upload = file_storage_upload($blobPath, $payload, $blobContentType);
    if (!$upload['ok']) {
        return [
            'ok'        => false,
            'error'     => $upload['error'] ?? 'Unable to upload attachment to blob storage.',
            'blob_path' => null,
            'encrypted' => false,
        ];
    }

    return [
        'ok'        => true,
        'error'     => null,
        'blob_path' => (string) ($upload['blob_path'] ?? $blobPath),
        'encrypted' => $encrypt,
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

function attachment_storage_row_is_encrypted(array $attachmentRow): bool
{
    $flag = $attachmentRow['IsEncrypted'] ?? $attachmentRow['ContentEncrypted'] ?? false;
    if (is_bool($flag)) {
        return $flag;
    }

    return (int) $flag === 1;
}

/**
 * Decrypt blob/legacy payload when the row is marked encrypted or the magic header is present.
 */
function attachment_storage_decrypt_if_needed(string $content, array $attachmentRow): array
{
    $shouldDecrypt = attachment_storage_row_is_encrypted($attachmentRow)
        || file_crypto_is_encrypted_payload($content);

    if (!$shouldDecrypt) {
        return ['ok' => true, 'error' => null, 'content' => $content];
    }

    $plain = file_crypto_decrypt_bytes($content);
    if ($plain === null) {
        return [
            'ok'      => false,
            'error'   => 'Unable to decrypt attachment.',
            'content' => '',
        ];
    }

    return ['ok' => true, 'error' => null, 'content' => $plain];
}

function attachment_storage_resolve_content(array $attachmentRow): array
{
    $contentType = trim((string) ($attachmentRow['ContentType'] ?? ''));
    if ($contentType === '') {
        $contentType = 'application/octet-stream';
    }

    $blobPath = trim((string) ($attachmentRow['BlobPath'] ?? ''));
    if ($blobPath !== '') {
        $blob = attachment_storage_read($blobPath);
        if (!$blob['ok']) {
            return [
                'ok'           => false,
                'error'        => $blob['error'] ?? 'Unable to read attachment from blob storage.',
                'content'      => '',
                'content_type' => $contentType,
            ];
        }

        $decrypted = attachment_storage_decrypt_if_needed((string) $blob['content'], $attachmentRow);
        if (!$decrypted['ok']) {
            return [
                'ok'           => false,
                'error'        => $decrypted['error'],
                'content'      => '',
                'content_type' => $contentType,
            ];
        }

        $blobType = trim((string) ($blob['content_type'] ?? ''));
        if ($blobType !== '' && $blobType !== 'application/octet-stream' && !attachment_storage_row_is_encrypted($attachmentRow)) {
            $contentType = $blobType;
        }

        return [
            'ok'           => true,
            'error'        => null,
            'content'      => (string) $decrypted['content'],
            'content_type' => $contentType,
        ];
    }

    $content = attachment_storage_row_file_bytes($attachmentRow);
    if ($content === '') {
        return [
            'ok'           => false,
            'error'        => 'Attachment content not found.',
            'content'      => '',
            'content_type' => $contentType,
        ];
    }

    $decrypted = attachment_storage_decrypt_if_needed($content, $attachmentRow);
    if (!$decrypted['ok']) {
        return [
            'ok'           => false,
            'error'        => $decrypted['error'],
            'content'      => '',
            'content_type' => $contentType,
        ];
    }

    return [
        'ok'           => true,
        'error'        => null,
        'content'      => (string) $decrypted['content'],
        'content_type' => $contentType,
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
