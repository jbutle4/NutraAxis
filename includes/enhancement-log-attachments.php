<?php

require_once __DIR__ . '/enhancement-log.php';
require_once __DIR__ . '/attachment-storage.php';

const ENH_LOG_MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

const ENH_LOG_ATTACHMENT_KINDS = [
    'Screenshot' => 'Screenshot',
    'Other'      => 'Other',
];

const ENH_LOG_ATTACHMENT_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

function enh_log_can_add_attachments(): bool
{
    return enhancement_log_can_update();
}

function enh_log_attachment_kind_label(string $kind): string
{
    return ENH_LOG_ATTACHMENT_KINDS[$kind] ?? $kind;
}

function enh_log_is_allowed_image(array $file): bool
{
    $fileName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (in_array($extension, ENH_LOG_ATTACHMENT_EXTENSIONS, true)) {
        return true;
    }

    $contentType = strtolower(trim((string) ($file['type'] ?? '')));

    return str_starts_with($contentType, 'image/');
}

function enh_log_guess_content_type(string $fileName, string $reportedType = ''): string
{
    $reportedType = strtolower(trim($reportedType));
    if ($reportedType !== '' && str_starts_with($reportedType, 'image/')) {
        return $reportedType;
    }

    return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
        'png'        => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif'        => 'image/gif',
        'webp'       => 'image/webp',
        default      => 'image/png',
    };
}

function enh_log_read_file_bytes_base64(int $attachmentId): string
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $stmt = $pdo->prepare(<<<SQL
        SELECT CAST(N'' AS XML).value('xs:base64Binary(sql:column("FileData"))', 'VARCHAR(MAX)') AS FileDataB64
        FROM dbo.EnhLogAttachment
        WHERE AttachmentID = :id
    SQL);
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || !isset($row['FileDataB64']) || $row['FileDataB64'] === null || $row['FileDataB64'] === '') {
        return '';
    }

    $decoded = base64_decode((string) $row['FileDataB64'], true);

    return $decoded === false ? '' : $decoded;
}

function enh_log_read_file_bytes_hex(int $attachmentId): string
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $stmt = $pdo->prepare('SELECT CONVERT(VARCHAR(MAX), FileData, 2) AS FileDataHex FROM dbo.EnhLogAttachment WHERE AttachmentID = :id');
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || !isset($row['FileDataHex']) || $row['FileDataHex'] === null || $row['FileDataHex'] === '') {
        return '';
    }

    $bytes = hex2bin((string) $row['FileDataHex']);

    return $bytes === false ? '' : $bytes;
}

function enh_log_read_file_bytes_binary(int $attachmentId): string
{
    $pdo = db();
    db_apply_sql_server_options($pdo);
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $previousEncoding = null;

    if ($driver === 'sqlsrv' && defined('PDO::SQLSRV_ATTR_ENCODING') && defined('PDO::SQLSRV_ENCODING_BINARY')) {
        $previousEncoding = $pdo->getAttribute(PDO::SQLSRV_ATTR_ENCODING);
        $pdo->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_BINARY);
    }

    try {
        $stmt = $pdo->prepare('SELECT FileData FROM dbo.EnhLogAttachment WHERE AttachmentID = :id');
        $stmt->execute(['id' => $attachmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !array_key_exists('FileData', $row)) {
            return '';
        }

        $fileData = $row['FileData'];
        if (is_resource($fileData)) {
            $content = stream_get_contents($fileData);

            return $content === false ? '' : $content;
        }

        return (string) $fileData;
    } finally {
        if ($previousEncoding !== null && defined('PDO::SQLSRV_ENCODING_UTF8')) {
            $pdo->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_UTF8);
        }
    }
}

