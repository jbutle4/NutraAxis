<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';
require dirname(__DIR__, 2) . '/includes/qbo-insert-approval.php';
require dirname(__DIR__, 2) . '/includes/payment-approval.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $invoiceId = (int) ($_GET['id'] ?? 0);
    $rawToken = trim($_GET['token'] ?? '');
    $action = trim($_GET['action'] ?? '');

    if ($invoiceId > 0 && $rawToken !== '') {
        $params = ['id' => $invoiceId, 'token' => $rawToken];
        if ($action !== '') {
            $params['action'] = $action;
        }
        header('Location: /accounting/supplier-invoices/approve.php?' . http_build_query($params), true, 302);
        exit;
    }

    header('Location: /approvals/?type=Payment&status=pending', true, 302);
    exit;
}

$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$comments = trim($_POST['comments'] ?? '');
$rawToken = trim($_POST['approval_token'] ?? '');
$invoice = $invoiceId > 0 ? supplier_invoice_get($invoiceId) : null;

$tokenContext = null;
$tokenKind = null;
if ($rawToken !== '') {
    $tokenContext = payment_approval_invoice_token_resolve($rawToken, $invoiceId);
    if ($tokenContext !== null) {
        $tokenKind = 'Payment';
    } else {
        $tokenContext = qbo_insert_approval_token_resolve($rawToken, $invoiceId);
        if ($tokenContext !== null) {
            $tokenKind = 'QBOInsert';
        }
    }
}

$isQboRecovery = $tokenKind === 'QBOInsert'
    || ($tokenKind === null && $invoice !== null && qbo_insert_is_recovery_pending($invoice));

if ($tokenContext !== null) {
    $result = $isQboRecovery
        ? qbo_insert_process_approval_action($invoiceId, $action, $comments, $tokenContext['user'])
        : payment_approval_invoice_process_action($invoiceId, $action, $comments, $tokenContext['user']);
    $invoice = supplier_invoice_get($invoiceId);
    $pageTitle = 'Approval Recorded | Accounting';
    require dirname(__DIR__, 2) . '/includes/head.php';
    require dirname(__DIR__, 2) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero">';
    if ($result['ok']) {
        echo '<div class="admin-notice is-success" role="status">Your action was recorded successfully.</div>';
        echo '<h1>Thank you</h1><p class="page-lead">Action recorded';
        if ($invoice !== null) {
            echo ' for invoice <strong>' . htmlspecialchars(supplier_invoice_reference($invoice)) . '</strong>';
        }
        echo '.</p>';
    } else {
        echo '<div class="admin-notice is-error is-detail" role="alert">' . htmlspecialchars((string) $result['error']) . '</div>';
        echo '<h1>Unable to complete action</h1>';
    }
    echo '</div></div></main>';
    require dirname(__DIR__, 2) . '/includes/footer.php';
    exit;
}

if ($isQboRecovery) {
    qbo_insert_require_action();
    $result = qbo_insert_process_approval_action($invoiceId, $action, $comments);
    $redirectQueue = '/approvals/?type=QBOInsert&status=pending&notice=actioned';
} else {
    payment_approval_require_action();
    $result = payment_approval_invoice_process_action($invoiceId, $action, $comments);
    $redirectQueue = '/approvals/?type=Payment&status=pending&notice=actioned';
}

if ($result['ok']) {
    header('Location: ' . $redirectQueue, true, 302);
    exit;
}

header('Location: /accounting/supplier-invoices/approve.php?id=' . $invoiceId . '&error=' . rawurlencode($result['error']), true, 302);
exit;
