<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-qbo-sync.php';

po_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-management/', true, 302);
    exit;
}

$poId = (int) ($_POST['po_id'] ?? 0);
$order = $poId > 0 ? po_get_order($poId) : null;

if ($order === null) {
    header('Location: /po-management/', true, 302);
    exit;
}

if ((string) ($order['POStatus'] ?? '') !== PO_STATUS_APPROVED) {
    header('Location: /po-management/view.php?id=' . $poId . '&warning=' . rawurlencode('Only approved purchase orders can sync to QuickBooks.'), true, 302);
    exit;
}

$result = po_qbo_sync_after_approval($poId);

$query = http_build_query(array_filter([
    'id'      => $poId,
    'notice'  => $result['ok'] && empty($result['skipped']) ? 'qbo_synced' : null,
    'warning' => !$result['ok'] ? ($result['error'] ?? 'QuickBooks purchase order sync failed.') : null,
]));

header('Location: /po-management/view.php?' . $query, true, 302);
exit;
