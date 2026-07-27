<?php
/**
 * Consumes the emailed challenge token and opens the application.
 */
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

$token = trim((string) ($_GET['token'] ?? ''));
$result = provider_signup_confirm_email_challenge($token);

if (!$result['ok'] || !is_array($result['application'] ?? null)) {
    header(
        'Location: /provider-signup/application.php?error=' . rawurlencode(
            $result['error'] ?? 'Unable to confirm your email.'
        ),
        true,
        302
    );
    exit;
}

$accessToken = (string) ($result['application']['AccessToken'] ?? '');
header(
    'Location: /provider-signup/policy.php?token=' . rawurlencode($accessToken) . '&notice=started',
    true,
    302
);
exit;
