<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/attachment-storage.php';

const COA_PERMISSION_COLUMN = 'LabelingOperations';
const COA_MAX_UPLOAD_BYTES = 15728640;
const COA_ALLOWED_EXTENSIONS = ['pdf'];

const COA_LIST_SORT_COLUMNS = [
    'product'    => 'Product',
    'lot'        => 'Lot number',
    'expiration' => 'Expiration',
    'publish'    => 'Publish',
    'sort_order' => 'Sort order',
    'modified'   => 'Modified',
];

const COA_LIST_SORT_SQL = [
    'product'    => 'c.ProductName',
    'lot'        => 'c.LotNumber',
    'expiration' => 'c.ExpirationDate',
    'publish'    => 'c.Publish',
    'sort_order' => 'c.SortOrder',
    'modified'   => 'c.ModifiedDate',
];

const COA_LIST_SORT_NUMERIC = ['sort_order'];

function coa_permission_value(): ?string
{
    return auth_permission_value(COA_PERMISSION_COLUMN);
}

function coa_can_read(): bool
{
    return auth_can_read(COA_PERMISSION_COLUMN);
}

function coa_can_create(): bool
{
    return auth_can_create(COA_PERMISSION_COLUMN);
}

function coa_can_update(): bool
{
    return auth_can_update(COA_PERMISSION_COLUMN);
}

function coa_can_delete(): bool
{
    return auth_can_delete(COA_PERMISSION_COLUMN);
}

