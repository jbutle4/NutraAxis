<?php
require_once __DIR__ . '/marketing-site.php';

$pageTitle = $pageTitle ?? 'Provider Signup | NutraAxis';
$pageDescription = $pageDescription ?? 'Provider signup for NutraAxis professional-grade supplements.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>" />
  <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="/assets/css/marketing-site.css?v=<?= htmlspecialchars(marketing_site_css_version()) ?>"
  />
</head>
<body class="marketing-site appear">
