<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/supplier.php';

supplier_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /supplier-management/', true, 302);
    exit;
}

$supplierId = (int) ($_POST['supplier_id'] ?? 0);
$active = (string) ($_POST['is_active'] ?? '0') === '1';
$result = supplier_set_active($supplierId, $active);

if ($result['ok']) {
    $notice = $active ? 'activated' : 'deactivated';
    header('Location: /supplier-management/?notice=' . $notice, true, 302);
    exit;
}

header('Location: /supplier-management/view.php?id=' . $supplierId . '&error=' . rawurlencode((string) $result['error']), true, 302);
exit;
