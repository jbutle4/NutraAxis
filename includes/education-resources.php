<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/attachment-storage.php';

const EDUCATION_RESOURCES_PERMISSION_COLUMN = 'EducationResources';

const EDUCATION_RESOURCE_TYPES = [
    'PDF'   => 'PDF',
    'Video' => 'Video',
];

const EDUCATION_RESOURCE_MAX_UPLOAD_BYTES = 25 * 1024 * 1024;

/** @var list<string> */
const EDUCATION_RESOURCE_ALLOWED_EXTENSIONS = ['pdf'];

const EDUCATION_RESOURCES_LIST_SORT_COLUMNS = [
    'description' => 'Description',
    'type'        => 'Type',
    'created'     => 'Created',
    'updated'     => 'Updated',
];

const EDUCATION_RESOURCES_LIST_SORT_SQL = [
    'description' => 'r.Description',
    'type'        => 'r.Type',
    'created'     => 'r.CreateDate',
    'updated'     => 'r.UpdateDate',
];

function education_resources_permission_value(): ?string
{
    return auth_permission_value(EDUCATION_RESOURCES_PERMISSION_COLUMN);
}

function education_resources_can_read(): bool
{
    return auth_can_read(EDUCATION_RESOURCES_PERMISSION_COLUMN);
}

function education_resources_can_create(): bool
{
    return auth_can_create(EDUCATION_RESOURCES_PERMISSION_COLUMN);
}

function education_resources_can_update(): bool
{
    return auth_can_update(EDUCATION_RESOURCES_PERMISSION_COLUMN);
}

function education_resources_can_delete(): bool
{
    return auth_can_delete(EDUCATION_RESOURCES_PERMISSION_COLUMN);
}

function education_resources_require_read(): void
{
    auth_require_login();
    if (education_resources_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Education Resources.');
}

function education_resources_require_create(): void
{
    education_resources_require_read();
    if (education_resources_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create education resources.');
}

function education_resources_require_update(): void
{
    education_resources_require_read();
    if (education_resources_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update education resources.');
}

function education_resources_require_delete(): void
{
    education_resources_require_read();
    if (education_resources_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete education resources.');
}

function education_resources_download_path(int $erid): string
{
    return '/education-resources/download.php?id=' . $erid;
}

function education_resources_format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('America/Chicago'))->format('M j, Y g:i A');
    } catch (Throwable) {
        return (string) $value;
    }
}

/**
 * @param array<string, mixed> $input
 * @return array{erid: int, description: string, type: string, url: string}
 */
function education_resources_from_input(array $input): array
{
    $type = trim((string) ($input['type'] ?? ''));
    if (!isset(EDUCATION_RESOURCE_TYPES[$type])) {
        $type = '';
    }

    return [
        'erid'        => (int) ($input['erid'] ?? 0),
        'description' => trim((string) ($input['description'] ?? '')),
        'type'        => $type,
        'url'         => trim((string) ($input['url'] ?? '')),
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array{erid: int, description: string, type: string, url: string}
 */
function education_resources_row_to_form(array $row): array
{
    return [
        'erid'        => (int) ($row['ERID'] ?? 0),
        'description' => (string) ($row['Description'] ?? ''),
        'type'        => (string) ($row['Type'] ?? ''),
        'url'         => (string) ($row['URL'] ?? ''),
    ];
}

/**
 * @param array{erid: int, description: string, type: string, url: string} $form
 */
function education_resources_validate_form(array $form, bool $pdfUploadPresent, bool $isEdit, bool $hasExistingPdf): ?string
{
    if ($form['description'] === '') {
        return 'Description is required.';
    }
    if ($form['type'] === '' || !isset(EDUCATION_RESOURCE_TYPES[$form['type']])) {
        return 'Type must be PDF or Video.';
    }

    if ($form['type'] === 'Video') {
        if ($form['url'] === '') {
            return 'Vimeo URL is required for video resources.';
        }
        if (filter_var($form['url'], FILTER_VALIDATE_URL) === false) {
            return 'Enter a valid Vimeo URL.';
        }
        $host = strtolower((string) (parse_url($form['url'], PHP_URL_HOST) ?? ''));
        if (!str_contains($host, 'vimeo.com')) {
            return 'Video URL must be a Vimeo link (vimeo.com).';
        }

        return null;
    }

    // PDF
    if (!$pdfUploadPresent && !$isEdit) {
        return 'PDF file is required for new PDF resources.';
    }
    if (!$pdfUploadPresent && $isEdit && !$hasExistingPdf) {
        return 'PDF file is required.';
    }

    return null;
}

function education_resources_validate_upload(?array $file, bool $required): ?string
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $required ? 'PDF file is required.' : null;
    }

    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'PDF is too large for the server upload limit (max 25 MB).',
            UPLOAD_ERR_PARTIAL => 'PDF upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded PDF.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the PDF upload.',
            default => 'Unable to upload the PDF file.',
        };
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > EDUCATION_RESOURCE_MAX_UPLOAD_BYTES) {
        return 'PDF must be greater than 0 bytes and no larger than 25 MB.';
    }

    $name = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, EDUCATION_RESOURCE_ALLOWED_EXTENSIONS, true)) {
        return 'Only PDF files are allowed.';
    }

    $mime = (string) ($file['type'] ?? '');
    if ($mime !== '' && stripos($mime, 'pdf') === false) {
        return 'Only PDF files are allowed.';
    }

    return null;
}

