<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-approval.php';
require dirname(__DIR__) . '/includes/te-approval-token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /travel-expense/approvals.php', true, 302);
    exit;
}

$reportId = (int) ($_POST['report_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$comments = trim($_POST['comments'] ?? '');
$rawToken = trim($_POST['approval_token'] ?? '');
$tokenContext = $rawToken !== '' ? te_approval_token_resolve($rawToken, $reportId) : null;

if ($tokenContext !== null) {
    $result = te_process_approval_action($reportId, $action, $comments, $tokenContext['user']);
    $report = te_get_report($reportId);
    $actionLabel = TE_APPROVAL_ACTIONS[$action]['label'] ?? 'Action';
    $pageTitle = 'Approval Recorded | Travel & Expense';

    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero">';
    if ($result['ok']) {
        echo '<div class="admin-notice is-success" role="status">Your action was recorded successfully.</div>';
        echo '<h1>Thank you</h1>';
        echo '<p class="page-lead">';
        echo htmlspecialchars($actionLabel) . ' was recorded';
        if ($report !== null) {
            echo ' for expense report <strong>' . htmlspecialchars((string) $report['ReportNumber']) . '</strong>';
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

te_require_approval_action();

$result = te_process_approval_action($reportId, $action, $comments);

if ($result['ok']) {
    header('Location: /travel-expense/approvals.php?notice=actioned', true, 302);
    exit;
}

header('Location: /travel-expense/approve.php?id=' . $reportId . '&error=' . rawurlencode($result['error']), true, 302);
exit;
