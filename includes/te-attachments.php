<?php

require_once __DIR__ . '/te.php';

const TE_ATTACHMENT_KINDS = ['Receipt', 'Other'];

function te_can_add_attachments(array $report): bool
{
    if (!te_can_edit_report($report)) {
        return false;
    }

    return in_array((string) ($report['ReportStatus'] ?? ''), TE_EDITABLE_STATUSES, true);
}

function te_save_attachment(int $reportId, array $file, string $kind = 'Receipt'): array
{
    $report = te_get_report($reportId);
    if ($report === null) {
        return ['ok' => false, 'error' => 'Expense report not found.'];
    }

    if (!te_can_add_attachments($report)) {
        return ['ok' => false, 'error' => 'Receipts cannot be added for this expense report.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed.'];
    }

    if (($file['size'] ?? 0) > TE_MAX_ATTACHMENT_BYTES) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 15 MB.'];
    }

    $contentType = strtolower(trim((string) ($file['type'] ?? '')));
    $fileName = (string) ($file['name'] ?? 'attachment');
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($extension !== 'pdf' && $contentType !== 'application/pdf') {
        return ['ok' => false, 'error' => 'Only PDF receipt files are allowed.'];
    }

    if (!in_array($kind, TE_ATTACHMENT_KINDS, true)) {
        $kind = 'Receipt';
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        return ['ok' => false, 'error' => 'Unable to read uploaded file.'];
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.TEAttachment (
                ReportID, FileName, ContentType, FileSizeBytes, FileData, AttachmentKind, UploadedByUser
            )
            OUTPUT INSERTED.AttachmentID AS inserted_id
            VALUES (:report, :name, :type, :size, :data, :kind, :user)
        SQL);

        $stmt->bindValue(':report', $reportId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $fileName);
        $stmt->bindValue(':type', $contentType !== '' ? $contentType : 'application/pdf');
        $stmt->bindValue(':size', (int) $file['size'], PDO::PARAM_INT);
        $stmt->bindValue(':data', $content, PDO::PARAM_LOB);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':user', auth_user()['UserID'] ?? 0, PDO::PARAM_INT);
        $stmt->execute();

        return ['ok' => true, 'error' => null, 'id' => db_fetch_inserted_int($stmt, 'inserted_id')];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => te_format_exception_message($e, 'save the receipt attachment')];
    }
}

function te_list_attachments(int $reportId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            a.AttachmentID,
            a.FileName,
            a.ContentType,
            a.FileSizeBytes,
            a.AttachmentKind,
            a.UploadedAt,
            u.UserName AS UploadedByName
        FROM dbo.TEAttachment a
        LEFT JOIN dbo.[User] u ON u.UserID = a.UploadedByUser
        WHERE a.ReportID = :id
        ORDER BY a.UploadedAt DESC, a.AttachmentID DESC
    SQL);
    $stmt->execute(['id' => $reportId]);

    return $stmt->fetchAll();
}

function te_get_attachment(int $attachmentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.TEAttachment WHERE AttachmentID = :id');
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function te_attachment_download_path(int $reportId, int $attachmentId): string
{
    return '/travel-expense/attachment.php?report_id=' . $reportId . '&id=' . $attachmentId;
}

function te_format_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}

function te_delete_attachment(int $reportId, int $attachmentId): array
{
    $report = te_get_report($reportId);
    if ($report === null) {
        return ['ok' => false, 'error' => 'Expense report not found.'];
    }

    if (!te_can_add_attachments($report)) {
        return ['ok' => false, 'error' => 'Receipts cannot be removed for this expense report.'];
    }

    $attachment = te_get_attachment($attachmentId);
    if ($attachment === null || (int) ($attachment['ReportID'] ?? 0) !== $reportId) {
        return ['ok' => false, 'error' => 'Attachment not found.'];
    }

    try {
        $pdo = db();
        $pdo->prepare('DELETE FROM dbo.TEAttachment WHERE AttachmentID = :id AND ReportID = :report')
            ->execute(['id' => $attachmentId, 'report' => $reportId]);

        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => te_format_exception_message($e, 'delete this receipt attachment')];
    }
}
