<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-performance',
    'title' => 'Performance',
    'lead'  => 'Search Console and GA4 signals that steer the next content cycle.',
    'phase' => 'S3 Feedback loop',
]);
