<?php

require_once __DIR__ . '/env.php';

function cron_normalize_secret(string $value): string
{
    return trim($value, " \t\n\r\0\x0B");
}

function cron_provided_secret(): string
{
    $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($queryString !== '') {
        foreach (explode('&', $queryString) as $pair) {
            if (!str_starts_with($pair, 'key=')) {
                continue;
            }

            $value = rawurldecode(substr($pair, 4));
            if ($value !== '') {
                return cron_normalize_secret($value);
            }
        }
    }

    if (isset($_GET['key']) && $_GET['key'] !== '') {
        return cron_normalize_secret((string) $_GET['key']);
    }

    if (!empty($_SERVER['HTTP_X_CRON_SECRET'])) {
        return cron_normalize_secret((string) $_SERVER['HTTP_X_CRON_SECRET']);
    }

    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
        return cron_normalize_secret($matches[1]);
    }

    return '';
}

function cron_secret(): string
{
    return cron_normalize_secret(
        (string) (env_runtime_value('CRON_SECRET') ?? env('CRON_SECRET') ?? '')
    );
}

function cron_auth_check(): array
{
    $secret = cron_secret();
    if ($secret === '') {
        return [
            'ok'    => false,
            'error' => 'CRON_SECRET is not configured in Azure App Service application settings.',
        ];
    }

    $provided = cron_provided_secret();
    if ($provided === '') {
        return [
            'ok'    => false,
            'error' => 'Missing cron key. Send header X-Cron-Secret: YOUR_SECRET, or add ?key=URL_ENCODED_SECRET to the URL.',
        ];
    }

    if (!hash_equals($secret, $provided)) {
        $lengthHint = strlen($secret) === strlen($provided)
            ? 'Character mismatch — copy CRON_SECRET from Azure App Settings and paste it into the URL (do not retype).'
            : sprintf(
                'Length mismatch — URL key has %d characters but CRON_SECRET has %d. Update the URL or App Settings so they match.',
                strlen($provided),
                strlen($secret)
            );

        return [
            'ok'    => false,
            'error' => 'Invalid cron key. ' . $lengthHint,
        ];
    }

    return ['ok' => true, 'error' => null];
}

function cron_authorized(): bool
{
    return cron_auth_check()['ok'];
}
