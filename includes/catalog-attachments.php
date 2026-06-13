<?php

require_once __DIR__ . '/catalog.php';

const CATALOG_MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

const CATALOG_ATTACHMENT_KINDS = [
    'LabelPDF'        => 'Label (print-ready PDF)',
    'SupplementFacts' => 'Supplement facts',
    'SpecSheet'       => 'Specification sheet',
    'Image'           => 'Product image',
    'Other'           => 'Other',
];

function catalog_save_attachment(int $skuId, array $file, string $kind = 'Other'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed.'];
    }

    if (($file['size'] ?? 0) > CATALOG_MAX_ATTACHMENT_BYTES) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 15 MB.'];
    }

    if (!array_key_exists($kind, CATALOG_ATTACHMENT_KINDS)) {
        $kind = 'Other';
    }

    if (catalog_get_sku($skuId) === null) {
        return ['ok' => false, 'error' => 'SKU not found.'];
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        return ['ok' => false, 'error' => 'Unable to read uploaded file.'];
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.SKUMasterAttachment (
                SKUID, FileName, ContentType, FileSizeBytes, FileData, AttachmentKind, UploadedByUser
            )
            OUTPUT INSERTED.AttachmentID AS inserted_id
            VALUES (:sku, :name, :type, :size, :data, :kind, :user)
        SQL);

        $stmt->bindValue(':sku', $skuId, PDO::PARAM_INT);
        $stmt->bindValue(':name', (string) ($file['name'] ?? 'attachment'));
        $stmt->bindValue(':type', (string) ($file['type'] ?? 'application/octet-stream'));
        $stmt->bindValue(':size', (int) $file['size'], PDO::PARAM_INT);
        $stmt->bindValue(':data', $content, PDO::PARAM_LOB);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':user', auth_user()['UserID'] ?? 0, PDO::PARAM_INT);
        $stmt->execute();

        return ['ok' => true, 'error' => null, 'id' => db_fetch_inserted_int($stmt, 'inserted_id')];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save attachment. Please try again.'];
    }
}

function catalog_list_attachments(int $skuId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            a.AttachmentID,
            a.FileName,
            a.ContentType,
            a.FileSizeBytes,
            a.AttachmentKind,
            a.UploadDate,
            u.UserName AS UploadedByName
        FROM dbo.SKUMasterAttachment a
        INNER JOIN dbo.[User] u ON u.UserID = a.UploadedByUser
        WHERE a.SKUID = :id
        ORDER BY a.UploadDate DESC
    SQL);
    $stmt->execute(['id' => $skuId]);

    return $stmt->fetchAll();
}

function catalog_get_attachment(int $attachmentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.SKUMasterAttachment WHERE AttachmentID = :id');
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function catalog_format_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}

function catalog_attachment_kind_label(string $kind): string
{
    return CATALOG_ATTACHMENT_KINDS[$kind] ?? $kind;
}
