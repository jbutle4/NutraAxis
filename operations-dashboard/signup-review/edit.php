<?php
require dirname(__DIR__, 2) . '/includes/init.php';

$applicationId = (int) ($_GET['id'] ?? 0);
$target = '/operations-dashboard/signup-review/application-form.php?id=' . $applicationId;

header('Location: ' . $target, true, 302);
exit;
