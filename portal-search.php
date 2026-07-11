<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/portal-search.php';

header('Content-Type: application/json; charset=utf-8');

if (!auth_is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'ok'      => false,
        'results' => [],
        'error'   => 'Login required.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));

try {
    echo json_encode([
        'ok'      => true,
        'results' => portal_search($query),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('portal_search failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'results' => [],
        'error'   => 'Search failed.',
    ], JSON_UNESCAPED_SLASHES);
}
