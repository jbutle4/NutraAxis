<?php
/**
 * Resend payment approval emails for supplier invoice IDs.
 *
 * CLI:  php scripts/resend-payment-approval-invoices.php 19 20 21
 * HTTP: /scripts/resend-payment-approval-invoices.php?key=<SMOKE_KEY>&ids=19,20,21
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/includes/init.php';
require $root . '/includes/payment-approval.php';

$isHttp = PHP_SAPI !== 'cli';

if ($isHttp) {
    $expected = trim((string) env('SMOKE_KEY', env('NUTRA_SMOKE_KEY', '')));
    $provided = trim((string) ($_GET['key'] ?? ''));
    if ($expected === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
        exit;
    }
    $ids = array_values(array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))), static fn (int $id): bool => $id > 0));
} else {
    $ids = array_values(array_filter(array_map('intval', array_slice($argv, 1)), static fn (int $id): bool => $id > 0));
}

if ($ids === []) {
    $message = 'Provide invoice id(s). CLI: php scripts/resend-payment-approval-invoices.php <id> [...]  HTTP: ?ids=19,20,21';
    if ($isHttp) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $message]);
    } else {
        fwrite(STDERR, $message . PHP_EOL);
    }
    exit(1);
}

$summary = [
    'ok' => true,
    'uat_routing' => approval_uat_testing_role_routing_enabled(),
    'smtp_configured' => mail_smtp_is_configured(),
    'results' => [],
];

foreach ($ids as $invoiceId) {
    $result = payment_approval_invoice_resubmit($invoiceId);
    $invoice = supplier_invoice_get($invoiceId);
    $doc = is_array($invoice) ? (string) ($invoice['DocNumber'] ?? (string) $invoiceId) : (string) $invoiceId;

    $entry = [
        'invoice_id' => $invoiceId,
        'doc_number' => $doc,
        'ok' => (bool) ($result['ok'] ?? false),
        'error' => $result['error'] ?? null,
        'notify' => $result['notify'] ?? null,
    ];
    $summary['results'][] = $entry;

    if (!($result['ok'] ?? false)) {
        $summary['ok'] = false;
    } elseif (is_array($result['notify'] ?? null)) {
        $notify = $result['notify'];
        if (($notify['skipped_reason'] ?? null) !== null || ($notify['failed'] ?? []) !== []) {
            $summary['ok'] = false;
        }
    }
}

if ($isHttp) {
    header('Content-Type: application/json');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit($summary['ok'] ? 0 : 1);
}

echo 'uat_routing=' . ($summary['uat_routing'] ? 'yes' : 'no') . PHP_EOL;
echo 'smtp=' . ($summary['smtp_configured'] ? 'yes' : 'no') . PHP_EOL;
$exitCode = $summary['ok'] ? 0 : 1;
foreach ($summary['results'] as $entry) {
    echo PHP_EOL . '=== ' . $entry['doc_number'] . ' (id ' . $entry['invoice_id'] . ') ===' . PHP_EOL;
    if (!$entry['ok']) {
        echo 'error: ' . (string) ($entry['error'] ?? 'unknown') . PHP_EOL;
        continue;
    }
    $notify = is_array($entry['notify']) ? $entry['notify'] : [];
    $sent = $notify['sent'] ?? [];
    $failed = $notify['failed'] ?? [];
    echo 'sent: ' . ($sent === [] ? '(none)' : implode(', ', $sent)) . PHP_EOL;
    foreach ($failed as $email => $message) {
        echo "failed {$email}: {$message}" . PHP_EOL;
    }
    if (($notify['skipped_reason'] ?? null) !== null && $sent === [] && $failed === []) {
        echo 'skipped: ' . (string) $notify['skipped_reason'] . PHP_EOL;
    }
}

exit($exitCode);
