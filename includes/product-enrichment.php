<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/attachment-storage.php';

const PRODUCT_ENRICHMENT_PERMISSION_COLUMN = 'ProductCatalog';
const PRODUCT_ENRICHMENT_MAX_UPLOAD_BYTES = 15728640;
const PRODUCT_ENRICHMENT_ALLOWED_EXTENSIONS = ['pdf'];

const PRODUCT_ENRICHMENT_LIST_SORT_COLUMNS = [
    'sku'      => 'SKU',
    'product'  => 'Product',
    'pdf_link' => 'PDF link text',
    'publish'  => 'Publish',
    'modified' => 'Date|Time Modified',
];

const PRODUCT_ENRICHMENT_LIST_SORT_SQL = [
    'sku'      => 'e.SKUCode',
    'product'  => 'e.ProductName',
    'pdf_link' => 'e.PdfLinkText',
    'publish'  => 'e.Publish',
    'modified' => 'e.ModifiedDate',
];

function product_enrichment_permission_value(): ?string
{
    return auth_permission_value(PRODUCT_ENRICHMENT_PERMISSION_COLUMN);
}

function product_enrichment_can_read(): bool
{
    return auth_can_read(PRODUCT_ENRICHMENT_PERMISSION_COLUMN);
}

function product_enrichment_can_create(): bool
{
    return auth_can_create(PRODUCT_ENRICHMENT_PERMISSION_COLUMN);
}

function product_enrichment_can_update(): bool
{
    return auth_can_update(PRODUCT_ENRICHMENT_PERMISSION_COLUMN);
}

function product_enrichment_can_delete(): bool
{
    return auth_can_delete(PRODUCT_ENRICHMENT_PERMISSION_COLUMN);
}

