<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/nav.php';
require_once __DIR__ . '/links.php';

function portal_search_result(string $title, string $description, string $href, string $type, bool $external = false): array
{
    return [
        'title'       => $title,
        'description' => $description,
        'href'        => $href,
        'type'        => $type,
        'external'    => $external,
    ];
}

function portal_search_text_matches(array $result, string $query): bool
{
    $haystack = strtolower(implode(' ', [
        $result['title'] ?? '',
        $result['description'] ?? '',
        $result['href'] ?? '',
        $result['type'] ?? '',
    ]));

    foreach (preg_split('/\s+/', strtolower($query)) ?: [] as $term) {
        if ($term === '') {
            continue;
        }

        if (!str_contains($haystack, $term)) {
            return false;
        }
    }

    return true;
}

function portal_search_rank(array $result, string $query): int
{
    $query = strtolower(trim($query));
    $title = strtolower((string) ($result['title'] ?? ''));
    $description = strtolower((string) ($result['description'] ?? ''));

    if ($title === $query) {
        return 0;
    }

    if (str_starts_with($title, $query)) {
        return 1;
    }

    if (str_contains($title, $query)) {
        return 2;
    }

    if (str_contains($description, $query)) {
        return 3;
    }

    return 4;
}

function portal_search_add_result(array &$results, array &$seen, array $result): void
{
    $title = trim((string) ($result['title'] ?? ''));
    $href = trim((string) ($result['href'] ?? ''));
    if ($title === '' || $href === '') {
        return;
    }

    $key = strtolower($title . '|' . $href);
    if (isset($seen[$key])) {
        return;
    }

    $seen[$key] = true;
    $results[] = $result;
}

function portal_search_flatten_nav_children(array $children, string $type, array &$items): void
{
    foreach ($children as $child) {
        if (isset($child['children']) && is_array($child['children'])) {
            portal_search_flatten_nav_children($child['children'], (string) ($child['title'] ?? $type), $items);
            continue;
        }

        $href = trim((string) ($child['href'] ?? ''));
        if ($href === '') {
            continue;
        }

        $items[] = portal_search_result(
            (string) ($child['title'] ?? 'Link'),
            $type,
            $href,
            $type,
            !empty($child['external'])
        );
    }
}

function portal_search_static_items(): array
{
    $items = [];

    foreach (auth_filter_modules(app_functions()) as $module) {
        $slug = (string) ($module['slug'] ?? '');
        $href = (string) ($module['href'] ?? '');
        if ($slug === '' || $href === '') {
            continue;
        }

        $items[] = portal_search_result(
            (string) ($module['title'] ?? 'Application'),
            (string) ($module['desc'] ?? ''),
            $href,
            'Application'
        );

        portal_search_flatten_nav_children(nav_children_for_parent($slug), (string) ($module['title'] ?? 'Application'), $items);
    }

    return $items;
}

function portal_search_links_index_results(string $query): array
{
    if (!links_can_read()) {
        return [];
    }

    try {
        $rows = links_list([
            'status' => 'active',
        ]);
    } catch (Throwable $e) {
        error_log('portal_search links index failed: ' . $e->getMessage());

        return [];
    }

    $results = [];
    foreach ($rows as $row) {
        $href = links_external_url((string) ($row['LinkURL'] ?? ''));
        if ($href === '') {
            continue;
        }

        $result = portal_search_result(
            (string) ($row['LinkName'] ?? 'Link'),
            trim((string) (($row['LinkCategory'] ?? '') . ' ' . ($row['LinkDescription'] ?? ''))),
            $href,
            'Other Link',
            !str_starts_with($href, '/')
        );

        if (portal_search_text_matches($result, $query)) {
            $results[] = $result;
        }
    }

    return $results;
}

function portal_search(string $query, int $limit = 20): array
{
    $query = trim($query);
    if (strlen($query) < 2) {
        return [];
    }

    $matches = [];
    $seen = [];

    foreach (portal_search_static_items() as $item) {
        if (portal_search_text_matches($item, $query)) {
            portal_search_add_result($matches, $seen, $item);
        }
    }

    foreach (portal_search_links_index_results($query) as $item) {
        portal_search_add_result($matches, $seen, $item);
    }

    usort($matches, function (array $a, array $b) use ($query): int {
        $rank = portal_search_rank($a, $query) <=> portal_search_rank($b, $query);
        if ($rank !== 0) {
            return $rank;
        }

        return strcasecmp((string) $a['title'], (string) $b['title']);
    });

    return array_slice($matches, 0, $limit);
}
