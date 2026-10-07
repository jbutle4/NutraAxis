<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'research-production',
    'title' => 'Original Research',
    'lead'  => 'Produce and track original research assets and study workflows.',
    'phase' => 'Research Track B',
]);
