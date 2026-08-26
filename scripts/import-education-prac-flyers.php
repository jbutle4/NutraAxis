<?php
/**
 * One-shot import: practitioner flyer PDFs → NA_Education_Resources + Azure Blob.
 *
 * Usage:
 *   php scripts/import-education-prac-flyers.php [path ...]
 *
 * If no paths are passed, imports the default Practitioner Flyers set.
 */
require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/database.php';
require dirname(__DIR__) . '/includes/education-resources.php';

$defaultDir = '/Users/jbutle4/Library/CloudStorage/OneDrive-NationalFinancialCompanies(NEW)/Nutra Collaboration - General/Marketing/One Pagers/Practitioner Flyers';

$defaultFiles = [
    'AdrenaAxis - Prac Sheet Flyer.pdf',
    'AndroAxis - Prac Sheet Flyer.pdf',
    'DIMAxis - Prac Sheet Flyer.pdf',
    'EstroAxis - Prac Sheet Flyer.pdf',
    'IronAxis - Prac Sheet Flyer.pdf',
    'IronAxis - Prac Sheet Flyerv3.pdf',
    'IronAxis - Prac Sheet Flyerv4.pdf',
    'LeanAxis - Prac Sheet Flyer.pdf',
    'MagRenew - Prac Sheet Flyer.pdf',
    'MetaGI - Prac Sheet Flyer.pdf',
    'MitoAxis - Prac Sheet Flyer.pdf',
    'MultiAxis - Prac Sheet Flyer.pdf',
    'MyoProtect - Prac Sheet Flyer.pdf',
    'OmegaAxis - Prac Sheet Flyer.pdf',
    'ProbioAxis - Prac Sheet Flyer.pdf',
    'ResolvAxis - Prac Sheet Flyer.pdf',
];

$paths = array_slice($argv, 1);
if ($paths === []) {
    foreach ($defaultFiles as $name) {
        $paths[] = $defaultDir . '/' . $name;
    }
}

if (!file_storage_is_configured()) {
    fwrite(STDERR, "Azure Blob Storage is not configured (AZURE_STORAGE_CONNECTION_STRING).\n");
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
    $updatedBy = null;
}

$ok = 0;
$skipped = 0;
$failed = 0;

foreach ($paths as $path) {
    $path = (string) $path;
    $base = basename($path);
    echo "→ {$base} ... ";

    if (!is_readable($path)) {
        echo "FAIL (not readable)\n";
        $failed++;
        continue;
    }

    $description = preg_replace('/\.pdf$/i', '', $base) ?: $base;
    $exists = $pdo->prepare(<<<SQL
        SELECT TOP 1 ERID
        FROM dbo.NA_Education_Resources
        WHERE Type = N'PDF'
          AND (
            FileName = :file_name
            OR Description = :description
          )
    SQL);
    $exists->execute([
        'file_name'   => education_resources_safe_file_name($base),
        'description' => $description,
    ]);
    if ($exists->fetch() !== false) {
        echo "SKIP (already present)\n";
        $skipped++;
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false || $content === '') {
        echo "FAIL (empty read)\n";
        $failed++;
        continue;
    }

    try {
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.NA_Education_Resources (Description, Type, URL, UpdatedBy)
            OUTPUT INSERTED.ERID AS inserted_id
            VALUES (:description, N'PDF', NULL, :updated_by)
        SQL);
        $stmt->execute([
            'description' => $description,
            'updated_by'  => $updatedBy,
        ]);
        $erid = db_fetch_inserted_int($stmt, 'inserted_id');
        if ($erid <= 0) {
            echo "FAIL (insert)\n";
            $failed++;
            continue;
        }

        $fakeUpload = [
            'tmp_name' => $path,
            'name'     => $base,
            'type'     => 'application/pdf',
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($content),
        ];

        // education_resources_save_upload reads tmp_name via file_get_contents
        $stored = education_resources_save_upload($erid, $fakeUpload);
        if (!$stored['ok']) {
            $pdo->prepare('DELETE FROM dbo.NA_Education_Resources WHERE ERID = :id')->execute(['id' => $erid]);
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

        echo "OK (ERID={$erid})\n";
        $ok++;
    } catch (Throwable $e) {
        echo 'FAIL (' . $e->getMessage() . ")\n";
        $failed++;
    }
}

echo "\nDone. ok={$ok} skipped={$skipped} failed={$failed}\n";
exit($failed > 0 ? 1 : 0);
