<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-ranks',
    'title' => 'Rank Tracker',
    'lead'  => 'Keyword positions and competitor SERP movement.',
    'phase' => 'Phase 2',
]);
