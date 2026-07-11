<?php
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/marketing-site.php';
require dirname(__DIR__) . '/includes/provider-signup-landing.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /provider-signup/', true, 302);
    exit;
}

$token = trim((string) ($_GET['token'] ?? $_POST['access_token'] ?? ''));
$result = provider_signup_save_attachment($token, $_FILES['reseller_certificate'] ?? []);

if ($result['ok']) {
    header(
        'Location: /provider-signup/apply.php?token=' . rawurlencode($token) . '&notice=certificate_uploaded',
        true,
        302
    );
    exit;
}

header(
    'Location: /provider-signup/apply.php?token=' . rawurlencode($token)
        . '&upload_error=' . rawurlencode((string) ($result['error'] ?? 'Unable to upload certificate.')),
    true,
    302
);
exit;