function product_enrichment_require_read(): void
{
    auth_require_login();
    if (product_enrichment_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view product page enrichment.');
}

function product_enrichment_require_create(): void
{
    product_enrichment_require_read();
    if (product_enrichment_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create product page enrichment.');
}

function product_enrichment_require_update(): void
{
    product_enrichment_require_read();
    if (product_enrichment_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update product page enrichment.');
}

function product_enrichment_require_delete(): void
{
    product_enrichment_require_read();
    if (product_enrichment_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete product page enrichment.');
}

function product_enrichment_site_base_url(): string
{
    return rtrim((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'), '/');
}

function product_enrichment_normalize_sku(string $skuCode): string
{
    return strtolower(trim($skuCode));
}

function product_enrichment_api_decode_sku_param(string $encoded): string
{
    $encoded = trim($encoded);
    if ($encoded === '') {
        return '';
    }

    $normalized = strtr($encoded, '-_', '+/');
    $padding = strlen($normalized) % 4;
    if ($padding > 0) {
        $normalized .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($normalized, true);
    if (!is_string($decoded) || $decoded === '') {
        return '';
    }

    return product_enrichment_normalize_sku($decoded);
}

function product_enrichment_api_read_sku(): string
{
    if (strcasecmp((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 'POST') === 0) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            try {
                $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($body)) {
                    $encodedSku = product_enrichment_api_decode_sku_param((string) ($body['s'] ?? ''));
                    if ($encodedSku !== '') {
                        return $encodedSku;
                    }

                    $sku = product_enrichment_normalize_sku((string) ($body['sku'] ?? ''));
                    if ($sku !== '') {
                        return $sku;
                    }
                }
            } catch (Throwable) {
                /* fall through */
            }
        }
    }

    $encodedSku = product_enrichment_api_decode_sku_param((string) ($_GET['s'] ?? ''));
    if ($encodedSku !== '') {
        return $encodedSku;
    }

    return product_enrichment_normalize_sku((string) ($_GET['sku'] ?? ''));
}

function product_enrichment_api_respond(): void
{
    require_once __DIR__ . '/coa-public-api.php';

    coa_public_handle_preflight();

    $sku = product_enrichment_api_read_sku();
    if ($sku === '') {
        coa_public_json_response([
            'ok'    => false,
            'error' => 'SKU is required.',
        ], 400);
    }

    $row = product_enrichment_get_published_by_sku($sku);
    if ($row === null) {
        coa_public_json_response([
            'ok'    => false,
            'error' => 'No published enrichment found for this product.',
        ], 404);
    }

    coa_public_json_response([
        'ok'           => true,
        'generated_at' => gmdate('c'),
        'item'         => product_enrichment_to_public_api_item($row),
    ]);
}

function product_enrichment_build_file_name(string $productName): string
{
    $product = preg_replace('/[^A-Za-z0-9]+/', '', $productName) ?? '';

    if ($product === '') {
        return 'InfoSheet.pdf';
    }

    return $product . 'InfoSheet.pdf';
}

function product_enrichment_public_pdf_url(string $skuCode): string
{
    $row = product_enrichment_get_published_by_sku($skuCode);
    if ($row !== null) {
        return product_enrichment_public_pdf_url_for_row($row);
    }

    $sku = product_enrichment_normalize_sku($skuCode);

    return product_enrichment_site_base_url() . '/product-enrichment-files/download.php?sku=' . rawurlencode($sku);
}

function product_enrichment_public_pdf_url_for_row(array $row): string
{
    $id = (int) ($row['ProductEnrichmentID'] ?? 0);
    if ($id <= 0) {
        return '';
    }

    return product_enrichment_site_base_url() . '/product-enrichment-files/download.php?id=' . $id;
}

function product_enrichment_admin_pdf_url(int $productEnrichmentId): string
{
    return product_enrichment_site_base_url() . '/product-enrichment/download.php?id=' . $productEnrichmentId;
}

function product_enrichment_public_api_url(string $skuCode): string
{
    return product_enrichment_site_base_url() . '/coa-test/pdp-enrichment.php';
}

function product_enrichment_lookup_sku_master(string $skuCode): ?array
{
    $normalized = product_enrichment_normalize_sku($skuCode);
    if ($normalized === '') {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT SKUID, SKUCode, ProductName
        FROM dbo.SKUMaster
        WHERE LOWER(SKUCode) = :sku
    SQL);
    $stmt->execute(['sku' => $normalized]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function product_enrichment_from_input(array $input): array
{
    return [
        'product_enrichment_id' => (int) ($input['product_enrichment_id'] ?? 0),
        'sku_code'              => product_enrichment_normalize_sku((string) ($input['sku_code'] ?? '')),
        'product_name'          => trim((string) ($input['product_name'] ?? '')),
        'enrichment_html'       => trim((string) ($input['enrichment_html'] ?? '')),
        'pdf_link_text'         => trim((string) ($input['pdf_link_text'] ?? '')),
        'is_published'          => !empty($input['is_published']),
        'notes'                 => trim((string) ($input['notes'] ?? '')),
    ];
}

function product_enrichment_row_to_form(array $row): array
{
    return [
        'product_enrichment_id' => (int) ($row['ProductEnrichmentID'] ?? 0),
        'sku_code'              => (string) ($row['SKUCode'] ?? ''),
        'product_name'          => (string) ($row['ProductName'] ?? ''),
        'enrichment_html'       => (string) ($row['EnrichmentHtml'] ?? ''),
        'pdf_link_text'         => (string) ($row['PdfLinkText'] ?? ''),
        'is_published'          => !empty($row['Publish']),
        'notes'                 => (string) ($row['Notes'] ?? ''),
    ];
}

function product_enrichment_pdf_link_html(string $pdfUrl, string $linkText): string
{
    $text = $linkText !== '' ? $linkText : 'Product Information Sheet';

    return '<p><a href="' . htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</a></p>';
}

const PRODUCT_ENRICHMENT_CONTAINER_STYLE = 'max-width: 720px; margin-left: auto; margin-right: auto; padding: 24px 16px; width: 100%; box-sizing: border-box; font-family: inherit; color: inherit;';

function product_enrichment_normalize_display_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $html = preg_replace_callback('/<\/?ol\b/i', static function (array $matches): string {
        return str_starts_with(strtolower($matches[0]), '</') ? '</ul>' : '<ul';
    }, $html) ?? $html;

    if (!preg_match('/max-width:\s*720px/i', $html)) {
        $style = htmlspecialchars(PRODUCT_ENRICHMENT_CONTAINER_STYLE, ENT_QUOTES, 'UTF-8');

        return '<div class="pdp-enrichment-inner" style="' . $style . '">' . $html . '</div>';
    }

    return $html;
}

function product_enrichment_scrub_public_html(string $html, int $lookupKey): string
{
    $pdfUrl = product_enrichment_site_base_url()
        . '/coa-test/pe-pdf-feed.php?h=' . rawurlencode((string) $lookupKey);

    $html = preg_replace(
        '#https?://[^"\'\\s]*product-enrichment-files/download\\.php\\?sku=[^"\'\\s]+#i',
        $pdfUrl,
        $html
    ) ?? $html;

    return preg_replace('/\\?sku=[^"\'&\\s>]+/i', '', $html) ?? $html;
}

function product_enrichment_render_html(array $row, ?string $pdfUrlOverride = null, bool $omitSku = false): string
{
    $sku = (string) ($row['SKUCode'] ?? '');
    $pdfUrl = $pdfUrlOverride ?? product_enrichment_public_pdf_url($sku);
    $linkText = trim((string) ($row['PdfLinkText'] ?? ''));
    $html = (string) ($row['EnrichmentHtml'] ?? '');

    $replacements = [
        '{{PDF_URL}}'       => $pdfUrl,
        '{{PDF_LINK_TEXT}}' => $linkText !== '' ? $linkText : 'Product Information Sheet',
        '{{PDF_LINK}}'      => product_enrichment_pdf_link_html($pdfUrl, $linkText),
        '{{SKU}}'           => $omitSku ? '' : $sku,
    ];

    if ($html !== '') {
        $rendered = strtr($html, $replacements);
        if ($linkText !== '' && !str_contains($html, '{{PDF_LINK}}') && !str_contains($html, '{{PDF_URL}}')) {
            $rendered .= product_enrichment_pdf_link_html($pdfUrl, $linkText);
        }

        return product_enrichment_normalize_display_html($rendered);
    }

    if (trim((string) ($row['BlobPath'] ?? '')) === '' && trim((string) ($row['FileName'] ?? '')) === '') {
        return '';
    }

    return product_enrichment_pdf_link_html($pdfUrl, $linkText);
}

function product_enrichment_to_api_item(array $row): array
{
    $sku = (string) ($row['SKUCode'] ?? '');

    return [
        'sku'           => $sku,
        'product_name'  => (string) ($row['ProductName'] ?? ''),
        'html'          => product_enrichment_render_html($row),
        'pdf_url'       => product_enrichment_public_pdf_url($sku),
        'pdf_link_text' => (string) ($row['PdfLinkText'] ?? ''),
    ];
}

function product_enrichment_to_public_api_item(array $row): array
{
    $pdfUrl = product_enrichment_public_pdf_url_for_row($row);
    $lookupKey = product_enrichment_sku_lookup_key((string) ($row['SKUCode'] ?? ''));
    $html = product_enrichment_scrub_public_html(
        product_enrichment_render_html($row, $pdfUrl, true),
        $lookupKey
    );

    return [
        'product_name'  => (string) ($row['ProductName'] ?? ''),
        'html'          => $html,
        'pdf_url'       => $pdfUrl,
        'pdf_link_text' => (string) ($row['PdfLinkText'] ?? ''),
    ];
}

function product_enrichment_validate_form(array $form): ?string
{
    if ($form['sku_code'] === '') {
        return 'SKU code is required.';
    }

    if (!preg_match('/^na-[a-z]{2}-\d{3}$/', $form['sku_code'])) {
        return 'SKU code must match the NutraAxis format (for example, na-gw-002).';
    }

    if ($form['product_name'] === '') {
        $master = product_enrichment_lookup_sku_master($form['sku_code']);
        if ($master === null) {
            return 'Product name is required when the SKU is not in Product SKU Master.';
        }
    }

    return null;
}

function product_enrichment_validate_upload(?array $file, bool $required): ?string
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $required ? 'PDF file is required.' : null;
    }

    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'PDF is too large for the server upload limit (max 15 MB).',
            UPLOAD_ERR_PARTIAL => 'PDF upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded PDF.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the PDF upload.',
            default => 'Unable to upload the PDF file.',
        };
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > PRODUCT_ENRICHMENT_MAX_UPLOAD_BYTES) {
        return 'PDF must be greater than 0 bytes and no larger than 15 MB.';
    }

    $name = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, PRODUCT_ENRICHMENT_ALLOWED_EXTENSIONS, true)) {
        return 'Only PDF files are allowed.';
    }

    $mime = (string) ($file['type'] ?? '');
    if ($mime !== '' && stripos($mime, 'pdf') === false) {
        return 'Only PDF files are allowed.';
    }

    return null;
}

