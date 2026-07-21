<?php

require_once __DIR__ . '/data-profile.php';
require_once __DIR__ . '/app.php';

function nav_href_is_active(string $href): bool
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = rtrim((string) $path, '/') ?: '/';
    $target = parse_url($href, PHP_URL_PATH);
    $target = rtrim((string) $target, '/') ?: '/';

    if ($path === $target) {
        return true;
    }

    if (str_ends_with($target, '.php')) {
        return false;
    }

    return str_starts_with($path, $target . '/');
}

function nav_accounting_children(): array
{
    require_once __DIR__ . '/accounting.php';

    $children = [];
    foreach (app_accounting_submodules() as $item) {
        if (auth_is_logged_in() && !auth_can_read_leaf_module((string) ($item['slug'] ?? ''))) {
            continue;
        }

        $children[] = [
            'slug'  => (string) ($item['slug'] ?? 'accounting'),
            'title' => (string) ($item['title'] ?? ''),
            'href'  => (string) ($item['href'] ?? '#'),
        ];
    }

    return $children;
}

function nav_labeling_children(): array
{
    require_once __DIR__ . '/labeling.php';

    if (auth_is_logged_in() && !label_can_read()) {
        return [];
    }

    $children = [
        [
            'slug'  => 'labeling-operations',
            'title' => 'Overview',
            'href'  => '/labeling-operations/',
        ],
    ];

    foreach (label_hub_areas() as $area) {
        $children[] = [
            'slug'  => 'labeling-operations',
            'title' => $area['title'],
            'href'  => $area['href'],
        ];
    }

    return $children;
}

function nav_operations_dashboard_children(): array
{
    require_once __DIR__ . '/operations-dashboard.php';

    if (auth_is_logged_in() && !auth_can_read_module('operations-dashboard')) {
        return [];
    }

    return operations_dashboard_nav_sections();
}

function nav_child_has_nested_children(array $child): bool
{
    return isset($child['children']) && is_array($child['children']) && $child['children'] !== [];
}

function nav_nested_group_is_active(array $group): bool
{
    foreach ($group['children'] ?? [] as $child) {
        if (nav_child_has_nested_children($child)) {
            if (nav_nested_group_is_active($child)) {
                return true;
            }
            continue;
        }

        if (nav_href_is_active((string) ($child['href'] ?? ''))) {
            return true;
        }
    }

    return false;
}

function nav_operations_dashboard_is_active(?string $activeSlug): bool
{
    if (($activeSlug ?? '') === 'operations-dashboard' && nav_href_is_active('/operations-dashboard/')) {
        return true;
    }

    foreach (nav_operations_dashboard_children() as $section) {
        if (nav_nested_group_is_active($section)) {
            return true;
        }
    }

    return ($activeSlug ?? '') === 'operations-dashboard';
}

function nav_render_submenu_items(array $items, string $parentSlug, ?string $activeSlug, int $depth = 1): void
{
    foreach ($items as $index => $child) {
        if (nav_child_has_nested_children($child)) {
            $sectionSlug = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) ($child['title'] ?? 'section')));
            $groupId = 'nav-group-' . preg_replace('/[^a-z0-9-]+/', '-', strtolower($parentSlug))
                . '-' . $sectionSlug . '-' . $index;
            $sectionActive = nav_nested_group_is_active($child);
            $isExpanded = $sectionActive;

            echo '<li class="nav-group has-children nav-group-nested';
            if ($parentSlug === 'operations-dashboard' && $depth === 1) {
                echo ' nav-dashboard-section';
            }
            if ($isExpanded) {
                echo ' is-expanded';
            }
            if ($sectionActive) {
                echo ' is-active';
            }
            echo '">';

            echo '<div class="nav-parent-row">';
            echo '<span class="nav-parent-link nav-section-heading">' . htmlspecialchars((string) ($child['title'] ?? '')) . '</span>';
            echo '<button type="button" class="nav-parent-toggle" aria-expanded="' . ($isExpanded ? 'true' : 'false') . '" aria-controls="' . htmlspecialchars($groupId) . '" aria-label="' . htmlspecialchars(($isExpanded ? 'Collapse ' : 'Expand ') . (string) ($child['title'] ?? 'section')) . '">';
            echo '<svg class="nav-parent-chevron" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
            echo '</button>';
            echo '</div>';

            echo '<ul class="nav-sublist nav-sublist-nested" id="' . htmlspecialchars($groupId) . '">';
            nav_render_submenu_items($child['children'], $parentSlug, $activeSlug, $depth + 1);
            echo '</ul>';
            echo '</li>';
            continue;
        }

        $childHref = auth_is_logged_in()
            ? (string) ($child['href'] ?? '#')
            : auth_login_url((string) ($child['href'] ?? '/'));
        $childActive = nav_child_is_active($child);
        $external = !empty($child['external']);

        echo '<li>';
        echo '<a href="' . htmlspecialchars($childHref) . '" class="' . ($childActive ? 'is-active' : '') . '"';
        if ($external) {
            echo ' target="_blank" rel="noopener noreferrer"';
        }
        echo '>' . htmlspecialchars((string) ($child['title'] ?? ''));
        if ($external) {
            echo '<span class="nav-external-indicator" aria-hidden="true">↗</span>';
        }
        echo '</a>';
        echo '</li>';
    }
}

function nav_children_for_parent(string $parentSlug): array
{
    if (function_exists('app_hub_slugs') && in_array($parentSlug, app_hub_slugs(), true)) {
        return auth_filter_hub_submodules(app_hub_submodules($parentSlug));
    }

    return match ($parentSlug) {
        'accounting'             => nav_accounting_children(),
        'labeling-operations'    => nav_labeling_children(),
        'operations-dashboard'   => nav_operations_dashboard_children(),
        default                  => [],
    };
}

function nav_parent_is_active(string $parentSlug, ?string $activeSlug): bool
{
    if (function_exists('app_hub_slugs') && in_array($parentSlug, app_hub_slugs(), true)) {
        return auth_hub_nav_active($parentSlug, $activeSlug);
    }

    if ($parentSlug === 'accounting' || $parentSlug === 'labeling-operations') {
        if (($activeSlug ?? '') !== $parentSlug) {
            return false;
        }

        foreach (nav_children_for_parent($parentSlug) as $child) {
            if (nav_href_is_active((string) ($child['href'] ?? ''))) {
                return true;
            }
        }

        return nav_href_is_active(
            $parentSlug === 'accounting' ? '/accounting/' : '/labeling-operations/'
        );
    }

    if ($parentSlug === 'operations-dashboard') {
        return nav_operations_dashboard_is_active($activeSlug);
    }

    return ($activeSlug ?? '') === $parentSlug;
}

function nav_child_is_active(array $child): bool
{
    return nav_href_is_active((string) ($child['href'] ?? ''));
}

function nav_group_is_expanded(string $parentSlug, ?string $activeSlug): bool
{
    return nav_parent_is_active($parentSlug, $activeSlug);
}
