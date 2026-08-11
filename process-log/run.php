<?php
require dirname(__DIR__) . '/includes/process-log-page.php';

$dataProfile = process_log_normalize_profile($dataProfile ?? ($_POST['data_profile'] ?? 'production'));
require dirname(__DIR__) . '/includes/init.php';
data_profile_set($dataProfile);
require dirname(__DIR__) . '/includes/process-runner.php';

auth_require_module_read('process-log');

if (!auth_can_update(MODULE_PERMISSION_COLUMNS['process-log'])) {
    process_log_redirect($dataProfile, 'error=' . rawurlencode('You do not have permission to run processes.'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    process_log_redirect($dataProfile);
}

$code = trim((string) ($_POST['process_code'] ?? ''));
$user = auth_user();
$userId = is_array($user) ? (int) ($user['UserID'] ?? 0) : 0;

if ($code === '' || process_registry_entry($code) === null) {
    process_log_redirect($dataProfile, 'error=' . rawurlencode('Unknown process code.'));
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

process_log_redirect($dataProfile, $query);
