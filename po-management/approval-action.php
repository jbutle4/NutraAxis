<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-approval.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-management/approvals.php', true, 302);
    exit;
}

$poId = (int) ($_POST['po_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$comments = trim($_POST['comments'] ?? '');
$rawToken = trim($_POST['approval_token'] ?? '');
$tokenContext = $rawToken !== '' ? po_approval_token_resolve($rawToken, $poId) : null;

if ($tokenContext !== null) {
    $result = po_process_approval_action($poId, $action, $comments, $tokenContext['user']);
    $order = po_get_order($poId);
    $actionLabel = PO_APPROVAL_ACTIONS[$action]['label'] ?? 'Action';
    $pageTitle = 'Approval Recorded | PO Management';

    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero">';
    if ($result['ok']) {
        echo '<div class="admin-notice is-success" role="status">Your action was recorded successfully.</div>';
        echo '<h1>Thank you</h1>';
        echo '<p class="page-lead">';
        echo htmlspecialchars($actionLabel) . ' was recorded';
        if ($order !== null) {
            echo ' for purchase order <strong>' . htmlspecialchars((string) $order['PONumber']) . '</strong>';
            if (!empty($result['status'])) {
                echo ' (now <strong>' . htmlspecialchars((string) $result['status']) . '</strong>)';
            }
        }
        echo '.</p>';
    } else {
        echo '<div class="admin-notice is-error is-detail" role="alert">' . htmlspecialchars((string) $result['error']) . '</div>';
        echo '<h1>Unable to complete action</h1>';
        echo '<p class="page-lead">This approval link may no longer be valid.</p>';
    }
    echo '</div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

po_require_approval_action();

$result = po_process_approval_action($poId, $action, $comments);

if ($result['ok']) {
    header('Location: /po-management/approvals.php?notice=actioned', true, 302);
    exit;
}

header('Location: /po-management/approve.php?id=' . $poId . '&error=' . rawurlencode($result['error']), true, 302);
exit;
