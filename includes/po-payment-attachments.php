<?php

require_once __DIR__ . '/po-payment.php';
require_once __DIR__ . '/attachment-storage.php';

const POPAYMENT_MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

const POPAYMENT_ATTACHMENT_KINDS = ['Remittance', 'Receipt', 'Invoice', 'Confirmation', 'Other'];

function po_payment_attachment_kind_label(string $kind): string
{
    return match ($kind) {
        'Remittance'   => 'Remittance advice',
        'Receipt'      => 'Payment receipt',
        'Invoice'      => 'Invoice',
        'Confirmation' => 'Payment confirmation',
        default        => 'Other',
    };
}

function po_payment_format_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}

function po_payment_can_manage_attachments(array $payment): bool
{
    if (po_payment_can_update()) {
        return true;
    }

    if (!empty($payment['SupplierInvoiceID'])) {
        require_once __DIR__ . '/accounting.php';

        return accounting_can_update();
    }

    return false;
}

function po_payment_save_attachment(int $paymentId, array $file, string $kind = 'Other'): array
{
    $payment = po_payment_get($paymentId);
    if ($payment === null) {
        return ['ok' => false, 'error' => 'Payment not found.'];
    }

    if (!po_payment_can_manage_attachments($payment)) {
        return ['ok' => false, 'error' => 'You do not have permission to upload payment attachments.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed.'];
    }

    if (($file['size'] ?? 0) > POPAYMENT_MAX_ATTACHMENT_BYTES) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 15 MB.'];
    }

    if (!in_array($kind, POPAYMENT_ATTACHMENT_KINDS, true)) {
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
            INSERT INTO dbo.POPaymentAttachment (
                POID, SupplierInvoiceID, PaymentID, FileName, ContentType, FileSizeBytes, FileData, BlobPath,
                AttachmentKind, UploadedByUser
            )
            OUTPUT INSERTED.POPaymentAttachmentID AS inserted_id
            VALUES (
                :po_id, :supplier_invoice_id, :payment_id, :name, :type, :size, NULL, NULL, :kind, :user
            )
        SQL);

        $poId = !empty($payment['POID']) ? (int) $payment['POID'] : null;
        $supplierInvoiceId = !empty($payment['SupplierInvoiceID']) ? (int) $payment['SupplierInvoiceID'] : null;
        $stmt->bindValue(':po_id', $poId, $poId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':supplier_invoice_id', $supplierInvoiceId, $supplierInvoiceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $fileName);
        $stmt->bindValue(':type', $contentType);
        $stmt->bindValue(':size', (int) $file['size'], PDO::PARAM_INT);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':user', auth_user()['UserID'] ?? 0, PDO::PARAM_INT);
        $stmt->execute();

        $id = db_fetch_inserted_int($stmt, 'inserted_id');
        $stored = attachment_storage_save('po-payment', $paymentId, $id, $fileName, $contentType, $content);
        if (!$stored['ok']) {
            $pdo->prepare('DELETE FROM dbo.POPaymentAttachment WHERE POPaymentAttachmentID = :id')->execute(['id' => $id]);

            return ['ok' => false, 'error' => $stored['error'] ?? 'Unable to save attachment to blob storage.'];
        }

        $pdo->prepare('UPDATE dbo.POPaymentAttachment SET BlobPath = :path, FileData = NULL WHERE POPaymentAttachmentID = :id')
            ->execute(['path' => $stored['blob_path'], 'id' => $id]);

        return ['ok' => true, 'error' => null, 'id' => $id];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => po_format_exception_message($e, 'save the payment attachment')];
    }
}

function po_payment_list_attachments(int $paymentId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            a.POPaymentAttachmentID,
            a.POID,
            a.PaymentID,
            a.FileName,
            a.ContentType,
            a.FileSizeBytes,
            a.AttachmentKind,
            a.UploadDate,
            u.UserName AS UploadedByName
        FROM dbo.POPaymentAttachment a
        INNER JOIN dbo.[User] u ON u.UserID = a.UploadedByUser
        WHERE a.PaymentID = :payment_id
        ORDER BY a.UploadDate DESC
    SQL);
    $stmt->execute(['payment_id' => $paymentId]);

    return $stmt->fetchAll();
}

function po_payment_get_attachment(int $attachmentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.POPaymentAttachment WHERE POPaymentAttachmentID = :id');
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}
