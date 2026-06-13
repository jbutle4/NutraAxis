<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/table-actions.php';
require_once __DIR__ . '/table-sort.php';

auth_start_session();
auth_refresh_permissions();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
