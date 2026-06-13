<?php
require __DIR__ . '/init.php';

$module = get_module($moduleSlug ?? '');
if ($module === null) {
    http_response_code(404);
    $pageTitle = 'Not Found | NutraAxis Operations';
    $pageDescription = 'The requested operations module could not be found.';
    require __DIR__ . '/head.php';
    require __DIR__ . '/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Page not found</h1><p class="page-lead">That operations module does not exist.</p><div class="module-actions"><a class="btn-secondary" href="/">Back to Home</a></div></div></div></main>';
    require __DIR__ . '/footer.php';
    exit;
}

auth_require_module_read($module['slug']);

$activeSlug = $module['slug'];
$pageTitle = $module['title'] . ' | NutraAxis Operations';
$pageDescription = $module['lead'];

require __DIR__ . '/head.php';
require __DIR__ . '/header.php';
require __DIR__ . '/module-landing.php';
require __DIR__ . '/footer.php';
