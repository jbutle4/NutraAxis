<?php

require_once __DIR__ . '/supplier-invoice.php';
require_once __DIR__ . '/attachment-storage.php';

const SUPPLIER_INVOICE_MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

const SUPPLIER_INVOICE_ATTACHMENT_KINDS = [
    'InvoicePDF' => 'Invoice PDF',
    'Receipt'    => 'Receipt',
    'Supporting' => 'Supporting document',
    'Other'      => 'Other',
];

function supplier_invoice_attachment_kind_label(string $kind): string
{
    return SUPPLIER_INVOICE_ATTACHMENT_KINDS[$kind] ?? $kind;
}

function supplier_invoice_format_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}

function supplier_invoice_save_attachment(int $invoiceId, array $file, string $kind = 'InvoicePDF'): array
{
    if (supplier_invoice_get($invoiceId) === null) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }

    if (!supplier_invoice_can_update()) {
        return ['ok' => false, 'error' => 'You do not have permission to upload invoice attachments.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed.'];
    }

    if (($file['size'] ?? 0) > SUPPLIER_INVOICE_MAX_ATTACHMENT_BYTES) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 15 MB.'];
    }

    if (!array_key_exists($kind, SUPPLIER_INVOICE_ATTACHMENT_KINDS)) {
        $kind = 'Other';
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        return ['ok' => false, 'error' => 'Unable to read uploaded file.'];
    }

    try {
        $pdo = db();
        $fileName = (string) ($file['name'] ?? 'attachment');
        $contentType = (string) ($file['type'] ?? 'application/octet-stream');

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.SupplierInvoiceAttachment (
                SupplierInvoiceID, FileName, ContentType, FileSizeBytes, FileData, BlobPath,
                AttachmentKind, UploadedByUser
            )
            OUTPUT INSERTED.AttachmentID AS inserted_id
            VALUES (:invoice_id, :name, :type, :size, NULL, NULL, :kind, :user)
        SQL);

        $stmt->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $fileName);
        $stmt->bindValue(':type', $contentType);
        $stmt->bindValue(':size', (int) $file['size'], PDO::PARAM_INT);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':user', auth_user()['UserID'] ?? 0, PDO::PARAM_INT);
        $stmt->execute();

        $id = db_fetch_inserted_int($stmt, 'inserted_id');
        $stored = attachment_storage_save('supplier-invoice', $invoiceId, $id, $fileName, $contentType, $content);
        if (!$stored['ok']) {
            $pdo->prepare('DELETE FROM dbo.SupplierInvoiceAttachment WHERE AttachmentID = :id')->execute(['id' => $id]);

            return ['ok' => false, 'error' => $stored['error'] ?? 'Unable to save attachment to blob storage.'];
        }

        $pdo->prepare('UPDATE dbo.SupplierInvoiceAttachment SET BlobPath = :path, FileData = NULL WHERE AttachmentID = :id')
            ->execute(['path' => $stored['blob_path'], 'id' => $id]);

        return ['ok' => true, 'error' => null, 'id' => $id];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save attachment. Please try again.'];
    }
}

function supplier_invoice_list_attachments(int $invoiceId): array
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
        FROM dbo.SupplierInvoiceAttachment a
        INNER JOIN dbo.[User] u ON u.UserID = a.UploadedByUser
        WHERE a.SupplierInvoiceID = :id
        ORDER BY a.UploadDate DESC
    SQL);
    $stmt->execute(['id' => $invoiceId]);

    return $stmt->fetchAll();
}

function supplier_invoice_get_attachment(int $attachmentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.SupplierInvoiceAttachment WHERE AttachmentID = :id');
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}
