<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/subdomain-site.php';

$pageTitle = 'Training | NutraAxis';
$pageDescription = 'Training resources for the NutraAxis team.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';

subdomain_site_render_landing([
    'title'    => 'Training',
    'category' => 'Training',
    'lead'     => 'Training materials and enablement for NutraAxis operations.',
    'hostname' => 'training.nutraaxislabs.com',
]);

require dirname(__DIR__) . '/includes/footer.php';
