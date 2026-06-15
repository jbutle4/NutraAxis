<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-approval.php';

te_require_approval_read();

$activeSlug = 'travel-expense';
$activeTeSection = 'approvals';
$listFilters = table_sort_state(TE_APPROVAL_LIST_SORT_COLUMNS, 'period', 'asc', $_GET);
$reports = te_list_pending_approvals($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'T&E Approvals | Travel & Expense';
$pageDescription = 'Review expense reports submitted for approval.';

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
          <h1>Approval Queue</h1>
          <p class="page-lead">Expense reports waiting for your review. Open a report to see details, receipts, and approval actions.</p>
          <p class="permission-note">Signed in as <?= htmlspecialchars(auth_user()['UserName'] ?? '') ?> · Approval access: <?= htmlspecialchars(permission_label(te_approval_permission_value())) ?></p>
        </div>
        <?php if (te_can_read()): ?>
        <a class="btn-secondary" href="/travel-expense/?skip_approver_redirect=1">View All Reports</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'actioned'): ?>
      <div class="admin-notice is-success" role="status">Approval action recorded successfully.</div>
      <?php endif; ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                TE_APPROVAL_LIST_SORT_COLUMNS,
                '/travel-expense/approvals.php',
                $listFilters,
                [],
                TE_APPROVAL_LIST_SORT_NUMERIC,
                'period',
                'asc',
                '',
                'View | Review'
            ); ?>
          </thead>
          <tbody>
            <?php if ($reports === []): ?>
            <tr>
              <td colspan="6" class="empty-cell">No expense reports are waiting for approval.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($reports as $report): ?>
            <tr>
              <td><?= htmlspecialchars($report['ReportNumber']) ?></td>
              <td><?= htmlspecialchars($report['EmployeeName']) ?></td>
              <td><?= htmlspecialchars(te_period_label($report)) ?></td>
              <td><?= htmlspecialchars(te_format_money($report['TotalReimbursementDue'])) ?></td>
              <td><?= htmlspecialchars(te_format_date($report['SubmittedAt'] ?? null)) ?></td>
              <?php table_actions_cell([
                  ['href' => '/travel-expense/view.php?id=' . (int) $report['ReportID'], 'label' => 'View'],
                  ['href' => '/travel-expense/approve.php?id=' . (int) $report['ReportID'], 'label' => 'Review'],
              ]); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
