<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-approval.php';

te_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /travel-expense/', true, 302);
    exit;
}

$reportId = (int) ($_POST['report_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

$result = match ($action) {
    'submit' => te_submit_for_approval($reportId),
    'resubmit' => te_resubmit_for_approval($reportId),
    default => ['ok' => false, 'error' => 'Invalid status action.'],
};

if ($result['ok']) {
    $notice = $action === 'resubmit' ? 'resubmitted' : 'submitted';
    $params = ['id' => $reportId, 'notice' => $notice];

    if (!empty($result['notify']) && is_array($result['notify'])) {
        $mailMessage = te_format_approval_notify_message($result['notify']);
        $sent = $result['notify']['sent'] ?? [];
        $failed = $result['notify']['failed'] ?? [];
        $skipped = $result['notify']['skipped_reason'] ?? null;

        if ($mailMessage !== '') {
            $params['mail_message'] = $mailMessage;
        }
        if ($skipped !== null || $failed !== [] || $sent === []) {
            $params['mail_warning'] = '1';
        }
    }

    header('Location: /travel-expense/view.php?' . http_build_query($params), true, 302);
    exit;
}

$activeSlug = 'travel-expense';
$pageTitle = 'Update Report Status | Travel & Expense';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/travel-expense/view.php?id=<?= $reportId ?>">Back to Report</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