function enh_log_bytes_match_expected(string $bytes, int $expectedSize): bool
{
    if ($bytes === '') {
        return false;
    }

    if ($expectedSize > 0 && strlen($bytes) !== $expectedSize) {
        return false;
    }

    if (strlen($bytes) < 4) {
        return false;
    }

    $header = bin2hex(substr($bytes, 0, 4));

    return in_array($header, ['89504e47', 'ffd8ffe0', 'ffd8ffe1', 'ffd8ffe2', 'ffd8ffe3', 'ffd8ffe8'], true)
        || str_starts_with($header, 'ffd8ff')
        || str_starts_with($header, '47494638')
        || str_starts_with($header, '52494646');
}

function enh_log_read_file_bytes(int $attachmentId): string
{
    if ($attachmentId <= 0) {
        return '';
    }

    $attachment = enh_log_get_attachment($attachmentId);
    if ($attachment === null) {
        return '';
    }

    $expectedSize = (int) ($attachment['FileSizeBytes'] ?? 0);
    $blobPath = trim((string) ($attachment['BlobPath'] ?? ''));
    if ($blobPath !== '') {
        $resolved = attachment_storage_resolve_content($attachment);
        if ($resolved['ok'] && enh_log_bytes_match_expected($resolved['content'], $expectedSize)) {
            return $resolved['content'];
        }
    }

    foreach ([
        'enh_log_read_file_bytes_base64',
        'enh_log_read_file_bytes_hex',
        'enh_log_read_file_bytes_binary',
    ] as $reader) {
        $bytes = $reader($attachmentId);
        if (enh_log_bytes_match_expected($bytes, $expectedSize)) {
            return $bytes;
        }
    }

    $resolved = attachment_storage_resolve_content($attachment);
    if ($resolved['ok'] && $resolved['content'] !== '') {
        return $resolved['content'];
    }

    return '';
}

function enh_log_attachment_data_uri(int $attachmentId, ?array $attachment = null): ?string
{
    $attachment ??= enh_log_get_attachment($attachmentId);
    if ($attachment === null) {
        return null;
    }

    $bytes = enh_log_read_file_bytes($attachmentId);
    if ($bytes === '') {
        return null;
    }

    $contentType = enh_log_attachment_content_type($attachment);

    return 'data:' . $contentType . ';base64,' . base64_encode($bytes);
}

function enh_log_attachment_bytes(array $attachment): string
{
    $attachmentId = (int) ($attachment['AttachmentID'] ?? 0);
    if ($attachmentId > 0) {
        return enh_log_read_file_bytes($attachmentId);
    }

    return '';
}

function enh_log_attachment_content_type(array $attachment): string
{
    return enh_log_guess_content_type(
        (string) ($attachment['FileName'] ?? 'screenshot.png'),
        (string) ($attachment['ContentType'] ?? '')
    );
}

function enh_log_attachment_view_path(int $logId, int $attachmentId): string
{
    return '/enhancement-log/image.php?log_id=' . $logId . '&id=' . $attachmentId;
}

