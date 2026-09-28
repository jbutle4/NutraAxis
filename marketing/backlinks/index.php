<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-backlinks',
    'title' => 'Backlinks & Outreach',
    'lead'  => 'Link profile, prospects, and outreach tracking.',
    'phase' => 'Phase 2',
]);
