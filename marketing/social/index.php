<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-social',
    'title' => 'Social Queue',
    'lead'  => 'Draft and dispatch social posts tied to approved content assets.',
    'phase' => 'S1 Demand engine',
]);
