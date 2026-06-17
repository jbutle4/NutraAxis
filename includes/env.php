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

function env_known_runtime_keys(): array
{
    static $keys = null;

    if ($keys !== null) {
        return $keys;
    }

    $keys = [
        'DB_HOST', 'DB_SERVER', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PASSWORD', 'DB_PORT',
        'NUTRA_FUNCTIONS_BASE_URL', 'NUTRA_FUNCTIONS_KEY',
        'AZURE_FUNCTION_APP_URL', 'AZURE_FUNCTION_APP_URL_PRODUCTION', 'AZURE_FUNCTION_APP_KEY',
        'MAIL_FROM', 'MAIL_FROM_NAME', 'MAIL_REPLY_TO', 'SITE_URL',
        'PO_TEAM_EMAIL', 'PO_TEAM_EMAIL_REPLACE',
        'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_ENCRYPTION',
        'ZENDESK_SUBDOMAIN', 'ZENDESK_EMAIL', 'ZENDESK_API_TOKEN',
        'QBO_CLIENT_ID', 'QBO_CLIENT_SECRET', 'QBO_REDIRECT_URI', 'QBO_ENVIRONMENT',
        'JAZZ_DOMAIN', 'JAZZ_USERNAME', 'JAZZ_PASSWORD', 'JAZZ_TENANT_CODE', 'JAZZ_BASE_URL', 'JAZZ_PAGE_SIZE',
        'JAZZ_DOMAIN_PROD', 'JAZZ_USERNAME_PROD', 'JAZZ_PASSWORD_PROD', 'JAZZ_BASE_URL_PROD',
        'JAZZ_PRODUCTION_DOMAIN', 'JAZZ_PRODUCTION_USERNAME', 'JAZZ_PRODUCTION_PASSWORD', 'JAZZ_PRODUCTION_BASE_URL',
        'JAZZ_UAT_DOMAIN', 'JAZZ_UAT_USERNAME', 'JAZZ_UAT_PASSWORD', 'JAZZ_UAT_BASE_URL',
        'JAZZ_ASN_ENDPOINT', 'JAZZ_ORDER_ENDPOINT',
        'ADOBE_COMMERCE_PRODUCTION_ENVIRONMENT', 'ADOBE_COMMERCE_UAT_ENVIRONMENT',
        'ACCS_PRODUCTION_ENVIRONMENT', 'ACCS_UAT_ENVIRONMENT',
        'ADOBE_ACCS_ENVIRONMENT', 'ACCS_ENVIRONMENT',
        'CRON_SECRET',
        'PROCESS_ALERT_EMAIL',
    ];

    require_once __DIR__ . '/adobe-commerce-settings.php';
    $keys = array_values(array_unique(array_merge($keys, ADOBE_COMMERCE_RUNTIME_ENV_KEYS)));

    return $keys;
}

/**
 * Azure App Service exposes settings as APPSETTING_* server variables.
 *
 * @return array<string, string>
 */
function env_load_azure_app_settings(): array
{
    $vars = [];

    foreach ([$_SERVER, $_ENV] as $source) {
        if (!is_array($source)) {
            continue;
        }

        foreach ($source as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }

            if (str_starts_with($key, 'APPSETTING_')) {
                $vars[substr($key, 11)] = $value;
            }
        }
    }

    foreach (env_known_runtime_keys() as $runtimeKey) {
        $runtimeValue = env_runtime_value($runtimeKey);
        if ($runtimeValue !== null) {
            $vars[$runtimeKey] = $runtimeValue;
        }
    }

    return $vars;
}

function env(string $key, ?string $default = null): ?string
{
    static $vars = null;

    if ($vars === null) {
        if (env_is_azure_hosted()) {
            $fileVars = [];
            $runtimeVars = env_load_azure_app_settings();
        } else {
            $fileVars = env_load(dirname(__DIR__) . '/.env');

            $runtimeVars = [];
            foreach (env_known_runtime_keys() as $runtimeKey) {
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

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto === 'https') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
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