function coa_require_read(): void
{
    auth_require_login();
    if (coa_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view COA documents.');
}

function coa_require_create(): void
{
    coa_require_read();
    if (coa_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create COA documents.');
}

function coa_require_update(): void
{
    coa_require_read();
    if (coa_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update COA documents.');
}

function coa_require_delete(): void
{
    coa_require_read();
    if (coa_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete COA documents.');
}

function coa_site_base_url(): string
{
    return rtrim((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'), '/');
}

function coa_build_file_name(string $productName, string $lotNumber): string
{
    $product = preg_replace('/[^A-Za-z0-9]+/', '', $productName) ?? '';
    $lot = preg_replace('/[^A-Za-z0-9]+/', '', $lotNumber) ?? '';

    if ($product === '' || $lot === '') {
        return 'coa.pdf';
    }

    return $product . $lot . '.pdf';
}

function coa_local_pdf_path(array $row): ?string
{
    $candidates = [];

    $canonical = coa_build_file_name(
        (string) ($row['ProductName'] ?? ''),
        (string) ($row['LotNumber'] ?? '')
    );
    if ($canonical !== 'coa.pdf') {
        $candidates[] = $canonical;
    }

    $stored = sanitize_filename_for_path((string) ($row['FileName'] ?? ''));
    if ($stored !== '' && $stored !== 'coa.pdf') {
        $candidates[] = $stored;
    }

    $root = dirname(__DIR__) . '/coa-test/files/';
    foreach (array_unique($candidates) as $fileName) {
        $path = $root . $fileName;
        if (is_readable($path)) {
            return $path;
        }
    }

    return null;
}

function sanitize_filename_for_path(string $fileName): string
{
    $base = basename(str_replace('\\', '/', $fileName));
    $base = preg_replace('/[^\w.\- ()]+/u', '_', $base) ?? 'coa.pdf';

    return trim($base) !== '' ? trim($base) : 'coa.pdf';
}

function coa_public_pdf_url(int $coaDocumentId): string
{
    return coa_site_base_url() . '/coa-documents/download.php?id=' . $coaDocumentId;
}

function coa_format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return (string) $value;
    }
}

function coa_format_expiration_display(array $row): string
{
    $display = trim((string) ($row['ExpirationDisplay'] ?? ''));
    if ($display !== '') {
        return $display;
    }

    $date = (string) ($row['ExpirationDate'] ?? '');
    if ($date === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($date))->format('m/d/Y');
    } catch (Throwable) {
        return $date;
    }
}

function coa_from_input(array $input): array
{
    return [
        'coa_document_id'    => (int) ($input['coa_document_id'] ?? 0),
        'product_name'       => trim((string) ($input['product_name'] ?? '')),
        'lot_number'         => trim((string) ($input['lot_number'] ?? '')),
        'expiration_date'    => trim((string) ($input['expiration_date'] ?? '')),
        'expiration_display' => trim((string) ($input['expiration_display'] ?? '')),
        'is_published'       => !empty($input['is_published']),
        'sort_order'         => trim((string) ($input['sort_order'] ?? '0')),
        'notes'              => trim((string) ($input['notes'] ?? '')),
    ];
}

function coa_row_to_form(array $row): array
{
    $expirationDate = (string) ($row['ExpirationDate'] ?? '');
    if ($expirationDate !== '') {
        try {
            $expirationDate = (new DateTimeImmutable($expirationDate))->format('Y-m-d');
        } catch (Throwable) {
            /* keep raw */
        }
    }

    return [
        'coa_document_id'    => (int) ($row['CoaDocumentID'] ?? 0),
        'product_name'       => (string) ($row['ProductName'] ?? ''),
        'lot_number'         => (string) ($row['LotNumber'] ?? ''),
        'expiration_date'    => $expirationDate,
        'expiration_display' => (string) ($row['ExpirationDisplay'] ?? ''),
        'is_published'       => !empty($row['Publish']),
        'sort_order'         => (string) ($row['SortOrder'] ?? '0'),
        'notes'              => (string) ($row['Notes'] ?? ''),
    ];
}

function coa_to_api_item(array $row): array
{
    $id = (int) ($row['CoaDocumentID'] ?? 0);

    return [
        'id'                 => 'coa-' . $id,
        'product_name'       => (string) ($row['ProductName'] ?? ''),
        'lot_number'         => (string) ($row['LotNumber'] ?? ''),
        'expiration_date'    => (string) ($row['ExpirationDate'] ?? ''),
        'expiration_display' => coa_format_expiration_display($row),
        'pdf_url'            => coa_public_pdf_url($id),
    ];
}

function coa_validate_form(array $form): ?string
{
    if ($form['product_name'] === '') {
        return 'Product name is required.';
    }
    if ($form['lot_number'] === '') {
        return 'Lot number is required.';
    }
    if ($form['expiration_date'] === '') {
        return 'Expiration date is required.';
    }

    try {
        new DateTimeImmutable($form['expiration_date']);
    } catch (Throwable) {
        return 'Expiration date must be a valid date.';
    }

    if (!is_numeric($form['sort_order'])) {
        return 'Sort order must be a number.';
    }

    return null;
}

function coa_validate_upload(?array $file, bool $required): ?string
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $required ? 'PDF file is required.' : null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return 'Unable to upload the PDF file.';
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > COA_MAX_UPLOAD_BYTES) {
        return 'PDF must be greater than 0 bytes and no larger than 15 MB.';
    }

    $name = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, COA_ALLOWED_EXTENSIONS, true)) {
        return 'Only PDF files are allowed.';
    }

    $mime = (string) ($file['type'] ?? '');
    if ($mime !== '' && stripos($mime, 'pdf') === false) {
        return 'Only PDF files are allowed.';
    }

    return null;
}

