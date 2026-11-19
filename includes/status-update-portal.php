<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

const STATUS_UPDATE_MODULE_SLUG = 'weekly-na-status-update';
const STATUS_UPDATE_PERMISSION_COLUMN = 'StatusUpdate';
const STATUS_UPDATE_TEMPLATE_PERMISSION_COLUMN = 'StatusUpdateTemplate';

function status_update_author_name(): string
{
    $user = auth_user();

    return trim((string) ($user['UserName'] ?? $user['UserLogin'] ?? 'unknown'));
}

function status_update_can_read(): bool
{
    return auth_can_read_module(STATUS_UPDATE_MODULE_SLUG);
}

function status_update_can_contribute(): bool
{
    return auth_can_update(STATUS_UPDATE_PERMISSION_COLUMN);
}

function status_update_can_publish(): bool
{
    return auth_can_create(STATUS_UPDATE_PERMISSION_COLUMN);
}

function status_update_can_manage_template(): bool
{
    $value = auth_permission_value(STATUS_UPDATE_TEMPLATE_PERMISSION_COLUMN);

    return permission_allows($value, 'U') || permission_allows($value, 'C');
}

function status_update_require_read(): void
{
    auth_require_login();
    if (!status_update_can_read()) {
        auth_render_access_denied('You do not have permission to view the Weekly NA Status Update.');
    }
}

function status_update_csrf_token(): string
{
    auth_start_session();
    if (empty($_SESSION['status_update_csrf']) || !is_string($_SESSION['status_update_csrf'])) {
        $_SESSION['status_update_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['status_update_csrf'];
}

function status_update_verify_csrf(?string $token): bool
{
    auth_start_session();
    $expected = $_SESSION['status_update_csrf'] ?? '';

    return is_string($token) && $token !== '' && is_string($expected) && hash_equals($expected, $token);
}

function status_update_parse_date(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($raw))->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

function status_update_default_week_date(): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Chicago'));
    $day = (int) $now->format('w');
    $monday = $day === 1 ? $now : $now->modify('last monday');

    return $monday->format('Y-m-d');
}

function status_update_format_status_date(string $date): string
{
    try {
        return (new DateTimeImmutable($date))->format('n/j/Y');
    } catch (Throwable) {
        return $date;
    }
}

function status_update_eyebrow_label(string $date): string
{
    try {
        $d = new DateTimeImmutable($date);

        return 'STATUS UPDATE  ·  ' . $d->format('m/d/Y');
    } catch (Throwable) {
        return 'STATUS UPDATE  ·  ' . $date;
    }
}