function product_enrichment_list(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            e.ProductEnrichmentID,
            e.SKUCode,
            e.ProductName,
            e.PdfLinkText,
            e.FileName,
            e.Publish,
            e.ModifiedDate,
            u.UserName AS ModifiedByName
        FROM dbo.ProductEnrichment e
        LEFT JOIN dbo.[User] u ON u.UserID = e.ModifiedByUser
        WHERE 1 = 1
    SQL;
    $params = [];

    if (($filters['published'] ?? '') === '1') {
        $sql .= ' AND e.Publish = 1';
    } elseif (($filters['published'] ?? '') === '0') {
        $sql .= ' AND e.Publish = 0';
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (e.SKUCode LIKE :q OR e.ProductName LIKE :q OR e.PdfLinkText LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sortState = table_sort_state(PRODUCT_ENRICHMENT_LIST_SORT_COLUMNS, 'sku', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(PRODUCT_ENRICHMENT_LIST_SORT_SQL, $sortState, 'sku', 'sku');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function product_enrichment_list_published(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT
            ProductEnrichmentID,
            SKUCode,
            ProductName,
            EnrichmentHtml,
            PdfLinkText,
            FileName,
            BlobPath,
            ContentType,
            FileSizeBytes,
            Publish
        FROM dbo.ProductEnrichment
        WHERE Publish = 1
        ORDER BY SKUCode ASC
    SQL);

    return $stmt->fetchAll();
}

function product_enrichment_get(int $productEnrichmentId): ?array
{
    if ($productEnrichmentId <= 0) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            e.*,
            cu.UserName AS CreatedByName,
            mu.UserName AS ModifiedByName
        FROM dbo.ProductEnrichment e
        LEFT JOIN dbo.[User] cu ON cu.UserID = e.CreatedByUser
        LEFT JOIN dbo.[User] mu ON mu.UserID = e.ModifiedByUser
        WHERE e.ProductEnrichmentID = :id
    SQL);
    $stmt->execute(['id' => $productEnrichmentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function product_enrichment_get_by_sku(string $skuCode): ?array
{
    $normalized = product_enrichment_normalize_sku($skuCode);
    if ($normalized === '') {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.ProductEnrichment WHERE SKUCode = :sku');
    $stmt->execute(['sku' => $normalized]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function product_enrichment_sku_lookup_key(string $skuCode): int
{
    $sku = product_enrichment_normalize_sku($skuCode);
    $hash = 0;
    $length = strlen($sku);

    for ($i = 0; $i < $length; $i++) {
        $hash = ((($hash << 5) - $hash) + ord($sku[$i])) & 0xFFFFFFFF;
        if ($hash > 0x7FFFFFFF) {
            $hash -= 0x100000000;
        }
    }

    return (int) $hash;
}

function product_enrichment_get_published_by_lookup_key(int $lookupKey): ?array
{
    if ($lookupKey === 0) {
        return null;
    }

    foreach (product_enrichment_list(['published' => '1']) as $summary) {
        $sku = (string) ($summary['SKUCode'] ?? '');
        if (product_enrichment_sku_lookup_key($sku) === $lookupKey) {
            return product_enrichment_get_published_by_sku($sku);
        }
    }

    return null;
}

function product_enrichment_get_published_by_sku(string $skuCode): ?array
{
    $row = product_enrichment_get_by_sku($skuCode);
    if ($row === null || empty($row['Publish'])) {
        return null;
    }

    return $row;
}

function product_enrichment_find_live_pdf_url(string $skuCode): ?string
{
    $sku = product_enrichment_normalize_sku($skuCode);
    if ($sku === '') {
        return null;
    }

    $page = product_enrichment_fetch_url(
        PRODUCT_ENRICHMENT_SITE_ORIGIN . '/enrichment/pdp/' . rawurlencode($sku) . '.plain.html'
    );
    if (!$page['ok']) {
        return null;
    }

    $rawHtml = product_enrichment_extract_site_html($page['body']);
    if ($rawHtml === null) {
        return null;
    }

    $transformed = product_enrichment_transform_site_html($rawHtml);
    if ($transformed['pdf_path'] === null) {
        return null;
    }

    return str_starts_with((string) $transformed['pdf_path'], 'http')
        ? (string) $transformed['pdf_path']
        : PRODUCT_ENRICHMENT_SITE_ORIGIN . (str_starts_with((string) $transformed['pdf_path'], '/') ? '' : '/')
            . (string) $transformed['pdf_path'];
}

function product_enrichment_upload_pdf_from_url(int $productEnrichmentId, string $productName, string $pdfUrl): array
{
    $pdf = product_enrichment_fetch_url($pdfUrl);
    if (!$pdf['ok']) {
        return ['ok' => false, 'error' => $pdf['error'] ?? 'Unable to download PDF.', 'row' => null];
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'pe-import-');
    if ($tempFile === false) {
        return ['ok' => false, 'error' => 'Unable to create temporary file for PDF import.', 'row' => null];
    }

    file_put_contents($tempFile, $pdf['body']);
    $file = [
        'name'     => product_enrichment_build_file_name($productName),
        'type'     => 'application/pdf',
        'tmp_name' => $tempFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => strlen($pdf['body']),
    ];

    $upload = product_enrichment_save_upload($productEnrichmentId, $file, $productName);
    @unlink($tempFile);

    if (!$upload['ok']) {
        return ['ok' => false, 'error' => $upload['error'] ?? 'Unable to save PDF.', 'row' => null];
    }

    return [
        'ok'    => true,
        'error' => null,
        'row'   => product_enrichment_get($productEnrichmentId),
    ];
}

function product_enrichment_ensure_pdf(array $row): array
{
    if (trim((string) ($row['BlobPath'] ?? '')) !== '') {
        return ['ok' => true, 'error' => null, 'row' => $row];
    }

    $recordId = (int) ($row['ProductEnrichmentID'] ?? 0);
    $sku = (string) ($row['SKUCode'] ?? '');
    $productName = (string) ($row['ProductName'] ?? '');
    if ($recordId <= 0 || $sku === '') {
        return ['ok' => false, 'error' => 'Invalid enrichment record.', 'row' => $row];
    }

    if ($productName === '') {
        $master = product_enrichment_lookup_sku_master($sku);
        $productName = $master !== null ? (string) ($master['ProductName'] ?? '') : $sku;
    }

    $pdfUrl = product_enrichment_find_live_pdf_url($sku);
    if ($pdfUrl === null) {
        return ['ok' => false, 'error' => 'No information sheet PDF found on the live site for this SKU.', 'row' => $row];
    }

    return product_enrichment_upload_pdf_from_url($recordId, $productName, $pdfUrl);
}

function product_enrichment_stream_or_backfill(array $row, bool $inline = true): void
{
    $ensure = product_enrichment_ensure_pdf($row);
    if (!$ensure['ok'] || $ensure['row'] === null) {
        http_response_code(404);
        exit($ensure['error'] ?? 'Product information sheet not found.');
    }

    product_enrichment_stream_document($ensure['row'], $inline);
}

function product_enrichment_save_upload(int $productEnrichmentId, array $file, string $productName): array
{
    $content = file_get_contents((string) ($file['tmp_name'] ?? ''));
    if ($content === false || $content === '') {
        return ['ok' => false, 'error' => 'Unable to read uploaded PDF.', 'blob_path' => null];
    }

    $fileName = product_enrichment_build_file_name($productName);
    $contentType = trim((string) ($file['type'] ?? ''));
    if ($contentType === '') {
        $contentType = 'application/pdf';
    }

    $stored = attachment_storage_save(
        'product-enrichment',
        $productEnrichmentId,
        $productEnrichmentId,
        $fileName,
        $contentType,
        $content
    );

    if (!$stored['ok']) {
        return [
            'ok'        => false,
            'error'     => $stored['error'] ?? 'Unable to save PDF to blob storage.',
            'blob_path' => null,
        ];
    }

    $blobPath = (string) ($stored['blob_path'] ?? '');

    db()->prepare(<<<SQL
        UPDATE dbo.ProductEnrichment
        SET
            FileName = :file_name,
            ContentType = :content_type,
            FileSizeBytes = :file_size,
            BlobPath = :blob_path,
            ModifiedDate = sysutcdatetime()
        WHERE ProductEnrichmentID = :id
    SQL)->execute([
        'file_name'    => $fileName,
        'content_type' => $contentType,
        'file_size'    => strlen($content),
        'blob_path'    => $blobPath,
        'id'           => $productEnrichmentId,
    ]);

    return ['ok' => true, 'error' => null, 'blob_path' => $blobPath];
}

function product_enrichment_save(array $input, ?array $file = null): array
{
    $form = product_enrichment_from_input($input);
    $productEnrichmentId = (int) $form['product_enrichment_id'];
    $isEdit = $productEnrichmentId > 0;
    $existing = $isEdit ? product_enrichment_get($productEnrichmentId) : null;

    if ($isEdit && $existing === null) {
        return ['ok' => false, 'error' => 'Product enrichment record not found.', 'id' => 0];
    }

    if ($form['product_name'] === '') {
        $master = product_enrichment_lookup_sku_master($form['sku_code']);
        if ($master !== null) {
            $form['product_name'] = (string) ($master['ProductName'] ?? '');
        }
    }

    $error = product_enrichment_validate_form($form);
    if ($error !== null) {
        return ['ok' => false, 'error' => $error, 'id' => 0];
    }

    $hasExistingPdf = $existing !== null && trim((string) ($existing['BlobPath'] ?? '')) !== '';
    $willUploadPdf = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($form['enrichment_html'] === '' && !$hasExistingPdf && !$willUploadPdf) {
        return ['ok' => false, 'error' => 'Provide enrichment HTML and/or upload an information sheet PDF.', 'id' => 0];
    }

    $uploadError = product_enrichment_validate_upload($file, false);
    if ($uploadError !== null) {
        return ['ok' => false, 'error' => $uploadError, 'id' => 0];
    }

    $userId = (int) (auth_user()['UserID'] ?? 0);
    $pdo = db();
    $canonicalFileName = product_enrichment_build_file_name($form['product_name']);

    try {
        if ($isEdit) {
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.ProductEnrichment
                SET
                    SKUCode = :sku_code,
                    ProductName = :product_name,
                    EnrichmentHtml = :enrichment_html,
                    PdfLinkText = :pdf_link_text,
                    FileName = :file_name,
                    Publish = :is_published,
                    Notes = :notes,
                    ModifiedByUser = :modified_by,
                    ModifiedDate = sysutcdatetime()
                WHERE ProductEnrichmentID = :id
            SQL);
            $stmt->execute([
                'sku_code'        => $form['sku_code'],
                'product_name'    => $form['product_name'] !== '' ? $form['product_name'] : null,
                'enrichment_html' => $form['enrichment_html'] !== '' ? $form['enrichment_html'] : null,
                'pdf_link_text'   => $form['pdf_link_text'] !== '' ? $form['pdf_link_text'] : null,
                'file_name'       => $hasExistingPdf || ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)
                    ? $canonicalFileName
                    : ($existing['FileName'] ?? null),
                'is_published'    => $form['is_published'] ? 1 : 0,
                'notes'           => $form['notes'] !== '' ? $form['notes'] : null,
                'modified_by'     => $userId > 0 ? $userId : null,
                'id'              => $productEnrichmentId,
            ]);
        } else {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.ProductEnrichment (
                    SKUCode, ProductName, EnrichmentHtml, PdfLinkText,
                    FileName, ContentType, Publish, Notes,
                    CreatedByUser, ModifiedByUser
                )
                OUTPUT INSERTED.ProductEnrichmentID
                VALUES (
                    :sku_code, :product_name, :enrichment_html, :pdf_link_text,
                    :file_name, :content_type, :is_published, :notes,
                    :created_by, :modified_by
                )
            SQL);
            $stmt->execute([
                'sku_code'        => $form['sku_code'],
                'product_name'    => $form['product_name'] !== '' ? $form['product_name'] : null,
                'enrichment_html' => $form['enrichment_html'] !== '' ? $form['enrichment_html'] : null,
                'pdf_link_text'   => $form['pdf_link_text'] !== '' ? $form['pdf_link_text'] : null,
                'file_name'       => ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $canonicalFileName : null,
                'content_type'    => trim((string) ($file['type'] ?? 'application/pdf')) ?: 'application/pdf',
                'is_published'    => $form['is_published'] ? 1 : 0,
                'notes'           => $form['notes'] !== '' ? $form['notes'] : null,
                'created_by'      => $userId > 0 ? $userId : null,
                'modified_by'     => $userId > 0 ? $userId : null,
            ]);
            $productEnrichmentId = (int) $stmt->fetchColumn();
        }

        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $oldBlob = trim((string) ($existing['BlobPath'] ?? ''));
            $upload = product_enrichment_save_upload($productEnrichmentId, $file, $form['product_name']);
            if (!$upload['ok']) {
                if (!$isEdit) {
                    $pdo->prepare('DELETE FROM dbo.ProductEnrichment WHERE ProductEnrichmentID = :id')
                        ->execute(['id' => $productEnrichmentId]);
                }

                return ['ok' => false, 'error' => $upload['error'] ?? 'Unable to save PDF.', 'id' => 0];
            }

            // Canonical filenames keep the same blob path on replace — do not delete the file we just wrote.
            $newBlob = trim((string) ($upload['blob_path'] ?? ''));
            if ($oldBlob !== '' && $newBlob !== '' && $oldBlob !== $newBlob) {
                attachment_storage_delete($oldBlob);
            }
        }

        return ['ok' => true, 'error' => null, 'id' => $productEnrichmentId];
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UQ_ProductEnrichment_SKUCode')) {
            return ['ok' => false, 'error' => 'An enrichment record for this SKU already exists.', 'id' => 0];
        }

        return ['ok' => false, 'error' => 'Unable to save product enrichment.', 'id' => 0];
    }
}

function product_enrichment_delete(int $productEnrichmentId): array
{
    $row = product_enrichment_get($productEnrichmentId);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Product enrichment record not found.'];
    }

    $blobPath = trim((string) ($row['BlobPath'] ?? ''));
    db()->prepare('DELETE FROM dbo.ProductEnrichment WHERE ProductEnrichmentID = :id')
        ->execute(['id' => $productEnrichmentId]);

    if ($blobPath !== '') {
        attachment_storage_delete($blobPath);
    }

    return ['ok' => true, 'error' => null];
}

function product_enrichment_stream_document(array $row, bool $inline = true): void
{
    $blobPath = trim((string) ($row['BlobPath'] ?? ''));
    if ($blobPath === '') {
        http_response_code(404);
        exit('Product information sheet is missing.');
    }

    $attachmentRow = [
        'FileName'    => (string) ($row['FileName'] ?? 'info-sheet.pdf'),
        'ContentType' => (string) ($row['ContentType'] ?? 'application/pdf'),
        'BlobPath'    => $blobPath,
        'FileData'    => null,
    ];

    $resolved = attachment_storage_resolve_content($attachmentRow);
    if (!$resolved['ok']) {
        http_response_code(404);
        exit('Product information sheet is missing.');
    }

    $fileName = basename((string) ($row['FileName'] ?? 'info-sheet.pdf'));
    header('Content-Type: ' . $resolved['content_type']);
    header(
        'Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $fileName . '"'
    );
    header('Content-Length: ' . strlen($resolved['content']));
    echo $resolved['content'];
    exit;
}

const PRODUCT_ENRICHMENT_SITE_ORIGIN = 'https://www.nutraaxislabs.com';

const PRODUCT_ENRICHMENT_DEFAULT_IMPORT_SKUS = [
    'na-gt-008',
    'na-gw-002',
    'na-gw-007',
    'na-gw-011',
    'na-gw-014',
    'na-hr-005',
    'na-hr-006',
    'na-hr-009',
    'na-if-015',
    'na-if-016',
    'na-lv-010',
    'na-lv-012',
    'na-mt-001',
    'na-mt-003',
    'na-mt-004',
    'na-ss-013',
];

function product_enrichment_fetch_url(string $url): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required to import from the live site.', 'body' => ''];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => ['Accept: text/html,application/pdf,*/*'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($body === false) {
        return ['ok' => false, 'error' => $error !== '' ? $error : 'Unable to fetch ' . $url, 'body' => ''];
    }

    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'error' => 'HTTP ' . $status . ' for ' . $url, 'body' => ''];
    }

    return ['ok' => true, 'error' => null, 'body' => (string) $body];
}

function product_enrichment_decode_html_entities(string $value): string
{
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function product_enrichment_extract_site_html(string $pageHtml): ?string
{
    if (!preg_match('/<pre><code>([\s\S]*?)<\/code><\/pre>/i', $pageHtml, $matches)) {
        return null;
    }

    $decoded = trim(product_enrichment_decode_html_entities($matches[1]));
    if ($decoded === '' || !str_starts_with($decoded, '<')) {
        return null;
    }

    return $decoded;
}

function product_enrichment_transform_site_html(string $html): array
{
    $pdfPath = null;
    $pdfLinkText = null;

    $transformed = preg_replace_callback(
        '/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>([\s\S]*?)<\/a>/i',
        static function (array $matches) use (&$pdfPath, &$pdfLinkText): string {
            $href = (string) ($matches[1] ?? '');
            $text = trim(strip_tags((string) ($matches[2] ?? '')));
            if (!preg_match('#/pdf/#i', $href) && !preg_match('/\.pdf(?:\?|#|$)/i', $href)) {
                return $matches[0];
            }

            $pdfPath = $href;
            $pdfLinkText = $text;

            return '<a href="{{PDF_URL}}" target="_blank" rel="noopener noreferrer">' . trim((string) ($matches[2] ?? '')) . '</a>';
        },
        $html
    );

    return [
        'html'          => is_string($transformed) ? $transformed : $html,
        'pdf_path'      => $pdfPath,
        'pdf_link_text' => $pdfLinkText,
    ];
}

function product_enrichment_import_from_site(string $skuCode, bool $publish = false): array
{
    $sku = product_enrichment_normalize_sku($skuCode);
    if ($sku === '') {
        return ['ok' => false, 'error' => 'SKU code is required.', 'id' => 0];
    }

    $page = product_enrichment_fetch_url(PRODUCT_ENRICHMENT_SITE_ORIGIN . '/enrichment/pdp/' . rawurlencode($sku) . '.plain.html');
    if (!$page['ok']) {
        return ['ok' => false, 'error' => $page['error'] ?? 'Unable to fetch enrichment page.', 'id' => 0];
    }

    $rawHtml = product_enrichment_extract_site_html($page['body']);
    if ($rawHtml === null) {
        return ['ok' => false, 'error' => 'No html-loader content found for ' . $sku . '.', 'id' => 0];
    }

    $transformed = product_enrichment_transform_site_html($rawHtml);
    if ($transformed['pdf_path'] === null) {
        return ['ok' => false, 'error' => 'No /pdf/ link found in enrichment HTML for ' . $sku . '.', 'id' => 0];
    }

    $master = product_enrichment_lookup_sku_master($sku);
    $productName = $master !== null
        ? (string) ($master['ProductName'] ?? '')
        : preg_replace('/\s+Information Sheet$/i', '', (string) ($transformed['pdf_link_text'] ?? '')) ?? $sku;

    $existing = product_enrichment_get_by_sku($sku);
    $input = [
        'product_enrichment_id' => (int) ($existing['ProductEnrichmentID'] ?? 0),
        'sku_code'              => $sku,
        'product_name'          => $productName,
        'enrichment_html'       => (string) $transformed['html'],
        'pdf_link_text'         => (string) ($transformed['pdf_link_text'] ?? ''),
        'is_published'          => $publish ? '1' : '0',
        'notes'                 => (string) ($existing['Notes'] ?? ''),
    ];

    $save = product_enrichment_save($input, null);
    if (!$save['ok']) {
        return $save;
    }

    $recordId = (int) $save['id'];
    $pdfUrl = product_enrichment_find_live_pdf_url($sku);
    if ($pdfUrl === null) {
        return ['ok' => false, 'error' => 'No /pdf/ link found in enrichment HTML for ' . $sku . '.', 'id' => $recordId];
    }

    $upload = product_enrichment_upload_pdf_from_url($recordId, $productName, $pdfUrl);

    if ($publish) {
        db()->prepare('UPDATE dbo.ProductEnrichment SET Publish = 1, ModifiedDate = sysutcdatetime() WHERE ProductEnrichmentID = :id')
            ->execute(['id' => $recordId]);
    }

    return ['ok' => true, 'error' => null, 'id' => $recordId];
}

function product_enrichment_import_defaults_from_site(bool $publish = false): array
{
    $results = [];

    foreach (PRODUCT_ENRICHMENT_DEFAULT_IMPORT_SKUS as $sku) {
        $results[$sku] = product_enrichment_import_from_site($sku, $publish);
    }

    $failed = array_filter($results, static fn(array $result): bool => !$result['ok']);

    return [
        'ok'      => $failed === [],
        'results' => $results,
        'error'   => $failed === [] ? null : 'Some SKUs failed to import.',
    ];
}
