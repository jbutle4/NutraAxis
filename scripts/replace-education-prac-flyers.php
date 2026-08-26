<?php
/**
 * Replace existing Education Resources PDFs with revised practitioner flyers.
 *
 * Usage:
 *   php scripts/replace-education-prac-flyers.php
 */
chdir(dirname(__DIR__));
require 'includes/env.php';
require 'includes/database.php';
require 'includes/education-resources.php';

$dir = '/Users/jbutle4/Library/CloudStorage/OneDrive-NationalFinancialCompanies(NEW)/Nutra Collaboration - General/Marketing/One Pagers/Practitioner Flyers/Revised One Pagers';

if (!file_storage_is_configured()) {
    fwrite(STDERR, "Azure Blob Storage is not configured (AZURE_STORAGE_CONNECTION_STRING).\n");
    exit(1);
}

if (!is_dir($dir)) {
    fwrite(STDERR, "Revised flyers folder not found:\n{$dir}\n");
    exit(1);
}

$pdo = db();
$updatedBy = null;
try {
    $userId = (int) $pdo->query("SELECT TOP 1 UserID FROM dbo.[User] WHERE UserLogin LIKE N'%jbutle4%' OR UserName LIKE N'%Butler%' ORDER BY UserID")->fetchColumn();
    if ($userId > 0) {
        $updatedBy = $userId;
    }
} catch (Throwable) {
}

$files = glob($dir . '/*.pdf') ?: [];
sort($files);

if ($files === []) {
    fwrite(STDERR, "No PDF files found in revised folder.\n");
    exit(1);
}

$ok = 0;
$missing = 0;
$failed = 0;

foreach ($files as $path) {
    $base = basename($path);
    $description = preg_replace('/\.pdf$/i', '', $base) ?: $base;
    echo "→ {$base} ... ";

    if (!is_readable($path)) {
        echo "FAIL (not readable)\n";
        $failed++;
        continue;
    }

    $stmt = $pdo->prepare(<<<SQL
        SELECT TOP 1 ERID, BlobPath, FileName, Description
        FROM dbo.NA_Education_Resources
        WHERE Type = N'PDF' AND Description = :description
        ORDER BY ERID
    SQL);
    $stmt->execute(['description' => $description]);
    $row = $stmt->fetch();
    if ($row === false) {
        echo "FAIL (no matching record for \"{$description}\")\n";
        $missing++;
        continue;
    }

    $erid = (int) $row['ERID'];
    $oldBlob = trim((string) ($row['BlobPath'] ?? ''));

    $fakeUpload = [
        'tmp_name' => $path,
        'name'     => $base,
        'type'     => 'application/pdf',
        'error'    => UPLOAD_ERR_OK,
        'size'     => (int) filesize($path),
    ];

    $stored = education_resources_save_upload($erid, $fakeUpload);
    if (!$stored['ok']) {
        echo 'FAIL (' . ($stored['error'] ?? 'blob upload') . ")\n";
        $failed++;
        continue;
    }

    $downloadUrl = education_resources_download_path($erid);
    $pdo->prepare(<<<SQL
        UPDATE dbo.NA_Education_Resources
        SET
            URL = :url,
            BlobPath = :blob_path,
            FileName = :file_name,
            UpdateDate = SYSUTCDATETIME(),
            UpdatedBy = :updated_by
        WHERE ERID = :id
    SQL)->execute([
        'url'        => $downloadUrl,
        'blob_path'  => $stored['blob_path'],
        'file_name'  => $stored['file_name'],
        'updated_by' => $updatedBy,
        'id'         => $erid,
    ]);

    if ($oldBlob !== '' && $oldBlob !== (string) $stored['blob_path']) {
        attachment_storage_delete($oldBlob);
    }

    echo "OK (ERID={$erid} replaced)\n";
    $ok++;
}

echo "\nDone. replaced={$ok} missing={$missing} failed={$failed}\n";
exit(($failed + $missing) > 0 ? 1 : 0);
