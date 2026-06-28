<?php

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

    if (auth_is_logged_in() && !accounting_can_read()) {
        return [];
    }

    $children = [];
    foreach (ACCOUNTING_SECTIONS as $section) {
        $children[] = [
            'slug'  => 'accounting',
            'title' => $section['title'],
            'href'  => $section['href'],
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

function nav_children_for_parent(string $parentSlug): array
{
    return match ($parentSlug) {
        'inventory-management' => auth_filter_inventory_submodules(app_inventory_submodules()),
        'sales-reporting'      => auth_filter_sales_submodules(app_sales_submodules()),
        'accounting'           => nav_accounting_children(),
        'labeling-operations'  => nav_labeling_children(),
        default                => [],
    };
}

function nav_parent_is_active(string $parentSlug, ?string $activeSlug): bool
{
    if ($parentSlug === 'inventory-management') {
        return auth_inventory_nav_active($activeSlug);
    }

    if ($parentSlug === 'sales-reporting') {
        return auth_sales_nav_active($activeSlug);
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
