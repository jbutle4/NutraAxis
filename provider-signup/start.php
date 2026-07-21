<?php
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /provider-signup/', true, 302);
    exit;
}

$providerEmail = trim((string) ($_POST['provider_email'] ?? ''));
$result = provider_signup_create_application($providerEmail);

if (!$result['ok'] || !is_array($result['application'])) {
    header('Location: /provider-signup/application.php?error=' . rawurlencode($result['error'] ?? 'Unable to start application.'), true, 302);
    exit;
}

$token = (string) ($result['application']['AccessToken'] ?? '');
header('Location: /provider-signup/policy.php?token=' . rawurlencode($token) . '&notice=started', true, 302);
exit;
