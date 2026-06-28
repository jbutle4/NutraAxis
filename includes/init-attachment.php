<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

auth_start_session();
auth_refresh_permissions();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
