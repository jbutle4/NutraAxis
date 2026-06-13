<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';

po_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-management/', true, 302);
    exit;
}

$poId = (int) ($_POST['po_id'] ?? 0);
if ($poId <= 0) {
    header('Location: /po-management/', true, 302);
    exit;
}

$result = po_save_notes($poId, (string) ($_POST['notes'] ?? ''));
$param = $result['ok']
    ? 'notice=notes_updated'
    : 'notes_error=' . rawurlencode((string) $result['error']);

header('Location: /po-management/view.php?id=' . $poId . '&' . $param, true, 302);
exit;
