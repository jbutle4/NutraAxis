<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';

marketing_render_placeholder([
    'slug'  => 'research-prompt-lab',
    'title' => 'Prompt Lab',
    'lead'  => 'Versioned prompts for research extraction, SEO briefs, drafts, and digests.',
    'phase' => 'Research shared chassis',
]);
