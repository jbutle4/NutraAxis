<?php

require_once __DIR__ . '/data-profile.php';

function hub_render_capability_cards(array $items, string $cardClass = 'capability-card capability-card-link', string $gridClass = 'capability-grid'): void
{
    $sections = hub_cards_partition_uat($items);
    $hasProduction = $sections['production'] !== [];
    $hasUat = $sections['uat'] !== [];

    if (!$hasProduction && !$hasUat) {
        return;
    }

    if ($hasProduction && !$hasUat) {
        hub_render_card_grid($sections['production'], $cardClass, $gridClass);
        return;
    }

    if (!$hasProduction && $hasUat) {
        hub_render_expandable_section(
            'uat',
            'UAT',
            'Test and sandbox systems — Jazz UAT and ACCS Stage.',
            $sections['uat'],
            $cardClass,
            $gridClass,
            false
        );
        return;
    }

    echo '<div class="portal-function-groups hub-env-groups">';
    hub_render_expandable_section(
        'production',
        'Production',
        'Live Jazz OMS and Adobe Commerce systems.',
        $sections['production'],
        $cardClass,
        $gridClass,
        true
    );
    hub_render_expandable_section(
        'uat',
        'UAT',
        'Test and sandbox systems — Jazz UAT and ACCS Stage.',
        $sections['uat'],
        $cardClass,
        $gridClass,
        false
    );
    echo '</div>';
}

/**
 * @param list<array<string, mixed>> $items
 */
function hub_render_expandable_section(
    string $key,
    string $title,
    string $desc,
    array $items,
    string $cardClass,
    string $gridClass,
    bool $openByDefault
): void {
    $panelId = 'hub-env-panel-' . $key;
    echo '<details class="portal-function-group portal-function-group--' . htmlspecialchars($key)
        . ' hub-env-group hub-env-group--' . htmlspecialchars($key) . '"'
        . ($openByDefault ? ' open' : '') . '>';
    echo '<summary class="portal-function-group__summary" aria-controls="' . htmlspecialchars($panelId) . '">';
    echo '<span class="portal-function-group__header">';
    echo '<span class="portal-function-group__title">' . htmlspecialchars($title) . '</span>';
    echo '<span class="portal-function-group__desc">' . htmlspecialchars($desc) . '</span>';
    echo '</span>';
    echo '<span class="portal-function-group__toggle" aria-hidden="true">Expand</span>';
    echo '</summary>';
    echo '<div class="portal-function-group__panel" id="' . htmlspecialchars($panelId) . '">';
    hub_render_card_grid($items, $cardClass, $gridClass);
    echo '</div></details>';
}

function hub_render_function_cards(array $items): void
{
    $sections = hub_cards_partition_uat($items);

    if ($sections['production'] !== []) {
        hub_render_function_card_grid($sections['production']);
    }

    if ($sections['uat'] !== []) {
        echo '<h2 class="hub-uat-section-title">UAT / Test Systems</h2>';
        hub_render_function_card_grid($sections['uat']);
    }
}

function hub_render_card_grid(array $items, string $baseClass, string $gridClass = 'capability-grid'): void
{
    echo '<div class="' . htmlspecialchars($gridClass) . '">';
    foreach ($items as $item) {
        $href = trim((string) ($item['href'] ?? ''));
        $tierClass = hub_card_tier_class($item);
        $isLinked = $href !== '';

        if ($isLinked) {
            echo '<a class="' . htmlspecialchars($baseClass . ' ' . $tierClass) . '" href="' . htmlspecialchars($href) . '">';
        } else {
            echo '<div class="' . htmlspecialchars('capability-card capability-card-static ' . $tierClass) . '">';
        }

        if (!empty($item['icon']) && function_exists('icon_svg')) {
            echo '<div class="function-icon">' . icon_svg((string) $item['icon']) . '</div>';
        }

        echo '<h3>' . htmlspecialchars((string) ($item['title'] ?? '')) . '</h3>';
        echo '<p>' . htmlspecialchars((string) ($item['desc'] ?? '')) . '</p>';

        if ($isLinked) {
            echo '<span class="function-link">Open<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>';
            echo '</a>';
        } else {
            echo '<span class="function-link function-link-muted">Coming soon</span>';
            echo '</div>';
        }
    }
    echo '</div>';
}

function hub_render_function_card_grid(array $items, bool $wrap = true): void
{
    if ($wrap) {
        echo '<div class="functions">';
    }

    hub_render_function_card_items($items);

    if ($wrap) {
        echo '</div>';
    }
}

function hub_render_function_card_items(array $items): void
{
    foreach ($items as $item) {
        $href = (string) ($item['href'] ?? '#');
        $external = !empty($item['external']);
        $tierClass = hub_card_tier_class($item);

        echo '<a class="function-card ' . htmlspecialchars($tierClass) . '" href="' . htmlspecialchars($href) . '"';
        if ($external) {
            echo ' target="_blank" rel="noopener noreferrer"';
        }
        echo '>';

        if (!empty($item['icon']) && function_exists('icon_svg')) {
            echo '<div class="function-icon">' . icon_svg((string) $item['icon']) . '</div>';
        }

        echo '<h3>' . htmlspecialchars((string) ($item['title'] ?? '')) . '</h3>';
        echo '<p>' . htmlspecialchars((string) ($item['desc'] ?? '')) . '</p>';
        echo '<span class="function-link">';
        echo $external ? 'Open in new tab' : (string) ($item['cta'] ?? 'Open');
        echo '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">';
        if ($external) {
            echo '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/>';
        } else {
            echo '<path d="M5 12h14M12 5l7 7-7 7"/>';
        }
        echo '</svg></span></a>';
    }
}
