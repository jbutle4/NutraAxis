<?php

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/hub-cards.php';

function module_hub_render(string $hubSlug, string $activeSlug): void
{
    auth_require_module_read($hubSlug);

    $hub = get_module($hubSlug);
    if ($hub === null) {
        http_response_code(404);
        exit('Module hub not found.');
    }

    $areas = auth_filter_hub_submodules(app_hub_submodules($hubSlug));

    $pageTitle = ($hub['title'] ?? 'Applications') . ' | NutraAxis Operations';
    $pageDescription = (string) ($hub['desc'] ?? '');

    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';

    echo '  <main class="page-main">';
    echo '    <div class="container page-inner">';
    render_list_page_header([
        'back_href'  => '/',
        'back_label' => 'Back to Operations Home',
        'category'   => (string) ($hub['label'] ?? 'Operations'),
        'title'      => (string) ($hub['headline'] ?? $hub['title'] ?? ''),
        'lead'       => (string) ($hub['lead'] ?? $hub['desc'] ?? ''),
    ]);

    if ($areas === []) {
        echo '      <div class="status-banner">';
        echo '        <div>';
        echo '          <strong>No applications assigned</strong>';
        echo '          <p>Your role does not include access to any modules in this area. Contact a site administrator.</p>';
        echo '        </div>';
        echo '      </div>';
    } else {
        hub_render_capability_cards($areas, 'capability-card capability-card-link', 'capability-grid capability-grid--six');
    }

    echo '    </div>';
    echo '  </main>';

    require dirname(__DIR__) . '/includes/footer.php';
}
