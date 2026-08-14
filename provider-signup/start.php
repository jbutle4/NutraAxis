<?php
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /provider-signup/', true, 302);
    exit;
}

$pendingAccsEnv = trim((string) ($_POST['accs_env'] ?? ''));
if ($pendingAccsEnv !== '') {
    provider_signup_accs_set_pending_environment($pendingAccsEnv);
}

$providerEmail = trim((string) ($_POST['provider_email'] ?? ''));
$recaptcha = (string) ($_POST['g-recaptcha-response'] ?? '');
$result = provider_signup_request_email_challenge($providerEmail, $recaptcha);

if (!$result['ok']) {
    header(
        'Location: /provider-signup/application.php?error=' . rawurlencode($result['error'] ?? 'Unable to start application.'),
        true,
        302
    );
    exit;
}

header(
    'Location: /provider-signup/check-email.php?email=' . rawurlencode(provider_signup_normalize_email($providerEmail)),
    true,
    302
);
exit;
