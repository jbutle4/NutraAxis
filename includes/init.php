<?php

require_once __DIR__ . '/data-profile.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/table-actions.php';
require_once __DIR__ . '/table-sort.php';
require_once __DIR__ . '/list-page-header.php';
require_once __DIR__ . '/form-actions.php';

auth_start_session();
auth_refresh_permissions();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
