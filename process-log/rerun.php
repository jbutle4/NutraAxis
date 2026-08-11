<?php
require dirname(__DIR__) . '/includes/process-log-page.php';

$dataProfile = process_log_normalize_profile($dataProfile ?? ($_POST['data_profile'] ?? 'production'));
require dirname(__DIR__) . '/includes/init.php';
data_profile_set($dataProfile);
require dirname(__DIR__) . '/includes/process-runner.php';

auth_require_module_read('process-log');

if (!auth_can_update(MODULE_PERMISSION_COLUMNS['process-log'])) {
    process_log_redirect($dataProfile, 'error=' . rawurlencode('You do not have permission to rerun processes.'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    process_log_redirect($dataProfile);
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

process_log_redirect($dataProfile, $query);
