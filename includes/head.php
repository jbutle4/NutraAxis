<?php
$pageTitle = $pageTitle ?? 'NutraAxis Operations';
$pageDescription = $pageDescription ?? 'NutraAxis Operations — internal tools and resources for the NutraAxis team.';
require_once __DIR__ . '/assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>" />
  <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
  <link rel="stylesheet" href="/assets/css/operations.css?v=<?= htmlspecialchars(asset_css_version()) ?>" />
</head>
<body<?= !empty($bodyClass) ? ' class="' . htmlspecialchars((string) $bodyClass) . '"' : '' ?>>