function enh_log_save_attachment(int $logId, array $file, string $kind = 'Screenshot'): array
{
    if (enhancement_log_get($logId) === null) {
        return ['ok' => false, 'error' => 'Backlog item not found.'];
    }

    if (!enh_log_can_add_attachments()) {
        return ['ok' => false, 'error' => 'You do not have permission to add attachments.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed.'];
    }

    if (($file['size'] ?? 0) > ENH_LOG_MAX_ATTACHMENT_BYTES) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 15 MB.'];
    }

    if (!enh_log_is_allowed_image($file)) {
        return ['ok' => false, 'error' => 'Only image files are allowed (PNG, JPG, GIF, or WebP).'];
    }

    if (!array_key_exists($kind, ENH_LOG_ATTACHMENT_KINDS)) {
        $kind = 'Screenshot';
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        return ['ok' => false, 'error' => 'Unable to read uploaded file.'];
    }

    $fileName = (string) ($file['name'] ?? 'attachment');
    $contentType = enh_log_guess_content_type($fileName, (string) ($file['type'] ?? ''));

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.EnhLogAttachment (
                EnhancementLogID, FileName, ContentType, FileSizeBytes, FileData, BlobPath, AttachmentKind, UploadedByUser
            )
            OUTPUT INSERTED.AttachmentID AS inserted_id
            VALUES (:log_id, :name, :type, :size, NULL, NULL, :kind, :user)
        SQL);

        $stmt->bindValue(':log_id', $logId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $fileName);
        $stmt->bindValue(':type', $contentType);
        $stmt->bindValue(':size', strlen($content), PDO::PARAM_INT);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':user', auth_user()['UserID'] ?? null, PDO::PARAM_INT);
        $stmt->execute();

        $id = db_fetch_inserted_int($stmt, 'inserted_id');
        $stored = attachment_storage_save('enh-log', $logId, $id, $fileName, $contentType, $content);
        if (!$stored['ok']) {
            $pdo->prepare('DELETE FROM dbo.EnhLogAttachment WHERE AttachmentID = :id')->execute(['id' => $id]);

            return ['ok' => false, 'error' => $stored['error'] ?? 'Unable to save attachment to blob storage.'];
        }

        $pdo->prepare('UPDATE dbo.EnhLogAttachment SET BlobPath = :path, FileData = NULL WHERE AttachmentID = :id')
            ->execute(['path' => $stored['blob_path'], 'id' => $id]);

        return ['ok' => true, 'error' => null, 'id' => $id];
    } catch (Throwable $e) {
        error_log('enh_log_save_attachment: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to save attachment.'];
    }
}

function enh_log_list_attachments(int $logId): array
{
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $stmt = $pdo->prepare(<<<SQL
        SELECT
            a.AttachmentID,
            a.FileName,
            a.ContentType,
            a.FileSizeBytes,
            a.AttachmentKind,
            a.UploadedAt,
            u.UserName AS UploadedByName
        FROM dbo.EnhLogAttachment a
        LEFT JOIN dbo.[User] u ON u.UserID = a.UploadedByUser
        WHERE a.EnhancementLogID = :id
        ORDER BY a.UploadedAt DESC, a.AttachmentID DESC
    SQL);
    $stmt->execute(['id' => $logId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function enh_log_get_attachment(int $attachmentId): ?array
{
    if ($attachmentId <= 0) {
        return null;
    }

    $pdo = db();
    db_apply_sql_server_options($pdo);

    $stmt = $pdo->prepare(<<<SQL
        SELECT
            AttachmentID,
            EnhancementLogID,
            FileName,
            ContentType,
            FileSizeBytes,
            BlobPath,
            AttachmentKind,
            UploadedByUser,
            UploadedAt
        FROM dbo.EnhLogAttachment
        WHERE AttachmentID = :id
    SQL);
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function enh_log_attachment_download_path(int $logId, int $attachmentId): string
{
    return '/enhancement-log/attachment.php?log_id=' . $logId . '&id=' . $attachmentId;
}

function enh_log_format_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}

function enh_log_delete_attachment(int $logId, int $attachmentId): array
{
    if (enhancement_log_get($logId) === null) {
        return ['ok' => false, 'error' => 'Backlog item not found.'];
    }

    if (!enh_log_can_add_attachments()) {
        return ['ok' => false, 'error' => 'You do not have permission to remove attachments.'];
    }

    $attachment = enh_log_get_attachment($attachmentId);
    if ($attachment === null || (int) ($attachment['EnhancementLogID'] ?? 0) !== $logId) {
        return ['ok' => false, 'error' => 'Attachment not found.'];
    }

    try {
        attachment_storage_delete_row_blob($attachment);

        $pdo = db();
        db_apply_sql_server_options($pdo);
        $pdo->prepare('DELETE FROM dbo.EnhLogAttachment WHERE AttachmentID = :id AND EnhancementLogID = :log_id')
            ->execute(['id' => $attachmentId, 'log_id' => $logId]);

        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        error_log('enh_log_delete_attachment: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to delete attachment.'];
    }
}
