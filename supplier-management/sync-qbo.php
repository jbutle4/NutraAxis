<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/supplier.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

supplier_require_update();
supplier_qbo_bind_production();

$supplierId = (int) ($_POST['supplier_id'] ?? 0);
if ($supplierId <= 0) {
    header('Location: /supplier-management/', true, 302);
    exit;
}

try {
    if (supplier_get($supplierId) === null) {
        header('Location: /supplier-management/', true, 302);
        exit;
    }

    $result = qbo_sync_supplier($supplierId);
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => qbo_sync_format_exception($e)];
}

$notice = $result['ok'] ? (!empty($result['reconciled']) ? 'qbo_reconciled' : 'qbo_synced') : null;
$error = $result['ok'] ? null : ($result['error'] ?? 'QuickBooks sync failed.');
$warning = $result['ok'] ? ($result['warning'] ?? null) : null;

$query = http_build_query(array_filter([
    'id'      => $supplierId,
    'notice'  => $notice,
    'error'   => $error,
    'warning' => $warning,
]));

header('Location: /supplier-management/view.php?' . $query, true, 302);
exit;
