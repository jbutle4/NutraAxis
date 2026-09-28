<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-performance',
    'title' => 'Engagement & Performance',
    'lead'  => 'Per-asset clicks, GoHighLevel email stats, responses, and scores fed back into interests and topics.',
    'phase' => 'S2 collect / S3 engagement loop',
]);
