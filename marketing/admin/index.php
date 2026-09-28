<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-admin',
    'title' => 'Admin & Jobs',
    'lead'  => 'Job monitor, API usage, and Marketing settings.',
    'phase' => 'S0 Chassis',
]);
