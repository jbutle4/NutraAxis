<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'research-output',
    'title' => 'Output Generator',
    'lead'  => 'Export Word/PDF research summaries, SEO articles, and monthly reports.',
    'phase' => 'Research shared chassis',
]);
