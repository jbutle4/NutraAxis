<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-approval.php';

po_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-management/', true, 302);
    exit;
}

$poId = (int) ($_POST['po_id'] ?? 0);
$action = trim($_POST['action'] ?? $_POST['new_status'] ?? '');

$result = match ($action) {
    'submit', 'Submitted for Approval' => po_submit_for_approval($poId),
    'resubmit' => po_resubmit_for_approval($poId),
    'accounting', PO_STATUS_ACCOUNTING => po_advance_accounting_status($poId, PO_STATUS_ACCOUNTING),
    'paid', PO_STATUS_PAID => po_advance_accounting_status($poId, PO_STATUS_PAID),
    default => ['ok' => false, 'error' => 'Invalid status action.'],
};

if ($result['ok']) {
    $notice = match ($action) {
        'submit', 'Submitted for Approval' => 'submitted',
        'resubmit' => 'resubmitted',
        'accounting', PO_STATUS_ACCOUNTING => 'accounting',
        'paid', PO_STATUS_PAID => 'paid',
        default => 'updated',
    };

    $params = ['id' => $poId, 'notice' => $notice];
    if (!empty($result['notify']) && is_array($result['notify'])) {
        $mailMessage = po_format_approval_notify_message($result['notify']);
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

    $query = http_build_query($params);
    header('Location: /po-management/view.php?' . $query, true, 302);
    exit;
}

$activeSlug = 'po-management';
$pageTitle = 'Update PO Status | PO Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/po-management/view.php?id=<?= $poId ?>">Back to PO</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
