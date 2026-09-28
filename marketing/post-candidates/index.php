<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'research-post-candidates',
    'title' => 'Post Candidates',
    'lead'  => 'Automation that turns harvested literature into potential social and blog post drafts, then hands approved ideas to Content Pipeline or Social Queue.',
    'phase' => 'Research Track A — post generation',
]);
