<?php
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/marketing-site.php';
require dirname(__DIR__) . '/includes/provider-signup-landing.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /provider-signup/', true, 302);
    exit;
}

$payload = provider_signup_read_request_payload();
$token = trim((string) ($_GET['token'] ?? $payload['access_token'] ?? ''));
$application = provider_signup_get_by_token($token);
$wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

function provider_signup_reseller_doc_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

if ($application === null) {
    if ($wantsJson) {
        provider_signup_reseller_doc_json_response(['ok' => false, 'error' => 'Application not found.'], 404);
    }

    http_response_code(404);
    echo 'Application not found.';
    exit;
}

$parsedFile = provider_signup_parse_reseller_doc_upload($payload, $_FILES);
if (!($parsedFile['ok'] ?? false)) {
    $error = (string) ($parsedFile['error'] ?? 'No file uploaded.');
    if ($wantsJson) {
        provider_signup_reseller_doc_json_response(['ok' => false, 'error' => $error]);
    }

    http_response_code(400);
    echo htmlspecialchars($error);
    exit;
}

$form = provider_signup_form_from_post_merge($payload, $application);
$draft = provider_signup_save_draft($token, $form);
if (!$draft['ok']) {
    $error = $draft['error'] ?? 'Unable to save application draft.';
    if ($wantsJson) {
        provider_signup_reseller_doc_json_response(['ok' => false, 'error' => $error]);
    }

    http_response_code(400);
    echo htmlspecialchars($error);
    exit;
}

$upload = provider_signup_save_attachment_bytes(
    $token,
    (string) $parsedFile['content'],
    (string) $parsedFile['name'],
    (string) $parsedFile['type'],
    (int) $parsedFile['size']
);

if ($upload['ok']) {
    $redirect = '/provider-signup/apply.php?token=' . rawurlencode($token) . '&notice=reseller_doc_saved';
    if ($wantsJson) {
        provider_signup_reseller_doc_json_response(['ok' => true, 'redirect' => $redirect]);
    }
    header('Location: ' . $redirect, true, 302);
    exit;
}

$error = $upload['error'] ?? 'Unable to save the reseller certificate.';
if ($wantsJson) {
    provider_signup_reseller_doc_json_response(['ok' => false, 'error' => $error]);
}

http_response_code(400);
echo htmlspecialchars($error);
