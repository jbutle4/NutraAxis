<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/sales-rep-territory.php';

sales_rep_territory_require_delete();

$assignmentId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($assignmentId <= 0) {
    header('Location: /sales-rep-territory-assignment/', true, 302);
    exit;
}

$result = sales_rep_territory_delete($assignmentId);
$notice = !empty($result['ok']) ? 'deleted' : 'updated';
header('Location: /sales-rep-territory-assignment/?notice=' . rawurlencode($notice), true, 302);
exit;
