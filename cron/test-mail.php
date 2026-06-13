<?php
/**
 * SMTP connectivity test (secured cron endpoint).
 *
 * Usage:
 *   curl -H "X-Cron-Secret: $CRON_SECRET" \
 *     "https://nutraaxisweb.azurewebsites.net/cron/test-mail.php?to=you@example.com"
 *
 * Add &process_alert=1 to simulate an abandoned-process notification.
 */

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/cron-auth.php';
require dirname(__DIR__) . '/includes/mail.php';

header('Content-Type: application/json; charset=utf-8');

$auth = cron_auth_check();
if (!$auth['ok']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $auth['error']], JSON_UNESCAPED_SLASHES);
    exit;
}

$to = trim((string) ($_GET['to'] ?? env('PROCESS_ALERT_EMAIL', 'alerts@nutraaxislabs.zendesk.com')));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Valid to= email address is required.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$config = mail_config_status();
if (!$config['smtp_configured']) {
    http_response_code(500);
    echo json_encode([
        'ok'     => false,
        'error'  => 'SMTP is not fully configured (SMTP_HOST, SMTP_USER, SMTP_PASS required).',
        'config' => $config,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$simulateProcessAlert = !empty($_GET['process_alert']);
$diagnose = !empty($_GET['diagnose']);
$compareInternal = trim((string) ($_GET['internal'] ?? env('PO_TEAM_EMAIL', 'nutrateam@nfcllc.com')));

if ($diagnose) {
    $targets = [$to];
    if ($compareInternal !== '' && filter_var($compareInternal, FILTER_VALIDATE_EMAIL) && strcasecmp($compareInternal, $to) !== 0) {
        $targets[] = $compareInternal;
    }

    $reports = [];
    foreach ($targets as $target) {
        $reports[] = mail_smtp_diagnose($target);
    }

    $allOk = array_reduce($reports, static fn(bool $carry, array $report): bool => $carry && !empty($report['ok']), true);

    http_response_code($allOk ? 200 : 500);
    echo json_encode([
        'ok'      => $allOk,
        'mode'    => 'diagnose',
        'config'  => $config,
        'reports' => $reports,
        'note'    => 'RCPT TO probe only. If internal succeeds but external fails, Microsoft 365 is likely blocking outbound mail to non-tenant domains.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($simulateProcessAlert) {
    require dirname(__DIR__) . '/includes/process-alerts.php';
    process_alert_on_abandoned(
        'test-process',
        'SMTP Test Process',
        'Simulated abandoned-process alert from /cron/test-mail.php',
        ['log_id' => 0, 'attempt_count' => 3, 'max_attempts' => 3]
    );

    echo json_encode([
        'ok'      => true,
        'mode'    => 'process_alert',
        'to'      => process_alert_zendesk_email(),
        'config'  => $config,
        'message' => 'Abandoned-process alert function invoked.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$subject = 'NutraAxis Operations SMTP test';
$body = implode("\n", [
    'This is a test message from NutraAxis Operations on Azure.',
    '',
    'Time (UTC): ' . gmdate('Y-m-d H:i:s'),
    'Endpoint: /cron/test-mail.php',
    '',
    'If you received this email, SMTP relay is working.',
]);

$send = mail_send_result($to, $subject, $body);

http_response_code($send['ok'] ? 200 : 500);
echo json_encode([
    'ok'            => $send['ok'],
    'mode'          => 'test_mail',
    'to'            => $to,
    'config'        => $config,
    'error'         => $send['error'] ?? null,
    'smtp_response' => $send['smtp_response'] ?? null,
    'message'       => $send['ok']
        ? 'SMTP accepted the message. If the inbox is empty, use Exchange message trace with smtp_response.'
        : ($send['error'] ?? 'mail_send returned false; check application logs.'),
], JSON_UNESCAPED_SLASHES);
exit;
