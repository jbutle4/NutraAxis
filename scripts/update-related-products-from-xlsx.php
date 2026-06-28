<?php

/**
 * Update ACCS related products from RelatedProducts.xlsx.
 *
 * Usage:
 *   php scripts/update-related-products-from-xlsx.php /path/to/RelatedProducts.xlsx [--production] [--dry-run]
 */

require_once __DIR__ . '/../includes/data-profile.php';
$useProduction = in_array('--production', $argv, true);
data_profile_set($useProduction ? 'production' : 'uat');
require_once __DIR__ . '/../includes/adobe-commerce.php';

$dryRun = in_array('--dry-run', $argv, true);
$xlsxPath = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run' || $arg === '--production') {
        continue;
    }
    $xlsxPath = $arg;
    break;
}

if ($xlsxPath === null || !is_readable($xlsxPath)) {
    fwrite(STDERR, "Usage: php scripts/update-related-products-from-xlsx.php /path/to/RelatedProducts.xlsx [--production] [--dry-run]\n");
    exit(1);
}

$configError = adobe_commerce_config_error();
if ($configError !== null) {
    fwrite(STDERR, "ERROR: {$configError}\n");
    exit(1);
}

$rows = parse_related_products_xlsx($xlsxPath);
if ($rows === []) {
    fwrite(STDERR, "No SKU rows found in spreadsheet.\n");
    exit(1);
}

echo 'Environment : ' . adobe_commerce_environment() . "\n";
echo 'Tenant      : ' . adobe_commerce_tenant_id() . "\n";
echo 'Rows        : ' . count($rows) . "\n";
echo $dryRun ? "Mode        : DRY RUN\n" : "Mode        : LIVE\n";
echo str_repeat('-', 70) . "\n";

$tokenResult = adobe_commerce_get_token();
if (!$tokenResult['ok']) {
    fwrite(STDERR, 'ERROR getting token: ' . $tokenResult['error'] . "\n");
    exit(1);
}

$token    = $tokenResult['token'];
$clientId = adobe_commerce_client_id();
$baseUrl  = adobe_commerce_base_url();

$updated = 0;
$skipped = 0;
$failed  = 0;

foreach ($rows as $row) {
    $sku = $row['sku'];
    $relatedSkus = $row['related_skus'];

    $productCheck = adobe_commerce_api_request('GET', '/products/' . urlencode($sku));
    if (!$productCheck['ok']) {
        printf("[FAIL] %-12s product not found — %s\n", $sku, $productCheck['error']);
        $failed++;
        continue;
    }

    if ($relatedSkus === []) {
        printf("[SKIP] %-12s no related SKUs in spreadsheet\n", $sku);
        $skipped++;
        continue;
    }

    foreach ($relatedSkus as $relatedSku) {
        $relatedCheck = adobe_commerce_api_request('GET', '/products/' . urlencode($relatedSku));
        if (!$relatedCheck['ok']) {
            printf("[FAIL] %-12s related SKU %-12s not found in %s\n", $sku, $relatedSku, adobe_commerce_environment());
            $failed++;
            continue 2;
        }
    }

    $existing = adobe_commerce_api_request('GET', '/products/' . urlencode($sku) . '/links/related');
    $existingSkus = [];
    if ($existing['ok'] && is_array($existing['data'])) {
        foreach ($existing['data'] as $link) {
            $linked = trim((string) ($link['linked_product_sku'] ?? ''));
            if ($linked !== '') {
                $existingSkus[] = $linked;
            }
        }
    }

    if ($dryRun) {
        printf(
            "[DRY ] %-12s related => %s (existing: %s)\n",
            $sku,
            implode(', ', $relatedSkus),
            $existingSkus === [] ? 'none' : implode(', ', $existingSkus)
        );
        $updated++;
        continue;
    }

    foreach ($existingSkus as $existingSku) {
        if (!adobe_commerce_delete_related_link($baseUrl, $token, $clientId, $sku, $existingSku)) {
            printf("[FAIL] %-12s could not delete existing related link %s\n", $sku, $existingSku);
            $failed++;
            continue 2;
        }
    }

    $position = 0;
    foreach ($relatedSkus as $relatedSku) {
        $ok = adobe_commerce_add_related_link($baseUrl, $token, $clientId, $sku, $relatedSku, $position);
        if (!$ok) {
            printf("[FAIL] %-12s could not add related link %s\n", $sku, $relatedSku);
            $failed++;
            continue 2;
        }
        $position++;
    }

    printf("[OK  ] %-12s related => %s\n", $sku, implode(', ', $relatedSkus));
    $updated++;
}

echo str_repeat('-', 70) . "\n";
echo "Done. Updated: {$updated}  Skipped: {$skipped}  Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);

function parse_related_products_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is required to read XLSX files.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open XLSX file.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml !== false) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } elseif (isset($si->r)) {
                    $text = '';
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                    $sharedStrings[] = $text;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $sheetXml = $zip->getFromName('xl/worksheets/sheet3.xml');
    }
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('Worksheet XML not found in XLSX.');
    }

    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        throw new RuntimeException('Invalid worksheet XML.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            preg_match('/([A-Z]+)/', $ref, $m);
            $col = $m[1] ?? '';
            $type = (string) ($cell['t'] ?? '');
            $value = isset($cell->v) ? (string) $cell->v : '';
            if ($type === 's' && $value !== '' && isset($sharedStrings[(int) $value])) {
                $value = $sharedStrings[(int) $value];
            }
            $cells[$col] = $value;
        }

        $sku = normalize_sku_code($cells['B'] ?? '');
        if ($sku === '' || strcasecmp($sku, 'SKUCode') === 0) {
            continue;
        }

        $relatedSkus = [];
        foreach (['F', 'H', 'J'] as $col) {
            $related = normalize_sku_code($cells[$col] ?? '');
            if ($related !== '' && !in_array($related, $relatedSkus, true)) {
                $relatedSkus[] = $related;
            }
        }

        $rows[] = [
            'sku'           => $sku,
            'related_skus'  => $relatedSkus,
            'recommended'   => trim((string) ($cells['C'] ?? '')),
        ];
    }

    return $rows;
}

function normalize_sku_code(string $value): string
{
    return strtoupper(trim(preg_replace('/\s+/', '', $value) ?? ''));
}

function adobe_commerce_add_related_link(string $baseUrl, string $token, string $clientId, string $sku, string $relatedSku, int $position): bool
{
    $url = $baseUrl . '/products/' . rawurlencode($sku) . '/links';
    $body = json_encode([
        'items' => [[
            'sku'                 => $sku,
            'link_type'           => 'related',
            'linked_product_sku'  => $relatedSku,
            'linked_product_type' => 'simple',
            'position'            => $position,
        ]],
    ]);

    return adobe_commerce_links_request('POST', $url, $token, $clientId, $body);
}

function adobe_commerce_delete_related_link(string $baseUrl, string $token, string $clientId, string $sku, string $relatedSku): bool
{
    $url = $baseUrl . '/products/' . rawurlencode($sku) . '/links/related/' . rawurlencode($relatedSku);

    return adobe_commerce_links_request('DELETE', $url, $token, $clientId, null);
}

function adobe_commerce_links_request(string $method, string $url, string $token, string $clientId, ?string $body): bool
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'x-api-key: ' . $clientId,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $options);
    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($status < 200 || $status >= 300) {
        $err = $responseBody !== false ? trim((string) $responseBody) : 'request failed';
        fwrite(STDERR, "  HTTP {$status} {$method} {$url} — {$err}\n");
        return false;
    }

    return true;
}
