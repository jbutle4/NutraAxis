<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-qbo.php';

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

$order = po_get_order($poId);
if ($order === null) {
    header('Location: /po-management/', true, 302);
    exit;
}

try {
    $result = qbo_sync_purchase_order($poId);
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => qbo_sync_format_exception($e, 'sync this purchase order to QuickBooks')];
}

$notice = $result['ok'] ? 'qbo_synced' : null;
$error = $result['ok'] ? null : ($result['error'] ?? 'QuickBooks sync failed.');
$warning = $result['ok'] ? ($result['warning'] ?? null) : null;

$query = http_build_query(array_filter([
    'id'      => $poId,
    'notice'  => $notice,
    'error'   => $error,
    'warning' => $warning,
]));

header('Location: /po-management/view.php?' . $query, true, 302);
exit;
