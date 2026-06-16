<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-attachments.php';

te_require_read();

$id = (int) ($_GET['id'] ?? 0);
$reportId = (int) ($_GET['report_id'] ?? 0);
$attachment = te_get_attachment($id);

if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found.');
}

$report = te_get_report((int) $attachment['ReportID']);
if ($report === null || ($reportId > 0 && (int) $attachment['ReportID'] !== $reportId)) {
    http_response_code(404);
    exit('Expense report not found.');
}

header('Content-Type: ' . $attachment['ContentType']);
header('Content-Disposition: attachment; filename="' . basename($attachment['FileName']) . '"');
header('Content-Length: ' . (int) $attachment['FileSizeBytes']);
echo $attachment['FileData'];
exit;
