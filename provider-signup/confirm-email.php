<?php
/**
 * Consumes the emailed challenge token and opens the application.
 */
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

provider_signup_accs_discard_legacy_environment_cookie();

$token = trim((string) ($_GET['token'] ?? ''));
$accsEnvironment = provider_signup_accs_environment_from_request();
$result = provider_signup_confirm_email_challenge($token, $accsEnvironment);

if (!$result['ok'] || !is_array($result['application'] ?? null)) {
    $redirect = '/provider-signup/application.php?error=' . rawurlencode(
        $result['error'] ?? 'Unable to confirm your email.'
    );
    if ($accsEnvironment !== null) {
        $redirect .= '&accs_env=' . rawurlencode($accsEnvironment);
    }
    header('Location: ' . $redirect, true, 302);
    exit;
}

$accessToken = (string) ($result['application']['AccessToken'] ?? '');
header(
    'Location: /provider-signup/policy.php?token=' . rawurlencode($accessToken) . '&notice=started',
    true,
    302
);
exit;
