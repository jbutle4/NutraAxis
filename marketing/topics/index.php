<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'research-topics',
    'title' => 'Topic Synthesis',
    'lead'  => 'Nightly AI scoring and clustering of harvested items, and the Topic Board where people accept topics and set the angle.',
    'phase' => 'S1b Produce',
]);
