<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-attachments.php';
require dirname(__DIR__) . '/includes/te-approval.php';
require dirname(__DIR__) . '/includes/te-approval-token.php';

$reportId = (int) ($_GET['id'] ?? 0);
$rawToken = trim($_GET['token'] ?? '');
$tokenContext = $rawToken !== '' ? te_approval_token_resolve($rawToken, $reportId) : null;
$prefillAction = trim($_GET['action'] ?? '');
$isTokenAccess = $tokenContext !== null;

if ($rawToken !== '' && $tokenContext === null) {
    http_response_code(403);
    $pageTitle = 'Invalid Approval Link';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Approval link invalid or expired</h1><p class="page-lead">This link may have already been used or has expired. Sign in to review the expense report from the approval queue.</p><div class="module-actions"><a class="btn-secondary" href="/login/">Sign in</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

if (!$isTokenAccess) {
    te_require_approval_read();
}

$report = te_get_report($reportId);

if ($report === null) {
    http_response_code(404);
    $pageTitle = 'Report Not Found';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Expense report not found</h1><div class="module-actions"><a class="btn-secondary" href="/travel-expense/approvals.php">Back to Approvals</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$activeSlug = 'travel-expense';
$activeTeSection = 'approvals';
$form = te_default_form($report);
$totals = te_calculate_totals($reportId, (float) ($report['MileageRate'] ?? 0.70));
$approvalLog = te_list_approval_log($reportId);
$error = $_GET['error'] ?? null;
$notice = $_GET['notice'] ?? null;
$canAct = $report['ReportStatus'] === TE_STATUS_SUBMITTED && (
    ($isTokenAccess && $tokenContext['can_act'])
    || (!$isTokenAccess && te_can_take_approval_action())
);
$approverLabel = $isTokenAccess
    ? (string) ($tokenContext['user']['UserName'] ?? 'Approver')
    : (string) (auth_user()['UserName'] ?? 'Approver');

$pageTitle = 'Review ' . $report['ReportNumber'] . ' | T&E Approval';
$pageDescription = 'Review expense report details and take approval action.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php if (!$isTokenAccess): ?>
      <a class="breadcrumb" href="/travel-expense/approvals.php">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Approval Queue
      </a>
      <?php endif; ?>

      <?php require dirname(__DIR__) . '/includes/te-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">T&amp;E Approval</div>
          <h1><?= htmlspecialchars($report['ReportNumber']) ?></h1>
          <p class="page-lead">
            <span class="status-badge <?= te_status_class($report['ReportStatus']) ?>"><?= htmlspecialchars($report['ReportStatus']) ?></span>
            · <?= htmlspecialchars($report['EmployeeName']) ?>
            · <?= htmlspecialchars(te_format_money($totals['total_due'])) ?>
            <?php if ($isTokenAccess): ?>
            · Acting as <?= htmlspecialchars($approverLabel) ?>
            <?php endif; ?>
          </p>
        </div>
        <a class="btn-secondary" href="/travel-expense/print.php?id=<?= $reportId ?>" target="_blank" rel="noopener">Printable View</a>
      </div>

      <?php if ($notice === 'actioned'): ?>
      <div class="admin-notice is-success" role="status">Your approval action was recorded successfully.</div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($canAct): ?>
      <?php if ($prefillAction !== '' && isset(TE_APPROVAL_ACTIONS[$prefillAction])): ?>
      <div class="admin-notice" role="status">
        You opened the <strong><?= htmlspecialchars(TE_APPROVAL_ACTIONS[$prefillAction]['label']) ?></strong> link from email.
        Confirm your choice below or choose a different action.
      </div>
      <?php endif; ?>
      <div class="account-card approval-actions-card">
        <h2>Approver actions</h2>
        <p class="account-card-lead">Choose an action for this expense report. The employee will be notified by email.</p>
        <form class="admin-form" method="post" action="/travel-expense/approval-action.php">
          <input type="hidden" name="report_id" value="<?= $reportId ?>" />
          <?php if ($isTokenAccess): ?>
          <input type="hidden" name="approval_token" value="<?= htmlspecialchars($rawToken) ?>" />
          <?php endif; ?>
          <div class="form-group">
            <label for="comments">Comments</label>
            <textarea class="form-input" id="comments" name="comments" rows="4" placeholder="Required when sending back with comments."></textarea>
          </div>
          <div class="module-actions approval-action-buttons">
            <?php foreach (TE_APPROVAL_ACTIONS as $key => $action): ?>
            <button
              class="<?= $key === 'approve' ? 'btn-primary' : ($key === 'cancel' ? 'btn-text btn-text-danger' : 'btn-secondary') ?><?= $prefillAction === $key ? ' is-highlighted' : '' ?>"
              type="submit"
              name="action"
              value="<?= htmlspecialchars($key) ?>"
              <?php if ($key === 'reject'): ?>onclick="return confirm('Reject this expense report?');"<?php endif; ?>
              <?php if ($key === 'cancel'): ?>onclick="return confirm('Record that you viewed this report without taking further action?');"<?php endif; ?>
            ><?= htmlspecialchars($action['label']) ?></button>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
      <?php elseif ($report['ReportStatus'] !== TE_STATUS_SUBMITTED): ?>
      <div class="admin-notice" role="status">This expense report is no longer awaiting approval.</div>
      <?php else: ?>
      <div class="admin-notice" role="status">You have read-only access to this approval review.</div>
      <?php endif; ?>

      <section class="account-card" style="margin-top: 20px;">
        <h2>Reimbursement summary</h2>
        <dl class="account-details">
          <div><dt>Expense subtotal</dt><dd><?= htmlspecialchars(te_format_money($totals['expense_total'])) ?></dd></div>
          <div><dt>Mileage</dt><dd><?= htmlspecialchars(number_format($totals['mileage_miles'], 1)) ?> miles · <?= htmlspecialchars(te_format_money($totals['mileage_reimbursement'])) ?></dd></div>
          <div><dt>Entertainment subtotal</dt><dd><?= htmlspecialchars(te_format_money($totals['entertainment_total'])) ?></dd></div>
          <div><dt>Miscellaneous subtotal</dt><dd><?= htmlspecialchars(te_format_money($totals['misc_total'])) ?></dd></div>
          <div><dt>Total reimbursement due</dt><dd><strong><?= htmlspecialchars(te_format_money($totals['total_due'])) ?></strong></dd></div>
        </dl>
      </section>

      <?php
        $readonly = true;
        require dirname(__DIR__) . '/includes/te-form.php';
      ?>

      <?php
        $showUploadForm = false;
        require dirname(__DIR__) . '/includes/te-attachments-section.php';
      ?>

      <?php if ($approvalLog !== []): ?>
      <div class="account-card" style="margin-top: 20px;">
        <h2>Approval history</h2>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Approver</th>
              <th>Result</th>
              <th>Comments</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($approvalLog as $entry): ?>
            <tr>
              <td><?= htmlspecialchars(admin_format_datetime($entry['LogDate'])) ?></td>
              <td><?= htmlspecialchars($entry['ApproverName']) ?></td>
              <td><?= htmlspecialchars($entry['ApproverResult']) ?></td>
              <td><?= htmlspecialchars($entry['ApproverComments'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
