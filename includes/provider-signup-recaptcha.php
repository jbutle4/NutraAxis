<?php

require_once __DIR__ . '/env.php';

function provider_signup_recaptcha_site_key(): string
{
    return trim((string) env_first([
        'PROVIDER_SIGNUP_RECAPTCHA_SITE_KEY',
        'RECAPTCHA_SITE_KEY',
    ], ''));
}

function provider_signup_recaptcha_secret_key(): string
{
    return trim((string) env_first([
        'PROVIDER_SIGNUP_RECAPTCHA_SECRET_KEY',
        'RECAPTCHA_SECRET_KEY',
    ], ''));
}

function provider_signup_recaptcha_configured(): bool
{
    return provider_signup_recaptcha_site_key() !== ''
        && provider_signup_recaptcha_secret_key() !== '';
}

/**
 * Enforce captcha whenever site+secret keys are configured.
 * Email ownership challenge still runs even when captcha keys are not set yet.
 */
function provider_signup_recaptcha_required(): bool
{
    return provider_signup_recaptcha_configured();
}

/**
 * @return array{ok: bool, error: ?string, score: ?float}
 */
function provider_signup_recaptcha_verify(?string $responseToken, ?string $remoteIp = null): array
{
    if (!provider_signup_recaptcha_required()) {
        return ['ok' => true, 'error' => null, 'score' => null];
    }

    if (!provider_signup_recaptcha_configured()) {
        return [
            'ok'    => false,
            'error' => 'Application start is temporarily unavailable. Please try again later.',
            'score' => null,
        ];
    }

    $responseToken = trim((string) $responseToken);
    if ($responseToken === '') {
        return ['ok' => false, 'error' => 'Please complete the captcha and try again.', 'score' => null];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'Unable to verify captcha right now.', 'score' => null];
    }

    $postFields = [
        'secret'   => provider_signup_recaptcha_secret_key(),
        'response' => $responseToken,
    ];
    $remoteIp = trim((string) $remoteIp);
    if ($remoteIp !== '') {
        $postFields['remoteip'] = $remoteIp;
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postFields),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (is_resource($ch) || $ch instanceof CurlHandle) {
        curl_close($ch);
    }

    if ($raw === false || $status >= 400) {
        return ['ok' => false, 'error' => 'Unable to verify captcha right now.', 'score' => null];
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to verify captcha right now.', 'score' => null];
    }

    if (empty($data['success'])) {
        return ['ok' => false, 'error' => 'Captcha verification failed. Please try again.', 'score' => null];
    }

    $score = isset($data['score']) ? (float) $data['score'] : null;
    // v3 responses include score; v2 checkbox does not.
    if ($score !== null && $score < 0.5) {
        return ['ok' => false, 'error' => 'Captcha verification failed. Please try again.', 'score' => $score];
    }

    return ['ok' => true, 'error' => null, 'score' => $score];
}
