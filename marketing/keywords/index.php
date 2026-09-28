<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-keywords',
    'title' => 'Keyword Universe',
    'lead'  => 'Target keywords, clusters, priority scoring, and page mapping for nutraaxislabs.com.',
    'phase' => 'S1 Demand engine',
]);
