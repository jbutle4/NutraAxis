#!/usr/bin/env php
<?php
/**
 * Portal navigation / link wiring audit.
 *
 * Fails (exit 1) when hub cards, Operations home sections, auth slugs, or
 * breadcrumbs drift apart — the usual cause of "missing" modules on live.
 *
 * Usage: php scripts/audit-portal-nav.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/includes/data-profile.php';
require_once $root . '/includes/app.php';
require_once $root . '/includes/operations-dashboard.php';
require_once $root . '/includes/auth.php';

$errors = [];
$warnings = [];

/** Slugs with custom auth in auth_can_read_leaf_module() — not in MODULE_PERMISSION_COLUMNS. */
const AUDIT_SPECIAL_AUTH_SLUGS = [
    'procurement-approvals',
    'approvals',
];

/** Folders that are routes/utilities, not permission-gated modules. */
const AUDIT_IGNORE_MODULE_FOLDERS = [
    'login', 'logout', 'my-account', 'site-admin', 'privacy-policy', 'eula',
    'provider-signup', 'operations-dashboard', 'approvals', 'accounting',
    'function-test', 'inventory-demand', 'provider-application', 'reporting', 'training',
];

function audit_fail(array &$errors, string $message): void
{
    $errors[] = $message;
}

function audit_warn(array &$warnings, string $message): void
{
    $warnings[] = $message;
}

function audit_collect_hub_leaf_slugs(): array
{
    $slugs = [];
    foreach (app_hub_slugs() as $hubSlug) {
        foreach (app_hub_submodules($hubSlug) as $child) {
            $slug = (string) ($child['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $slugs[$slug] = ($slugs[$slug] ?? 0) + 1;
        }
    }

    return $slugs;
}

function audit_collect_app_function_slugs(): array
{
    $slugs = [];
    foreach (app_functions() as $module) {
        $slug = (string) ($module['slug'] ?? '');
        if ($slug !== '') {
            $slugs[] = $slug;
        }
    }

    return $slugs;
}

function audit_internal_module_folders(string $root): array
{
    $skip = [
        'includes', 'assets', 'sql', 'docs', 'scripts', 'functions', 'node_modules',
        '.git', '.github', '.vscode', '.cursor', 'Archive Sites', 'nutraaxis_test',
    ];
    $folders = [];
    foreach (scandir($root) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || in_array($entry, $skip, true)) {
            continue;
        }
        $path = $root . '/' . $entry;
        if (is_dir($path) && is_file($path . '/index.php')) {
            $folders[] = $entry;
        }
    }

    return $folders;
}

function audit_scan_php_files(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (str_starts_with($relative, 'node_modules/')
            || str_starts_with($relative, 'vendor/')
            || str_starts_with($relative, 'Archive Sites/')
        ) {
            continue;
        }
        $files[] = $relative;
    }

    return $files;
}

// --- Hub leaves must map to auth and a single hub ---
$permissionSlugs = array_keys(MODULE_PERMISSION_COLUMNS);
$hubLeafCounts = audit_collect_hub_leaf_slugs();

foreach ($hubLeafCounts as $slug => $count) {
    if ($count > 1) {
        audit_warn($warnings, "Hub leaf slug \"{$slug}\" appears in {$count} hub submodule lists (should be exactly one).");
    }
    if (!in_array($slug, $permissionSlugs, true) && !in_array($slug, AUDIT_SPECIAL_AUTH_SLUGS, true)) {
        audit_fail($errors, "Hub leaf \"{$slug}\" is missing from MODULE_PERMISSION_COLUMNS in includes/auth.php.");
    }
    if (app_module_nav_hidden($slug)) {
        continue;
    }
    $hub = app_hub_for_module_slug($slug);
    if ($hub === null) {
        audit_fail($errors, "Hub leaf \"{$slug}\" is not resolvable via app_hub_for_module_slug().");
    }
}

// --- Top-level app cards (non-hub) need auth mapping when they gate a module ---
foreach (audit_collect_app_function_slugs() as $slug) {
    if (in_array($slug, app_hub_slugs(), true)) {
        continue;
    }
    if (!in_array($slug, $permissionSlugs, true)) {
        audit_warn($warnings, "Home/admin card \"{$slug}\" has no MODULE_PERMISSION_COLUMNS entry.");
    }
}

// --- Operations home sections: internal links with module keys ---
foreach (operations_dashboard_sections() as $section) {
    foreach ($section['links'] ?? [] as $link) {
        $module = (string) ($link['module'] ?? '');
        if ($module !== '' && !in_array($module, $permissionSlugs, true)) {
            audit_fail(
                $errors,
                "Operations section \"{$section['title']}\" link \"{$link['title']}\" uses unknown module slug \"{$module}\"."
            );
        }

        $href = (string) ($link['href'] ?? '');
        if (!empty($link['internal']) && preg_match('#^/([a-z0-9-]+)/#', $href, $matches)) {
            $folder = $matches[1];
            if ($folder !== 'operations-dashboard' && !is_dir($root . '/' . $folder)) {
                audit_fail($errors, "Operations link \"{$link['title']}\" points to missing folder /{$folder}/.");
            }
        }
    }
}

