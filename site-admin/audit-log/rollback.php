<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/audit.php';

audit_require_rollback();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /site-admin/audit-log/', true, 302);
    exit;
}

$logId = (int) ($_POST['log_id'] ?? 0);
if ($logId <= 0) {
    header('Location: /site-admin/audit-log/?error=' . rawurlencode('Invalid audit log entry.'), true, 302);
    exit;
}

$result = audit_execute_rollback($logId);

if ($result['ok']) {
    header('Location: /site-admin/audit-log/view.php?id=' . $logId . '&notice=rolled_back', true, 302);
    exit;
}

header('Location: /site-admin/audit-log/view.php?id=' . $logId . '&error=' . rawurlencode((string) $result['error']), true, 302);
exit;