function coa_list(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            c.CoaDocumentID,
            c.ProductName,
            c.LotNumber,
            c.ExpirationDate,
            c.ExpirationDisplay,
            c.FileName,
            c.Publish,
            c.SortOrder,
            c.ModifiedDate,
            u.UserName AS ModifiedByName
        FROM dbo.CoaDocument c
        LEFT JOIN dbo.[User] u ON u.UserID = c.ModifiedByUser
        WHERE 1 = 1
    SQL;
    $params = [];

    if (($filters['published'] ?? '') === '1') {
        $sql .= ' AND c.Publish = 1';
    } elseif (($filters['published'] ?? '') === '0') {
        $sql .= ' AND c.Publish = 0';
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (c.ProductName LIKE :q OR c.LotNumber LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sortState = table_sort_state(COA_LIST_SORT_COLUMNS, 'product', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(COA_LIST_SORT_SQL, $sortState, 'product', 'product');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function coa_list_published(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT
            CoaDocumentID,
            ProductName,
            LotNumber,
            ExpirationDate,
            ExpirationDisplay,
            FileName,
            BlobPath,
            Publish,
            SortOrder
        FROM dbo.CoaDocument
        WHERE Publish = 1
        ORDER BY SortOrder DESC, ProductName ASC, LotNumber ASC
    SQL);

    return $stmt->fetchAll();
}

function coa_get(int $coaDocumentId): ?array
{
    if ($coaDocumentId <= 0) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            c.*,
            cu.UserName AS CreatedByName,
            mu.UserName AS ModifiedByName
        FROM dbo.CoaDocument c
        LEFT JOIN dbo.[User] cu ON cu.UserID = c.CreatedByUser
        LEFT JOIN dbo.[User] mu ON mu.UserID = c.ModifiedByUser
        WHERE c.CoaDocumentID = :id
    SQL);
    $stmt->execute(['id' => $coaDocumentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function coa_get_published(int $coaDocumentId): ?array
{
    $row = coa_get($coaDocumentId);
    if ($row === null || empty($row['Publish'])) {
        return null;
    }

    return $row;
}

function coa_save_upload(int $coaDocumentId, array $file, string $productName, string $lotNumber): array
{
    $content = file_get_contents((string) ($file['tmp_name'] ?? ''));
    if ($content === false || $content === '') {
        return ['ok' => false, 'error' => 'Unable to read uploaded PDF.'];
    }

    $fileName = coa_build_file_name($productName, $lotNumber);
    $contentType = trim((string) ($file['type'] ?? ''));
    if ($contentType === '') {
        $contentType = 'application/pdf';
    }

    $stored = attachment_storage_save(
        'coa-documents',
        $coaDocumentId,
        $coaDocumentId,
        $fileName,
        $contentType,
        $content
    );

    if (!$stored['ok']) {
        return ['ok' => false, 'error' => $stored['error'] ?? 'Unable to save PDF to blob storage.'];
    }

    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.CoaDocument
        SET
            FileName = :file_name,
            ContentType = :content_type,
            FileSizeBytes = :file_size,
            BlobPath = :blob_path,
            ModifiedDate = sysutcdatetime()
        WHERE CoaDocumentID = :id
    SQL)->execute([
        'file_name'    => $fileName,
        'content_type' => $contentType,
        'file_size'    => strlen($content),
        'blob_path'    => (string) ($stored['blob_path'] ?? ''),
        'id'           => $coaDocumentId,
    ]);

    return ['ok' => true, 'error' => null];
}

function coa_save(array $input, ?array $file = null): array
{
    $form = coa_from_input($input);
    $coaDocumentId = (int) $form['coa_document_id'];
    $isEdit = $coaDocumentId > 0;
    $existing = $isEdit ? coa_get($coaDocumentId) : null;

    if ($isEdit && $existing === null) {
        return ['ok' => false, 'error' => 'COA document not found.', 'id' => 0];
    }

    $error = coa_validate_form($form);
    if ($error !== null) {
        return ['ok' => false, 'error' => $error, 'id' => 0];
    }

    $uploadError = coa_validate_upload($file, !$isEdit);
    if ($uploadError !== null) {
        return ['ok' => false, 'error' => $uploadError, 'id' => 0];
    }

    $userId = (int) (auth_user()['UserID'] ?? 0);
    $pdo = db();
    $canonicalFileName = coa_build_file_name($form['product_name'], $form['lot_number']);

    try {
        if ($isEdit) {
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.CoaDocument
                SET
                    ProductName = :product_name,
                    LotNumber = :lot_number,
                    ExpirationDate = :expiration_date,
                    ExpirationDisplay = :expiration_display,
                    FileName = :file_name,
                    Publish = :is_published,
                    SortOrder = :sort_order,
                    Notes = :notes,
                    ModifiedByUser = :modified_by,
                    ModifiedDate = sysutcdatetime()
                WHERE CoaDocumentID = :id
            SQL);
            $stmt->execute([
                'product_name'       => $form['product_name'],
                'lot_number'         => $form['lot_number'],
                'expiration_date'    => $form['expiration_date'],
                'expiration_display' => $form['expiration_display'] !== '' ? $form['expiration_display'] : null,
                'file_name'          => $canonicalFileName,
                'is_published'       => $form['is_published'] ? 1 : 0,
                'sort_order'         => (int) $form['sort_order'],
                'notes'              => $form['notes'] !== '' ? $form['notes'] : null,
                'modified_by'        => $userId > 0 ? $userId : null,
                'id'                 => $coaDocumentId,
            ]);
        } else {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.CoaDocument (
                    ProductName, LotNumber, ExpirationDate, ExpirationDisplay,
                    FileName, ContentType, Publish, SortOrder, Notes,
                    CreatedByUser, ModifiedByUser
                )
                OUTPUT INSERTED.CoaDocumentID
                VALUES (
                    :product_name, :lot_number, :expiration_date, :expiration_display,
                    :file_name, :content_type, :is_published, :sort_order, :notes,
                    :created_by, :modified_by
                )
            SQL);
            $stmt->execute([
                'product_name'       => $form['product_name'],
                'lot_number'         => $form['lot_number'],
                'expiration_date'    => $form['expiration_date'],
                'expiration_display' => $form['expiration_display'] !== '' ? $form['expiration_display'] : null,
                'file_name'          => $canonicalFileName,
                'content_type'       => trim((string) ($file['type'] ?? 'application/pdf')) ?: 'application/pdf',
                'is_published'       => $form['is_published'] ? 1 : 0,
                'sort_order'         => (int) $form['sort_order'],
                'notes'              => $form['notes'] !== '' ? $form['notes'] : null,
                'created_by'         => $userId > 0 ? $userId : null,
                'modified_by'        => $userId > 0 ? $userId : null,
            ]);
            $coaDocumentId = (int) $stmt->fetchColumn();
        }

        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $oldBlob = trim((string) ($existing['BlobPath'] ?? ''));
            $upload = coa_save_upload($coaDocumentId, $file, $form['product_name'], $form['lot_number']);
            if (!$upload['ok']) {
                if (!$isEdit) {
                    $pdo->prepare('DELETE FROM dbo.CoaDocument WHERE CoaDocumentID = :id')->execute(['id' => $coaDocumentId]);
                }

                return ['ok' => false, 'error' => $upload['error'] ?? 'Unable to save PDF.', 'id' => 0];
            }

            if ($oldBlob !== '') {
                attachment_storage_delete($oldBlob);
            }
        }

        return ['ok' => true, 'error' => null, 'id' => $coaDocumentId];
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UQ_CoaDocument_Product_Lot')) {
            return ['ok' => false, 'error' => 'A COA for this product and lot number already exists.', 'id' => 0];
        }

        return ['ok' => false, 'error' => 'Unable to save COA document.', 'id' => 0];
    }
}

