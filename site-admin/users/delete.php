<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/admin.php';

auth_require_admin_delete('users');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /site-admin/users/', true, 302);
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$result = admin_delete_user($userId);

header('Location: /site-admin/users/?' . ($result['ok']
    ? 'notice=deleted'
    : 'error=' . rawurlencode((string) $result['error'])), true, 302);
exit;
