<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'research-harvester',
    'title' => 'Content Harvester',
    'lead'  => 'Configure and run RSS/Atom feeds, targeted site crawls, newsletter ingest, scheduler, and the dedup queue for Track A intelligence.',
    'phase' => 'Research Track A — Phase C',
]);
