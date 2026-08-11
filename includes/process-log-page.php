<?php

function process_log_normalize_profile(?string $profile): string
{
    return strtolower(trim((string) $profile)) === 'uat' ? 'uat' : 'production';
}

function process_log_base_path(?string $profile = null): string
{
    $profile = process_log_normalize_profile($profile ?? (function_exists('data_profile') ? data_profile() : 'production'));

    return $profile === 'uat' ? '/scheduled-jobs-uat/' : '/process-log/';
}

function process_log_redirect(string $profile, string $query = ''): void
{
    $location = process_log_base_path($profile);
    if ($query !== '') {
        $location .= '?' . ltrim($query, '?');
    }

    header('Location: ' . $location);
    exit;
}
