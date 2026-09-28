<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-calendar',
    'title' => 'Publishing Calendar',
    'lead'  => 'Approved social and email assets by scheduled date; record the GoHighLevel post or campaign ID once loaded.',
    'phase' => 'S1b Produce',
]);
