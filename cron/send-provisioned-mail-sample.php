<?php
/**
 * Send a sample Clinic Store provisioned welcome email (secured cron endpoint).
 *
 * Usage:
 *   curl -H "X-Cron-Secret: $CRON_SECRET" \
 *     "https://nutraaxisweb.azurewebsites.net/cron/send-provisioned-mail-sample.php?to=you@example.com&application_id=20"
 */

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/cron-auth.php';
require dirname(__DIR__) . '/includes/database.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

header('Content-Type: application/json; charset=utf-8');

$auth = cron_auth_check();
if (!$auth['ok']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $auth['error']], JSON_UNESCAPED_SLASHES);
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

$to = strtolower(trim((string) ($_GET['to'] ?? '')));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Valid to= email address is required.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$applicationId = max(1, (int) ($_GET['application_id'] ?? 20));
$application = provider_signup_get($applicationId);
if ($application === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Application not found.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$signInEmail = (string) ($application['AdminEmail'] ?? $application['ProviderEmail'] ?? '');
$application['ProviderEmail'] = $to;

try {
    provider_signup_mail_provisioned($application, 'SampleTempPass-ChangeMe!');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Failed to send sample email: ' . $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'ok'             => true,
    'to'             => $to,
    'application_id' => $applicationId,
    'company'        => (string) ($application['CompanyName'] ?? ''),
    'clinic_id'      => (string) ($application['AccsClinicId'] ?? ''),
    'sign_in_email'  => $signInEmail,
    'transport'      => $config['transport'],
    'message'        => 'Provisioned welcome sample sent via production SMTP.',
], JSON_UNESCAPED_SLASHES);
exit;
