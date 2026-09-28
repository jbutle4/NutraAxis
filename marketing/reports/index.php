<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-reports',
    'title' => 'Reports',
    'lead'  => 'Weekly digests and monthly SEO exports.',
    'phase' => 'Phase 2',
]);
