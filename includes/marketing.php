<?php

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/audit.php';

const MARKETING_PERMISSION_COLUMN = 'Marketing';

function marketing_can_create(): bool
{
    return auth_can_create(MARKETING_PERMISSION_COLUMN);
}

function marketing_can_update(): bool
{
    return auth_can_update(MARKETING_PERMISSION_COLUMN);
}

function marketing_can_delete(): bool
{
    return auth_can_delete(MARKETING_PERMISSION_COLUMN);
}

/**
 * Settings, API cost, and job controls need full CRUD — editors and coordinators
 * (offshore) must not see keys, spend, or budgets.
 */
function marketing_can_admin(): bool
{
    return marketing_can_create() && marketing_can_update() && marketing_can_delete();
}

function marketing_require_admin(): void
{
    if (!marketing_can_admin()) {
        auth_render_access_denied('Marketing & Research admin requires full Create/Read/Update/Delete access.');
    }
}

function marketing_require_update(): void
{
    if (!marketing_can_update()) {
        auth_render_access_denied('You do not have permission to change Marketing & Research records.');
    }
}

function marketing_require_create(): void
{
    if (!marketing_can_create()) {
        auth_render_access_denied('You do not have permission to create Marketing & Research records.');
    }
}

function marketing_user_id(): ?int
{
    $id = (int) (auth_user()['UserID'] ?? 0);

    return $id > 0 ? $id : null;
}

function marketing_settings_all(): array
{
    $rows = db()->query('SELECT SettingKey, SettingValue, Description, UpdatedAt FROM dbo.MktSetting ORDER BY SettingKey')->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[(string) $row['SettingKey']] = $row;
    }

    return $settings;
}

function marketing_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (marketing_settings_all() as $settingKey => $row) {
            $cache[$settingKey] = $row['SettingValue'];
        }
    }

    $value = $cache[$key] ?? null;

    return $value === null || $value === '' ? $default : (string) $value;
}

function marketing_setting_lines(string $key): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) marketing_setting($key, '')))));
}

/**
 * @param array<string, string> $values
 */
function marketing_settings_save(array $values): int
{
    $existing = marketing_settings_all();
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE dbo.MktSetting SET SettingValue = :value, UpdatedAt = SYSUTCDATETIME(), UpdatedBy = :user WHERE SettingKey = :key');
    $changed = 0;

    foreach ($values as $key => $value) {
        if (!isset($existing[$key])) {
            continue;
        }
        $value = str_replace("\r\n", "\n", trim((string) $value));
        $old = (string) ($existing[$key]['SettingValue'] ?? '');
        if ($value === $old) {
            continue;
        }
        $stmt->execute(['value' => $value, 'user' => marketing_user_id(), 'key' => $key]);
        $built = audit_build_update('MktSetting', 'SettingKey', $key, ['SettingValue' => $value], ['SettingValue' => $old]);
        audit_log_change($built['change'], $built['reverse']);
        $changed++;
    }

    return $changed;
}

function marketing_format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('America/Chicago'))
            ->format('M j, Y g:i A');
    } catch (Throwable) {
        return $value;
    }
}

function marketing_format_usd(float $value, int $decimals = 2): string
{
    return '$' . number_format($value, $decimals);
}

/**
 * @param array<string, string> $tabs key => label
 */
function marketing_render_tabs(string $baseHref, array $tabs, string $active): void
{
    $html = '';
    foreach ($tabs as $key => $label) {
        $class = $key === $active ? 'btn-primary' : 'btn-secondary';
        $href = $baseHref . '?tab=' . rawurlencode($key);
        $html .= '<a class="' . $class . '" href="' . htmlspecialchars($href) . '">' . htmlspecialchars($label) . '</a> ';
    }
    render_list_page_toolbar($html);
}

function marketing_render_notice(?string $success, ?string $error = null): void
{
    if ($success !== null && $success !== '') {
        echo '<div class="admin-notice is-success" role="status">' . htmlspecialchars($success) . '</div>';
    }
    if ($error !== null && $error !== '') {
        echo '<div class="admin-notice is-error is-detail" role="alert">' . htmlspecialchars($error) . '</div>';
    }
}

function marketing_redirect(string $path, array $query = []): never
{
    $url = $path . ($query !== [] ? (str_contains($path, '?') ? '&' : '?') . http_build_query($query) : '');
    header('Location: ' . $url, true, 302);
    exit;
}

/**
 * Placeholder leaf page for Marketing hub modules not yet built.
 *
 * @param array{slug: string, title: string, lead: string, phase: string} $config
 */
function marketing_render_placeholder(array $config): void
{
    $slug = (string) ($config['slug'] ?? '');
    $title = (string) ($config['title'] ?? 'Marketing');
    $lead = (string) ($config['lead'] ?? '');
    $phase = (string) ($config['phase'] ?? 'Coming soon');

    auth_require_module_read($slug);

    $activeSlug = $slug;
    $pageTitle = $title . ' | NutraAxis Operations';
    $pageDescription = $lead !== '' ? $lead : $title;

    $back = app_module_hub_back_link($slug);

    require __DIR__ . '/head.php';
    require __DIR__ . '/header.php';
    ?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => $title,
          'lead'       => $lead,
          'permission' => auth_module_permission_label($slug),
      ]); ?>

      <div class="status-banner">
        <div>
          <strong>Placeholder — <?= htmlspecialchars($phase) ?></strong>
          <p>This module is registered in the Marketing &amp; Research Hub for roadmap visibility. Functionality will land in the SEO Operations and Research Application build phases (see docs/SEO_OPS_BUILD_SPEC.md).</p>
        </div>
      </div>
    </div>
  </main>
    <?php
    require __DIR__ . '/footer.php';
}
