<?php

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/alert-messages.php';

const PROCESS_ALERT_ZENDESK_EMAIL = 'alerts@nutraaxislabs.zendesk.com';

function process_alert_zendesk_email(): string
{
    $subscribers = alert_recipient_email_list(ALERT_NAME_PROCESS_ABANDONED);
    if ($subscribers !== []) {
        return $subscribers[0];
    }

    $configured = trim((string) env('PROCESS_ALERT_EMAIL', PROCESS_ALERT_ZENDESK_EMAIL));

    return $configured !== '' ? $configured : PROCESS_ALERT_ZENDESK_EMAIL;
}

function process_alert_on_abandoned(
    string $processCode,
    string $processName,
    string $errorMessage,
    array $context = []
): void
{
    $siteUrl = trim((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'));
    $processLogUrl = rtrim($siteUrl, '/') . '/process-log/';
    $subject = 'NutraAxis process abandoned: ' . $processName;

    $lines = [
        'A NutraAxis Operations background process exhausted automatic retries and was marked abandoned.',
        '',
        'Process: ' . $processName . ' (' . $processCode . ')',
        'Error: ' . $errorMessage,
        'Time (UTC): ' . gmdate('Y-m-d H:i:s'),
        'Process log: ' . $processLogUrl,
        'Action: Review Process Log and rerun manually if needed.',
    ];

    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $lines[] = ucfirst((string) $key) . ': ' . (string) $value;
        }
    }

    $body = implode("\n", $lines);
    $result = alert_send_message(ALERT_NAME_PROCESS_ABANDONED, $subject, $body);

    if (!($result['ok'] ?? false) && ($result['sent'] ?? []) === []) {
        error_log(sprintf(
            'process_alert_on_abandoned email failed for %s: %s (%s)',
            $processCode,
            $errorMessage,
            (string) ($result['skipped_reason'] ?? $result['error'] ?? 'unknown')
        ));
    }
}
