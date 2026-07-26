<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/process-runner.php';

auth_require_module_read('process-log');

if (!auth_can_update(MODULE_PERMISSION_COLUMNS['process-log'])) {
    header('Location: /process-log/?error=' . rawurlencode('You do not have permission to run processes.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /process-log/');
    exit;
}

$code = trim((string) ($_POST['process_code'] ?? ''));
$user = auth_user();
$userId = is_array($user) ? (int) ($user['UserID'] ?? 0) : 0;

if ($code === '' || process_registry_entry($code) === null) {
    header('Location: /process-log/?error=' . rawurlencode('Unknown process code.'));
    exit;
}

$result = process_execute(
    $code,
    [],
    PROCESS_LOG_TRIGGER_MANUAL,
    $userId > 0 ? $userId : null
);

$notice = !empty($result['ok']) ? 'run_success' : 'run_failed';
$query = 'notice=' . rawurlencode($notice);

if (empty($result['ok']) && !empty($result['error'])) {
    $query .= '&error=' . rawurlencode((string) $result['error']);
}

header('Location: /process-log/?' . $query);
exit;
