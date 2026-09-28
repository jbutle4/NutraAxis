<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'marketing-pages',
    'title' => 'Page Inventory',
    'lead'  => 'Live URLs, crawl metadata, and keyword-to-page mapping.',
    'phase' => 'S2 Make it findable',
]);
