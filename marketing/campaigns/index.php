<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-campaigns',
    'title' => 'Campaign Studio',
    'lead'  => 'Generate single posts, series, and emails from accepted topics, with claims check and medical/editorial approval.',
    'phase' => 'S1b Produce',
]);
