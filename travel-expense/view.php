<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-attachments.php';
require dirname(__DIR__) . '/includes/te-approval.php';

te_require_read();

$reportId = (int) ($_GET['id'] ?? 0);
$report = te_get_report($reportId);

if ($report === null) {
    http_response_code(404);
    $pageTitle = 'Report Not Found';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Expense report not found</h1><div class="module-actions"><a class="btn-secondary" href="/travel-expense/">Back to Expense Reports</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$activeSlug = 'travel-expense';
$activeTeSection = 'list';
$form = te_default_form($report);
$totals = te_calculate_totals($reportId, (float) ($report['MileageRate'] ?? 0.70));
$approvalLog = te_list_approval_log($reportId);
$notice = $_GET['notice'] ?? null;
$mailMessage = isset($_GET['mail_message']) ? (string) $_GET['mail_message'] : null;
$mailWarning = !empty($_GET['mail_warning']);
$canApprove = te_can_read_approval_queue();
$canSubmitForApproval = te_can_submit_for_approval($report) && te_can_edit_report($report);

$pageTitle = $report['ReportNumber'] . ' | Travel & Expense';
$pageDescription = 'View travel and expense report details.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/travel-expense/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Expense Reports
      </a>

      <?php require dirname(__DIR__) . '/includes/te-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">Finance</div>
          <h1><?= htmlspecialchars($report['ReportNumber']) ?></h1>
          <p class="page-lead">
            <span class="status-badge <?= te_status_class($report['ReportStatus']) ?>"><?= htmlspecialchars($report['ReportStatus']) ?></span>
            · <?= htmlspecialchars($report['EmployeeName']) ?>
            · <?= htmlspecialchars(te_period_label($report)) ?>
          </p>
        </div>
        <div class="admin-actions">
          <a class="btn-secondary" href="/travel-expense/print.php?id=<?= $reportId ?>" target="_blank" rel="noopener">Printable View</a>
          <?php if ($canApprove && $report['ReportStatus'] === TE_STATUS_SUBMITTED): ?>
          <a class="btn-primary" href="/travel-expense/approve.php?id=<?= $reportId ?>">Review for Approval</a>
          <?php endif; ?>
          <?php if (te_can_edit_report($report)): ?>
          <a class="btn-secondary" href="/travel-expense/edit.php?id=<?= $reportId ?>">Edit</a>
          <?php if ($canSubmitForApproval): ?>
          <form method="post" action="/travel-expense/status.php" class="inline-form">
            <input type="hidden" name="report_id" value="<?= $reportId ?>" />
            <input type="hidden" name="action" value="submit" />
            <button type="submit" class="btn-primary">Submit for Approval</button>
          </form>
          <?php endif; ?>
          <?php endif; ?>
          <?php if (te_can_update() && $report['ReportStatus'] === TE_STATUS_SUBMITTED): ?>
          <form method="post" action="/travel-expense/status.php" class="inline-form">
            <input type="hidden" name="report_id" value="<?= $reportId ?>" />
            <input type="hidden" name="action" value="resubmit" />
            <button type="submit" class="btn-secondary">Resend Approval Notification</button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($notice === 'created' || $notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Expense report saved successfully.</div>
      <?php elseif ($notice === 'submitted'): ?>
      <div class="admin-notice <?= $mailWarning ? 'is-error is-detail' : 'is-success' ?>" role="status">
        <?= htmlspecialchars($mailMessage ?? 'Expense report submitted for approval. Approvers have been notified.') ?>
      </div>
      <?php elseif ($notice === 'resubmitted'): ?>
      <div class="admin-notice <?= $mailWarning ? 'is-error is-detail' : 'is-success' ?>" role="status">
        <?= htmlspecialchars($mailMessage ?? 'Approval notification resent to approvers.') ?>
      </div>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Receipt uploaded successfully.</div>
      <?php elseif ($notice === 'attachment_deleted'): ?>
      <div class="admin-notice is-success" role="status">Receipt deleted successfully.</div>
      <?php endif; ?>

      <?php if (te_can_update() && $report['ReportStatus'] === TE_STATUS_SUBMITTED): ?>
      <div class="admin-notice" role="status">
        This expense report is in the approval queue. Approvers can review it under <a href="/travel-expense/approvals.php">Approvals</a>.
        Use <strong>Resend Approval Notification</strong> if approvers did not receive the email.
      </div>
      <?php endif; ?>

      <section class="account-card">
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
        $showUploadForm = te_can_add_attachments($report);
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
