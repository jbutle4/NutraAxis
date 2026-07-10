<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/subdomain-site.php';

$pageTitle = 'Reporting | NutraAxis';
$pageDescription = 'Reporting resources for NutraAxis operations.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';

subdomain_site_render_landing([
    'title'    => 'Reporting',
    'category' => 'Reporting',
    'lead'     => 'Operational and business reporting for NutraAxis.',
    'hostname' => 'reporting.nutraaxislabs.com',
]);

require dirname(__DIR__) . '/includes/footer.php';
