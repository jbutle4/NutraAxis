<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/subdomain-site.php';

$pageTitle = 'Provider Signup | NutraAxis';
$pageDescription = 'Provider signup resources for NutraAxis.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';

subdomain_site_render_landing([
    'title'    => 'Provider Signup',
    'category' => 'Provider Signup',
    'lead'     => 'Provider onboarding and signup workflows.',
    'hostname' => 'provider-signup.nutraaxislabs.com',
]);

require dirname(__DIR__) . '/includes/footer.php';
