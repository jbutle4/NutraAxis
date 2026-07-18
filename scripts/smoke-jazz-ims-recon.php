<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-jazz-ims-recon.php';

header('Content-Type: application/json');

$expected = trim((string) (env('SMOKE_KEY', env('NUTRA_SMOKE_KEY', ''))));
$provided = trim((string) ($_GET['key'] ?? ''));
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$env = strtolower(trim((string) ($_GET['env'] ?? 'production'))) === 'uat' ? 'uat' : 'production';
$result = inventory_jazz_ims_recon_build_rows($env);
$sample = array_slice(array_values(array_filter($result['rows'] ?? [], static fn(array $r): bool => !empty($r['mismatch']))), 0, 8);
$matched = array_slice(array_values(array_filter($result['rows'] ?? [], static fn(array $r): bool => empty($r['mismatch']))), 0, 3);

echo json_encode([
    'ok' => (bool) ($result['ok'] ?? false),
    'error' => $result['error'] ?? null,
    'jazz_env' => $result['jazz_env'] ?? $env,
    'jazz_facility_codes' => $result['jazz_facility_codes'] ?? [],
    'row_count' => count($result['rows'] ?? []),
    'mismatch_count' => (int) ($result['mismatch_count'] ?? 0),
    'sample_mismatches' => $sample,
    'sample_matches' => $matched,
], JSON_PRETTY_PRINT);
