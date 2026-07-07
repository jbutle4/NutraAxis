<?php

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/cron-auth.php';

header('Content-Type: text/plain; charset=utf-8');

$auth = cron_auth_check();
if (!$auth['ok']) {
    http_response_code(403);
    echo $auth['error'];
    exit;
}

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "opcache_reset: ok\n";
} else {
    echo "opcache_reset: unavailable\n";
}

if (function_exists('opcache_invalidate')) {
    $files = [
        dirname(__DIR__) . '/includes/app.php',
        dirname(__DIR__) . '/includes/approval.php',
        dirname(__DIR__) . '/includes/qbo-insert-approval-token.php',
        dirname(__DIR__) . '/includes/payment-approval-token.php',
        dirname(__DIR__) . '/includes/qbo-insert-approval.php',
        dirname(__DIR__) . '/includes/payment-approval.php',
        dirname(__DIR__) . '/includes/alert-messages.php',
        dirname(__DIR__) . '/includes/data-profile.php',
        dirname(__DIR__) . '/includes/init.php',
        dirname(__DIR__) . '/includes/nav.php',
        dirname(__DIR__) . '/po-receiving/new.php',
    ];
    foreach ($files as $file) {
        opcache_invalidate($file, true);
        echo 'invalidated: ' . basename($file) . "\n";
    }
}

echo "done\n";
