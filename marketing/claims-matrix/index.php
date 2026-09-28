<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'research-claims',
    'title' => 'Claims Matrix',
    'lead'  => 'Approved claims, evidence tiers, and compliance gates for content release.',
    'phase' => 'Research governance',
]);
