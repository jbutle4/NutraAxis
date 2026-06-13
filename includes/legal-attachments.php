<?php

require_once __DIR__ . '/legal.php';

const LEGAL_MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

const LEGAL_ATTACHMENT_KINDS = [
    'ExecutedPDF' => 'Executed PDF',
    'DraftPDF'    => 'Draft PDF',
    'Amendment'   => 'Amendment',
    'Supporting'  => 'Supporting document',
    'Other'       => 'Other',
];

function legal_save_attachment(int $contractId, array $file, string $kind = 'Other'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed.'];
    }

    if (($file['size'] ?? 0) > LEGAL_MAX_ATTACHMENT_BYTES) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 15 MB.'];
    }

    if (!array_key_exists($kind, LEGAL_ATTACHMENT_KINDS)) {
        $kind = 'Other';
    }

    if (legal_get_contract($contractId) === null) {
        return ['ok' => false, 'error' => 'Contract not found.'];
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        return ['ok' => false, 'error' => 'Unable to read uploaded file.'];
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.ContractAttachment (
                ContractID, FileName, ContentType, FileSizeBytes, FileData, AttachmentKind, UploadedByUser
            )
            OUTPUT INSERTED.AttachmentID AS inserted_id
            VALUES (:contract, :name, :type, :size, :data, :kind, :user)
        SQL);

        $stmt->bindValue(':contract', $contractId, PDO::PARAM_INT);
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

function legal_list_attachments(int $contractId): array
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
        FROM dbo.ContractAttachment a
        INNER JOIN dbo.[User] u ON u.UserID = a.UploadedByUser
        WHERE a.ContractID = :id
        ORDER BY a.UploadDate DESC
    SQL);
    $stmt->execute(['id' => $contractId]);

    return $stmt->fetchAll();
}

function legal_get_attachment(int $attachmentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.ContractAttachment WHERE AttachmentID = :id');
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function legal_format_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}

function legal_attachment_kind_label(string $kind): string
{
    return LEGAL_ATTACHMENT_KINDS[$kind] ?? $kind;
}
