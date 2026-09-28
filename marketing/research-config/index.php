<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'research-config',
    'title' => 'Research Config',
    'lead'  => 'Product lines, SKUs, therapeutic areas, audience, and the shared taxonomy used by SEO and research.',
    'phase' => 'Research Track A/B',
]);