/**
 * @param array<string, mixed> $filters
 * @return list<array<string, mixed>>
 */
function education_resources_list(array $filters = []): array
{
    $sql = <<<SQL
        SELECT
            r.ERID,
            r.Description,
            r.Type,
            r.URL,
            r.BlobPath,
            r.FileName,
            r.CreateDate,
            r.UpdateDate,
            r.UpdatedBy,
            u.UserName AS UpdatedByName
        FROM dbo.NA_Education_Resources r
        LEFT JOIN dbo.[User] u ON u.UserID = r.UpdatedBy
        WHERE 1 = 1
    SQL;
    $params = [];

    $type = trim((string) ($filters['type'] ?? ''));
    if ($type !== '' && isset(EDUCATION_RESOURCE_TYPES[$type])) {
        $sql .= ' AND r.Type = :type';
        $params['type'] = $type;
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        [$likeSql, $likeParams] = db_like_or([
            'r.Description',
            'r.Type',
            'r.URL',
            'r.FileName',
        ], $q);
        $sql .= ' AND ' . $likeSql;
        $params = array_merge($params, $likeParams);
    }

    $sortState = table_sort_state(EDUCATION_RESOURCES_LIST_SORT_COLUMNS, 'updated', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(
        EDUCATION_RESOURCES_LIST_SORT_SQL,
        $sortState,
        'updated',
        'updated'
    );

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function education_resources_get(int $erid): ?array
{
    if ($erid <= 0) {
        return null;
    }

    $stmt = db()->prepare(<<<SQL
        SELECT
            r.ERID,
            r.Description,
            r.Type,
            r.URL,
            r.BlobPath,
            r.FileName,
            r.CreateDate,
            r.UpdateDate,
            r.UpdatedBy,
            u.UserName AS UpdatedByName
        FROM dbo.NA_Education_Resources r
        LEFT JOIN dbo.[User] u ON u.UserID = r.UpdatedBy
        WHERE r.ERID = :id
    SQL);
    $stmt->execute(['id' => $erid]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function education_resources_safe_file_name(string $originalName): string
{
    $base = pathinfo($originalName, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string) $base) ?: 'education-resource';
    $base = trim($base, '.-_');
    if ($base === '') {
        $base = 'education-resource';
    }

    return substr($base, 0, 180) . '.pdf';
}

/**
 * @return array{ok: bool, error: ?string, blob_path: ?string, file_name: ?string}
 */
function education_resources_save_upload(int $erid, array $file): array
{
    $content = file_get_contents((string) ($file['tmp_name'] ?? ''));
    if ($content === false || $content === '') {
        return ['ok' => false, 'error' => 'Unable to read uploaded PDF.', 'blob_path' => null, 'file_name' => null];
    }

    $fileName = education_resources_safe_file_name((string) ($file['name'] ?? 'resource.pdf'));
    $contentType = trim((string) ($file['type'] ?? ''));
    if ($contentType === '') {
        $contentType = 'application/pdf';
    }

    $stored = attachment_storage_save(
        'education-resources',
        $erid,
        $erid,
        $fileName,
        $contentType,
        $content
    );

    if (!$stored['ok']) {
        return [
            'ok'        => false,
            'error'     => $stored['error'] ?? 'Unable to save PDF to blob storage.',
            'blob_path' => null,
            'file_name' => null,
        ];
    }

    return [
        'ok'        => true,
        'error'     => null,
        'blob_path' => (string) ($stored['blob_path'] ?? ''),
        'file_name' => $fileName,
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array{ok: bool, error: ?string, id: int}
 */
function education_resources_save(array $input, ?array $file = null): array
{
    $form = education_resources_from_input($input);
    $erid = (int) $form['erid'];
    $isEdit = $erid > 0;
    $existing = $isEdit ? education_resources_get($erid) : null;

    if ($isEdit && $existing === null) {
        return ['ok' => false, 'error' => 'Education resource not found.', 'id' => 0];
    }

    $hasUpload = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hasExistingPdf = $isEdit
        && strtoupper((string) ($existing['Type'] ?? '')) === 'PDF'
        && trim((string) ($existing['BlobPath'] ?? '')) !== '';

    $error = education_resources_validate_form($form, $hasUpload, $isEdit, $hasExistingPdf);
    if ($error !== null) {
        return ['ok' => false, 'error' => $error, 'id' => 0];
    }

    if ($form['type'] === 'PDF') {
        $uploadError = education_resources_validate_upload($file, !$isEdit || !$hasExistingPdf);
        if ($uploadError !== null) {
            return ['ok' => false, 'error' => $uploadError, 'id' => 0];
        }
    }

    $userId = (int) (auth_user()['UserID'] ?? 0);
    $pdo = db();

    try {
        if ($isEdit) {
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.NA_Education_Resources
                SET
                    Description = :description,
                    Type = :type,
                    URL = :url,
                    UpdateDate = SYSUTCDATETIME(),
                    UpdatedBy = :updated_by
                WHERE ERID = :id
            SQL);
            $stmt->execute([
                'description' => $form['description'],
                'type'        => $form['type'],
                'url'         => $form['type'] === 'Video' ? $form['url'] : education_resources_download_path($erid),
                'updated_by'  => $userId > 0 ? $userId : null,
                'id'          => $erid,
            ]);
        } else {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.NA_Education_Resources (
                    Description, Type, URL, UpdatedBy
                )
                OUTPUT INSERTED.ERID AS inserted_id
                VALUES (
                    :description, :type, :url, :updated_by
                )
            SQL);
            $stmt->execute([
                'description' => $form['description'],
                'type'        => $form['type'],
                'url'         => $form['type'] === 'Video' ? $form['url'] : null,
                'updated_by'  => $userId > 0 ? $userId : null,
            ]);
            $erid = db_fetch_inserted_int($stmt, 'inserted_id');
            if ($erid <= 0) {
                return ['ok' => false, 'error' => 'Unable to create education resource.', 'id' => 0];
            }
        }

        if ($form['type'] === 'PDF' && $hasUpload) {
            $oldBlob = trim((string) ($existing['BlobPath'] ?? ''));
            $stored = education_resources_save_upload($erid, $file ?? []);
            if (!$stored['ok']) {
                if (!$isEdit) {
                    $pdo->prepare('DELETE FROM dbo.NA_Education_Resources WHERE ERID = :id')->execute(['id' => $erid]);
                }

                return ['ok' => false, 'error' => $stored['error'], 'id' => 0];
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
                'url'         => $downloadUrl,
                'blob_path'   => $stored['blob_path'],
                'file_name'   => $stored['file_name'],
                'updated_by'  => $userId > 0 ? $userId : null,
                'id'          => $erid,
            ]);

            if ($oldBlob !== '' && $oldBlob !== (string) $stored['blob_path']) {
                attachment_storage_delete($oldBlob);
            }
        } elseif ($form['type'] === 'Video') {
            $oldBlob = trim((string) ($existing['BlobPath'] ?? ''));
            $pdo->prepare(<<<SQL
                UPDATE dbo.NA_Education_Resources
                SET
                    BlobPath = NULL,
                    FileName = NULL,
                    URL = :url,
                    UpdateDate = SYSUTCDATETIME(),
                    UpdatedBy = :updated_by
                WHERE ERID = :id
            SQL)->execute([
                'url'        => $form['url'],
                'updated_by' => $userId > 0 ? $userId : null,
                'id'         => $erid,
            ]);
            if ($oldBlob !== '') {
                attachment_storage_delete($oldBlob);
            }
        } elseif ($form['type'] === 'PDF' && $isEdit) {
            // Keep existing blob; ensure URL points at download endpoint.
            $pdo->prepare(<<<SQL
                UPDATE dbo.NA_Education_Resources
                SET
                    URL = :url,
                    UpdateDate = SYSUTCDATETIME(),
                    UpdatedBy = :updated_by
                WHERE ERID = :id
            SQL)->execute([
                'url'        => education_resources_download_path($erid),
                'updated_by' => $userId > 0 ? $userId : null,
                'id'         => $erid,
            ]);
        }

        return ['ok' => true, 'error' => null, 'id' => $erid];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Unable to save education resource.', 'id' => 0];
    }
}

/**
 * @return array{ok: bool, error: ?string}
 */
function education_resources_delete(int $erid): array
{
    $row = education_resources_get($erid);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Education resource not found.'];
    }

    $blobPath = trim((string) ($row['BlobPath'] ?? ''));
    db()->prepare('DELETE FROM dbo.NA_Education_Resources WHERE ERID = :id')->execute(['id' => $erid]);

    if ($blobPath !== '') {
        attachment_storage_delete($blobPath);
    }

    return ['ok' => true, 'error' => null];
}

/**
 * @param array<string, mixed> $row
 */
function education_resources_open_href(array $row): string
{
    $type = (string) ($row['Type'] ?? '');
    if ($type === 'PDF') {
        return education_resources_download_path((int) ($row['ERID'] ?? 0));
    }

    $url = trim((string) ($row['URL'] ?? ''));

    return $url !== '' ? $url : '#';
}

function education_resources_public_site_base_url(): string
{
    require_once __DIR__ . '/coa-public-api.php';

    return coa_public_site_base_url();
}

function education_resources_public_pdf_url(int $erid): string
{
    return education_resources_public_site_base_url() . '/education-documents/download.php?id=' . $erid;
}

/**
 * Public marketing site resources (all current rows; no Publish gate yet).
 *
 * @return list<array<string, mixed>>
 */
function education_resources_list_public(): array
{
    $stmt = db()->query(<<<SQL
        SELECT
            ERID,
            Description,
            Type,
            URL,
            BlobPath,
            FileName,
            CreateDate,
            UpdateDate
        FROM dbo.NA_Education_Resources
        ORDER BY Description ASC, ERID ASC
    SQL);

    return $stmt->fetchAll() ?: [];
}

function education_resources_get_public(int $erid): ?array
{
    $row = education_resources_get($erid);
    if ($row === null) {
        return null;
    }

    $type = strtoupper((string) ($row['Type'] ?? ''));
    if ($type !== 'PDF') {
        return null;
    }

    if (trim((string) ($row['BlobPath'] ?? '')) === '') {
        return null;
    }

    return $row;
}

/**
 * @param array<string, mixed> $row
 * @return array{id: string, description: string, type: string, url: string}
 */
function education_resources_to_api_item(array $row): array
{
    $erid = (int) ($row['ERID'] ?? 0);
    $type = (string) ($row['Type'] ?? '');
    $url = $type === 'PDF'
        ? education_resources_public_pdf_url($erid)
        : trim((string) ($row['URL'] ?? ''));

    return [
        'id'          => 'edu-' . $erid,
        'description' => (string) ($row['Description'] ?? ''),
        'type'        => $type,
        'url'         => $url,
    ];
}

/**
 * @param array<string, mixed> $row
 */
function education_resources_stream_document(array $row, bool $inline = true): void
{
    $blobPath = trim((string) ($row['BlobPath'] ?? ''));
    if ($blobPath === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('PDF file is not available.');
    }

    $resolved = attachment_storage_resolve_content([
        'BlobPath'    => $blobPath,
        'ContentType' => 'application/pdf',
        'FileName'    => (string) ($row['FileName'] ?? 'education-resource.pdf'),
    ]);

    if (!$resolved['ok']) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('PDF file is not available.');
    }

    $fileName = trim((string) ($row['FileName'] ?? ''));
    if ($fileName === '') {
        $fileName = 'education-resource-' . (int) ($row['ERID'] ?? 0) . '.pdf';
    }

    $contentType = trim((string) ($resolved['content_type'] ?? ''));
    if ($contentType === '') {
        $contentType = 'application/pdf';
    }

    header('Content-Type: ' . $contentType);
    header(
        'Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
        . '; filename="' . str_replace('"', '', $fileName) . '"'
    );
    header('Content-Length: ' . strlen((string) $resolved['content']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=300');
    echo $resolved['content'];
    exit;
}
