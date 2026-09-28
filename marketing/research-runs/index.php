<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'research-runs',
    'title' => 'Research Runs',
    'lead'  => 'AI extraction jobs with dual-provider runner, run history, and reconciliation.',
    'phase' => 'Research Track A',
]);
