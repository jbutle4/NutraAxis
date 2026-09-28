<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'research-interests',
    'title' => 'Interests & Sources',
    'lead'  => 'Watched topics, include/exclude terms, feeds, sites, and search queries that drive the Content Harvester.',
    'phase' => 'S1a Intake',
]);
