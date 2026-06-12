<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-approval.php';

po_require_approval_action();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-management/approvals.php', true, 302);
    exit;
}

$poId = (int) ($_POST['po_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$comments = trim($_POST['comments'] ?? '');

$result = po_process_approval_action($poId, $action, $comments);

if ($result['ok']) {
    header('Location: /po-management/approvals.php?notice=actioned', true, 302);
    exit;
}

header('Location: /po-management/approve.php?id=' . $poId . '&error=' . rawurlencode($result['error']), true, 302);
exit;