function coa_delete(int $coaDocumentId): array
{
    $row = coa_get($coaDocumentId);
    if ($row === null) {
        return ['ok' => false, 'error' => 'COA document not found.'];
    }

    $blobPath = trim((string) ($row['BlobPath'] ?? ''));
    db()->prepare('DELETE FROM dbo.CoaDocument WHERE CoaDocumentID = :id')->execute(['id' => $coaDocumentId]);

    if ($blobPath !== '') {
        attachment_storage_delete($blobPath);
    }

    return ['ok' => true, 'error' => null];
}

function coa_stream_document(array $row, bool $inline = true): void
{
    $blobPath = trim((string) ($row['BlobPath'] ?? ''));
    if ($blobPath === '') {
        $localPath = coa_local_pdf_path($row);
        if ($localPath === null) {
            http_response_code(404);
            exit('COA file is missing.');
        }

        $fileName = basename($localPath);
        header('Content-Type: application/pdf');
        header(
            'Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $fileName . '"'
        );
        header('Content-Length: ' . (string) filesize($localPath));
        readfile($localPath);
        exit;
    }

    $attachmentRow = [
        'FileName'    => (string) ($row['FileName'] ?? 'coa.pdf'),
        'ContentType' => (string) ($row['ContentType'] ?? 'application/pdf'),
        'BlobPath'    => (string) ($row['BlobPath'] ?? ''),
        'FileData'    => null,
    ];

    $resolved = attachment_storage_resolve_content($attachmentRow);
    if (!$resolved['ok']) {
        http_response_code(404);
        exit('COA file is missing.');
    }

    $fileName = basename((string) ($row['FileName'] ?? 'coa.pdf'));
    header('Content-Type: ' . $resolved['content_type']);
    header(
        'Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $fileName . '"'
    );
    header('Content-Length: ' . strlen($resolved['content']));
    echo $resolved['content'];
    exit;
}
