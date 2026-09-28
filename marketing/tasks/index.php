<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-tasks',
    'title' => 'Tasks',
    'lead'  => 'Assign and track editor and coordinator work from the marketing queue.',
    'phase' => 'S1 Demand engine',
]);
