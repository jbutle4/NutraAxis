<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/process-runner.php';

auth_require_module_read('process-log');

if (!auth_can_update(MODULE_PERMISSION_COLUMNS['process-log'])) {
    header('Location: /process-log/?error=' . rawurlencode('You do not have permission to rerun processes.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /process-log/');
    exit;
}

$logId = (int) ($_POST['log_id'] ?? 0);
$user = auth_user();
$userId = is_array($user) ? (int) ($user['UserID'] ?? 0) : 0;

$result = process_rerun_failed_log($logId, $userId > 0 ? $userId : null);

$notice = $result['ok'] ? 'rerun_success' : 'rerun_failed';
$query = 'notice=' . rawurlencode($notice);

if (!$result['ok'] && !empty($result['error'])) {
    $query .= '&error=' . rawurlencode((string) $result['error']);
}

header('Location: /process-log/?' . $query);
exit;
