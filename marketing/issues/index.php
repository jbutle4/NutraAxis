<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-issues',
    'title' => 'Audit & Issues',
    'lead'  => 'Technical SEO findings, fix specs, and verification workflow.',
    'phase' => 'S4 Harden',
]);
