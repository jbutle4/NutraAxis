<?php

function env_load(string $path): array
{
    static $cache = [];

    if (isset($cache[$path])) {
        return $cache[$path];
    }

    $vars = [];

    if (is_readable($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $vars[$key] = $value;
        }
    }

    return $cache[$path] = $vars;
}

function env_runtime_value(string $key): ?string
{
    foreach ([$key, 'APPSETTING_' . $key] as $candidate) {
        $value = getenv($candidate);
        if ($value !== false && $value !== '') {
            return (string) $value;
        }

        if (isset($_SERVER[$candidate]) && $_SERVER[$candidate] !== '') {
            return (string) $_SERVER[$candidate];
        }

        if (isset($_ENV[$candidate]) && $_ENV[$candidate] !== '') {
            return (string) $_ENV[$candidate];
        }
    }

    return null;
}

function env(string $key, ?string $default = null): ?string
{
    static $vars = null;

    if ($vars === null) {
        $fileVars = env_load(dirname(__DIR__) . '/.env');

        $runtimeKeys = [
            'DB_HOST', 'DB_SERVER', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PASSWORD', 'DB_PORT',
            'NUTRA_FUNCTIONS_BASE_URL', 'NUTRA_FUNCTIONS_KEY',
            'AZURE_FUNCTION_APP_URL', 'AZURE_FUNCTION_APP_URL_PRODUCTION', 'AZURE_FUNCTION_APP_KEY',
            'MAIL_FROM', 'MAIL_FROM_NAME', 'MAIL_REPLY_TO', 'SITE_URL',
            'PO_TEAM_EMAIL', 'PO_TEAM_EMAIL_REPLACE',
            'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_ENCRYPTION',
            'ZENDESK_SUBDOMAIN', 'ZENDESK_EMAIL', 'ZENDESK_API_TOKEN',
            'QBO_CLIENT_ID', 'QBO_CLIENT_SECRET', 'QBO_REDIRECT_URI', 'QBO_ENVIRONMENT',
            'QBO_CLIENT_ID_PROD', 'QBO_CLIENT_SECRET_PROD',
            'JAZZ_DOMAIN', 'JAZZ_USERNAME', 'JAZZ_PASSWORD', 'JAZZ_TENANT_CODE', 'JAZZ_BASE_URL', 'JAZZ_PAGE_SIZE',
            'JAZZ_DOMAIN_PROD', 'JAZZ_PRODUCTION_DOMAIN',
            'JAZZ_USERNAME_PROD', 'JAZZ_PRODUCTION_USERNAME',
            'JAZZ_PASSWORD_PROD', 'JAZZ_PRODUCTION_PASSWORD',
            'JAZZ_BASE_URL_PROD', 'JAZZ_PRODUCTION_BASE_URL',
            'JAZZ_UAT_DOMAIN', 'JAZZ_UAT_USERNAME', 'JAZZ_UAT_PASSWORD', 'JAZZ_UAT_BASE_URL',
            'CRON_SECRET',
            'PROCESS_ALERT_EMAIL',
        ];

        require_once __DIR__ . '/adobe-commerce-settings.php';
        $runtimeKeys = array_merge($runtimeKeys, ADOBE_COMMERCE_RUNTIME_ENV_KEYS);

        $runtimeVars = [];
        foreach ($runtimeKeys as $runtimeKey) {
            $runtimeValue = env_runtime_value($runtimeKey);
            if ($runtimeValue !== null) {
                $runtimeVars[$runtimeKey] = $runtimeValue;
            }
        }

        foreach ($fileVars as $fileKey => $fileValue) {
            if ($fileValue === '') {
                unset($fileVars[$fileKey]);
            }
        }

        $vars = array_merge($fileVars, $runtimeVars);
    }

    $value = $vars[$key] ?? $default;

    return ($value !== null && $value !== '') ? $value : $default;
}

function env_is_azure_hosted(): bool
{
    return env_runtime_value('WEBSITE_SITE_NAME') !== null
        || env_runtime_value('WEBSITE_INSTANCE_ID') !== null;
}

function env_first(array $keys, ?string $default = null): ?string
{
    foreach ($keys as $key) {
        $value = env($key);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return $default;
}
