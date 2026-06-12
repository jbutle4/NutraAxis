<?php

require_once __DIR__ . '/po.php';

const PO_ATTACHMENT_KINDS = ['SourcePDF', 'SignedPDF', 'ImportExcel', 'ImportCSV', 'Other'];

function po_save_attachment(int $poId, array $file, string $kind = 'SourcePDF'): array
{
    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    if (!po_can_add_notes_and_attachments($order)) {
        return ['ok' => false, 'error' => 'Attachments cannot be added for this purchase order.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed.'];
    }

    if (($file['size'] ?? 0) > PO_MAX_ATTACHMENT_BYTES) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 15 MB.'];
    }

    if (!in_array($kind, PO_ATTACHMENT_KINDS, true)) {
        $kind = 'Other';
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        return ['ok' => false, 'error' => 'Unable to read uploaded file.'];
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.POAttachment (
                POID, FileName, ContentType, FileSizeBytes, FileData, AttachmentKind, UploadedByUser
            )
            OUTPUT INSERTED.AttachmentID AS inserted_id
            VALUES (:po, :name, :type, :size, :data, :kind, :user)
        SQL);

        $stmt->bindValue(':po', $poId, PDO::PARAM_INT);
        $stmt->bindValue(':name', (string) ($file['name'] ?? 'attachment'));
        $stmt->bindValue(':type', (string) ($file['type'] ?? 'application/octet-stream'));
        $stmt->bindValue(':size', (int) $file['size'], PDO::PARAM_INT);
        $stmt->bindValue(':data', $content, PDO::PARAM_LOB);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':user', auth_user()['UserID'] ?? 0, PDO::PARAM_INT);
        $stmt->execute();

        $id = db_fetch_inserted_int($stmt, 'inserted_id');

        require_once __DIR__ . '/audit.php';
        $attachment = po_get_attachment($id);
        if ($attachment !== null) {
            audit_log_attachment_insert($attachment);
        }

        return ['ok' => true, 'error' => null, 'id' => $id];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => po_format_exception_message($e, 'save the file attachment')];
    }
}

function po_list_attachments(int $poId): array
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
        FROM dbo.POAttachment a
        INNER JOIN dbo.[User] u ON u.UserID = a.UploadedByUser
        WHERE a.POID = :id
        ORDER BY a.UploadDate DESC
    SQL);
    $stmt->execute(['id' => $poId]);

    return $stmt->fetchAll();
}

function po_get_attachment(int $attachmentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.POAttachment WHERE AttachmentID = :id');
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function po_format_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}
