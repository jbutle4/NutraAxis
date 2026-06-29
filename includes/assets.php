<?php

require_once __DIR__ . '/env.php';

function asset_css_version(): string
{
    static $version = null;

    if ($version !== null) {
        return $version;
    }

    $configured = trim((string) env('ASSET_VERSION', ''));
    if ($configured !== '') {
        return $version = $configured;
    }

    if (env_is_azure_hosted()) {
        return $version = '20260629e';
    }

    $path = dirname(__DIR__) . '/assets/css/operations.css';

    return $version = is_readable($path) ? (string) filemtime($path) : '1';
}
