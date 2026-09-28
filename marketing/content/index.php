<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-content',
    'title' => 'Content Pipeline',
    'lead'  => 'Briefs, drafts, medical review, editorial, and publish URL tracking.',
    'phase' => 'S1 Demand engine',
]);
