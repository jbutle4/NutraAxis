<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/po-payment.php';
require dirname(__DIR__, 2) . '/includes/payment-approval.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $paymentId = (int) ($_GET['id'] ?? 0);
    $rawToken = trim($_GET['token'] ?? '');
    $action = trim($_GET['action'] ?? '');

    if ($paymentId > 0 && $rawToken !== '') {
        $params = ['id' => $paymentId, 'token' => $rawToken];
        if ($action !== '') {
            $params['action'] = $action;
        }
        header('Location: /accounting/invoice-payments/approve.php?' . http_build_query($params), true, 302);
        exit;
    }

    header('Location: /approvals/?type=Payment&status=pending', true, 302);
    exit;
}

$paymentId = (int) ($_POST['payment_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$comments = trim($_POST['comments'] ?? '');
$rawToken = trim($_POST['approval_token'] ?? '');
$tokenContext = $rawToken !== '' ? payment_approval_token_resolve($rawToken, $paymentId) : null;

if ($tokenContext !== null) {
    $result = payment_approval_process_action($paymentId, $action, $comments, $tokenContext['user']);
    $pageTitle = 'Approval Recorded | Accounting';
    require dirname(__DIR__, 2) . '/includes/head.php';
    require dirname(__DIR__, 2) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero">';
    if ($result['ok']) {
        echo '<div class="admin-notice is-success" role="status">Your action was recorded successfully.</div><h1>Thank you</h1>';
    } else {
        echo '<div class="admin-notice is-error is-detail" role="alert">' . htmlspecialchars((string) $result['error']) . '</div><h1>Unable to complete action</h1>';
    }
    echo '</div></div></main>';
    require dirname(__DIR__, 2) . '/includes/footer.php';
    exit;
}

payment_approval_require_action();
$result = payment_approval_process_action($paymentId, $action, $comments);

if ($result['ok']) {
    header('Location: /approvals/?type=Payment&status=pending&notice=actioned', true, 302);
    exit;
}

header('Location: /accounting/invoice-payments/approve.php?id=' . $paymentId . '&error=' . rawurlencode($result['error']), true, 302);
exit;
