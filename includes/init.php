<?php

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

require_once __DIR__ . '/data-profile.php';
require_once __DIR__ . '/function-apps.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/table-actions.php';
require_once __DIR__ . '/table-sort.php';

auth_start_session();
auth_refresh_permissions();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

set_exception_handler(static function (Throwable $e): void {
    error_log('Portal error: ' . $e->getMessage());

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
    }

    $message = $e->getMessage();
    $isDb = $e instanceof PDOException
        || str_contains($message, 'Database')
        || str_contains($message, 'SQLSTATE');

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8" /><title>Service unavailable</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:40rem;margin:3rem auto;padding:0 1rem;line-height:1.5}</style></head><body>';
    echo '<h1>Unable to load this page</h1>';
    if ($isDb) {
        echo '<p>The portal could not read required data from SQL Server. This usually means a database migration has not been applied to production yet, or SQL Server is temporarily unavailable.</p>';
    } else {
        echo '<p>A server error occurred while loading this page.</p>';
    }
    echo '<p><a href="/">Back to portal home</a></p>';
    echo '</body></html>';
    exit;
});
