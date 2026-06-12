<?php
$pageTitle = $pageTitle ?? 'NutraAxis Operations';
$pageDescription = $pageDescription ?? 'NutraAxis Operations — internal tools and resources for the NutraAxis team.';
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/operations.css?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/css/operations.css') ?>" />
</head>
<body<?= !empty($bodyClass) ? ' class="' . htmlspecialchars((string) $bodyClass) . '"' : '' ?>>
