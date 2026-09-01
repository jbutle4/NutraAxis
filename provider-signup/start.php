<?php
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /provider-signup/', true, 302);
    exit;
}

provider_signup_accs_discard_legacy_environment_cookie();

$providerEmail = trim((string) ($_POST['provider_email'] ?? ''));
$recaptcha = (string) ($_POST['g-recaptcha-response'] ?? '');
$accsEnvironment = provider_signup_accs_environment_from_request();
$result = provider_signup_request_email_challenge($providerEmail, $recaptcha, $accsEnvironment);

if (!$result['ok']) {
    $redirect = '/provider-signup/application.php?error=' . rawurlencode($result['error'] ?? 'Unable to start application.');
    if ($accsEnvironment !== null) {
        $redirect .= '&accs_env=' . rawurlencode($accsEnvironment);
    }
    header('Location: ' . $redirect, true, 302);
    exit;
}

header(
    'Location: /provider-signup/check-email.php?email=' . rawurlencode(provider_signup_normalize_email($providerEmail)),
    true,
    302
);
exit;