// --- Dedicated Operations Dashboard page must stay retired ---
$opsDashIndex = $root . '/operations-dashboard/index.php';
$opsDashIndexSource = is_readable($opsDashIndex) ? (string) file_get_contents($opsDashIndex) : '';
if ($opsDashIndexSource === '') {
    audit_fail($errors, 'operations-dashboard/index.php is missing.');
} elseif (str_contains($opsDashIndexSource, '$dashboardSections')
    || str_contains($opsDashIndexSource, 'dashboardSections')
    || preg_match("/'title'\s*=>\s*'[^']+'\s*,\s*\n\s*'desc'/", $opsDashIndexSource)
) {
    audit_fail(
        $errors,
        'operations-dashboard/index.php must be a redirect stub only — do not duplicate Operations link arrays here. Use includes/operations-dashboard.php.'
    );
} elseif (!str_contains($opsDashIndexSource, "header('Location:") && !str_contains($opsDashIndexSource, 'header("Location:')) {
    audit_fail($errors, 'operations-dashboard/index.php must redirect to / (home Operations cards).');
}

$opsDashShim = $root . '/operations-dashboard.php';
$opsDashShimSource = is_readable($opsDashShim) ? (string) file_get_contents($opsDashShim) : '';
if ($opsDashShimSource === '') {
    audit_fail($errors, 'operations-dashboard.php redirect shim is missing (live bookmarks depend on it).');
} elseif (!preg_match("#Location:\s*/['\"]#", $opsDashShimSource)) {
    audit_fail($errors, 'operations-dashboard.php must redirect to /.');
}

$orphanScript = $root . '/scripts/delete-orphaned-root-files.js';
if (is_readable($orphanScript) && str_contains((string) file_get_contents($orphanScript), "'operations-dashboard.php'")) {
    audit_fail(
        $errors,
        'scripts/delete-orphaned-root-files.js must not delete operations-dashboard.php (legacy bookmark shim).'
    );
}

// --- operations-dashboard app card should point home ---
foreach (app_functions() as $module) {
    if (($module['slug'] ?? '') !== 'operations-dashboard') {
        continue;
    }
    $href = rtrim((string) ($module['href'] ?? ''), '/') ?: '/';
    if ($href !== '/') {
        audit_fail($errors, 'includes/app.php operations-dashboard card href must be "/" (home), not "' . ($module['href'] ?? '') . '".');
    }
}

// --- Stale URL patterns in PHP templates (breadcrumb/href only) ---
$staleHrefChecks = [
    'href="/operations-dashboard.php"' => 'Use href="/" for Operations home.',
    "href='/operations-dashboard.php'" => 'Use href="/" for Operations home.',
    'href="/operations-dashboard/"' => 'Retired dashboard — use href="/" (signup-review submodule excepted).',
    "href='/operations-dashboard/'" => 'Retired dashboard — use href="/" (signup-review submodule excepted).',
    '/operations-dashboard/other-links.php' => 'Use /links-index/ instead.',
];

$allowPathFragments = [
    'scripts/audit-portal-nav.php',
];

foreach (audit_scan_php_files($root) as $relativePath) {
    if (in_array($relativePath, $allowPathFragments, true)) {
        continue;
    }
    if (str_starts_with($relativePath, 'operations-dashboard/signup-review/')) {
        continue;
    }

    $contents = (string) file_get_contents($root . '/' . $relativePath);
    foreach ($staleHrefChecks as $needle => $hint) {
        if (!str_contains($contents, $needle)) {
            continue;
        }
        if ($relativePath === 'operations-dashboard.php') {
            continue;
        }
        audit_fail($errors, "{$relativePath}: stale URL ({$needle}) — {$hint}");
    }
}

// --- Module folders with index.php should be registered somewhere ---
$registeredSlugs = array_unique(array_merge(
    $permissionSlugs,
    array_keys($hubLeafCounts),
    audit_collect_app_function_slugs()
));
$ignoreFolders = AUDIT_IGNORE_MODULE_FOLDERS;
foreach (audit_internal_module_folders($root) as $folder) {
    if (in_array($folder, $ignoreFolders, true)) {
        continue;
    }
    if (!in_array($folder, $registeredSlugs, true)) {
        audit_warn($warnings, "Folder /{$folder}/ exists but slug is not in auth hubs, app cards, or MODULE_PERMISSION_COLUMNS.");
    }
}

// --- Output ---
if ($warnings !== []) {
    fwrite(STDERR, "Warnings:\n");
    foreach ($warnings as $warning) {
        fwrite(STDERR, "  - {$warning}\n");
    }
    fwrite(STDERR, "\n");
}

if ($errors !== []) {
    fwrite(STDERR, "Portal nav audit FAILED (" . count($errors) . "):\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    exit(1);
}

echo "Portal nav audit passed";
if ($warnings !== []) {
    echo ' (' . count($warnings) . ' warning(s))';
}
echo ".\n";
