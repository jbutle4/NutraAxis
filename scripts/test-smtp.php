#!/usr/bin/env php
<?php
/**
 * Send a test email using configured SMTP / mail settings.
 *
 * Usage:
 *   php scripts/test-smtp.php [recipient@example.com]
 *   php scripts/test-smtp.php --process-alert [recipient@example.com]
 */

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/mail.php';

$args = array_slice($argv, 1);
$processAlert = false;

if (($args[0] ?? '') === '--process-alert') {
    $processAlert = true;
    array_shift($args);
}

$to = trim((string) ($args[0] ?? env('PROCESS_ALERT_EMAIL', 'alerts@nutraaxislabs.zendesk.com')));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/test-smtp.php [--process-alert] [recipient@example.com]\n");
    exit(1);
}

$config = mail_config_status();
echo "Mail transport: " . $config['transport'] . PHP_EOL;
echo "SMTP host: " . ($config['smtp_host'] !== '' ? $config['smtp_host'] : '(not set)') . PHP_EOL;
echo "SMTP user: " . ($config['smtp_user'] !== '' ? $config['smtp_user'] : '(not set)') . PHP_EOL;
echo "From: " . $config['from_name'] . ' <' . $config['from_address'] . '>' . PHP_EOL;
echo "To: {$to}" . PHP_EOL;

if (!$config['smtp_configured']) {
    fwrite(STDERR, "SMTP is not fully configured. Set SMTP_HOST, SMTP_USER, and SMTP_PASS.\n");
    exit(1);
}

if ($processAlert) {
    require dirname(__DIR__) . '/includes/process-alerts.php';
    process_alert_on_abandoned(
        'test-process',
        'SMTP Test Process',
        'This is a simulated abandoned-process alert for SMTP verification.',
        ['log_id' => 0, 'attempt_count' => 3, 'max_attempts' => 3]
    );
    echo "process_alert_on_abandoned invoked. Check logs and inbox for {$to}." . PHP_EOL;
    exit(0);
}

$subject = 'NutraAxis Operations SMTP test';
$body = implode("\n", [
    'This is a test message from NutraAxis Operations.',
    '',
    'Time (UTC): ' . gmdate('Y-m-d H:i:s'),
    'Host: ' . (php_uname('n') ?: 'cli'),
    '',
    'If you received this email, SMTP relay is working.',
]);

$send = mail_send_result($to, $subject, $body);
if (!$send['ok']) {
    fwrite(STDERR, ($send['error'] ?? 'mail_send returned false.') . "\n");
    exit(1);
}

echo "Test email sent successfully to {$to}." . PHP_EOL;
