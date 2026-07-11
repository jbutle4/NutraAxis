<?php

/**
 * Map NutraAxis custom subdomains to application subfolders on nutraaxisweb.
 *
 * All hostnames point at the same Azure App Service; this keeps the friendly
 * subdomain in the browser while serving the correct portal path.
 */
function subdomain_routing_hosts(): array
{
    return [
        'provider-signup.nutraaxislabs.com' => '/provider-signup',
        'providersignup.nutraaxislabs.com'  => '/provider-signup',
        'training.nutraaxislabs.com'        => '/training',
        'reporting.nutraaxislabs.com'       => '/reporting',
    ];
}

function subdomain_routing_normalize_host(?string $host): string
{
    $host = strtolower(trim((string) $host));

    return preg_replace('/:\d+$/', '', $host) ?? $host;
}

function subdomain_routing_normalize_path(?string $requestUri): string
{
    $path = parse_url((string) $requestUri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }

    $path = '/' . ltrim($path, '/');

    return rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
}

function subdomain_routing_target_path(string $basePath, string $path): string
{
    $base = rtrim($basePath, '/');
    if ($base === '') {
        return '/';
    }

    if ($path === '/' || $path === '') {
        return $base . '/';
    }

    $basePrefix = $base . '/';
    if ($path === $base || str_starts_with($path, $basePrefix)) {
        return $path === $base ? $base . '/' : $path;
    }

    return $basePrefix . ltrim($path, '/');
}

function subdomain_routing_apply(): void
{
    $host = subdomain_routing_normalize_host($_SERVER['HTTP_HOST'] ?? '');
    $hosts = subdomain_routing_hosts();

    if (!isset($hosts[$host])) {
        return;
    }

    $basePath = $hosts[$host];
    $path = subdomain_routing_normalize_path($_SERVER['REQUEST_URI'] ?? '/');
    $targetPath = subdomain_routing_target_path($basePath, $path);

    if ($targetPath === $path || $targetPath === $path . '/') {
        return;
    }

    $query = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    $location = $targetPath;
    if (is_string($query) && $query !== '') {
        $location .= '?' . $query;
    }

    header('Location: ' . $location, true, 302);
    exit;
}
