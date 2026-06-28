<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';

po_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-management/', true, 302);
    exit;
}

$poId = (int) ($_POST['po_id'] ?? 0);
$result = po_delete_order($poId);

if ($result['ok']) {
    header('Location: /po-management/?notice=deleted', true, 302);
    exit;
}

if ($poId > 0) {
    header('Location: /po-management/view.php?id=' . $poId . '&delete_error=' . rawurlencode((string) $result['error']), true, 302);
    exit;
}

header('Location: /po-management/?delete_error=' . rawurlencode((string) $result['error']), true, 302);
exit;
