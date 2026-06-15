<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-approval.php';

te_require_read();

if (te_can_read_approval_queue() && !te_can_create() && !isset($_GET['skip_approver_redirect'])) {
    header('Location: /travel-expense/approvals.php', true, 302);
    exit;
}

$activeSlug = 'travel-expense';
$activeTeSection = 'list';
$canCreate = te_can_create();
$canUpdate = te_can_update();
$canDelete = te_can_delete();
$canApprove = te_can_read_approval_queue();
$pendingApprovalCount = $canApprove ? te_count_pending_approvals() : 0;
$statusFilter = $_GET['status'] ?? '';
$listFilters = [
    'status' => $statusFilter !== '' ? $statusFilter : null,
] + table_sort_state(TE_LIST_SORT_COLUMNS, 'submitted', 'desc', $_GET);
$reports = te_list_reports($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Travel & Expense | NutraAxis Operations';
$pageDescription = 'Create, submit, and track employee travel and expense reimbursement reports.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <?php require dirname(__DIR__) . '/includes/te-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">Finance</div>
          <h1>Travel &amp; Expense Reports</h1>
          <p class="page-lead">Submit expense reports for reimbursement, attach receipt PDFs, and route through approval.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(te_permission_value())) ?></p>
        </div>
        <?php if ($canCreate): ?>
        <a class="btn-primary" href="/travel-expense/new.php">New Expense Report</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Expense report created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Expense report updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Expense report deleted successfully.</div>
      <?php elseif ($notice === 'submitted'): ?>
      <div class="admin-notice is-success" role="status">Expense report submitted for approval.</div>
      <?php endif; ?>

      <?php if ($canApprove && $pendingApprovalCount > 0): ?>
      <div class="status-banner status-banner-approval">
        <div>
          <strong><?= $pendingApprovalCount === 1 ? '1 expense report is' : $pendingApprovalCount . ' expense reports are' ?> waiting for approval</strong>
          <p>Review submitted reports and take approval action from the approval queue.</p>
        </div>
        <a class="btn-primary" href="/travel-expense/approvals.php">Open Approval Queue</a>
      </div>
      <?php endif; ?>

      <form class="po-filter" method="get" action="/travel-expense/">
        <?php table_sort_hidden_inputs($listFilters, 'submitted', 'desc'); ?>
        <label for="status">Filter by status</label>
        <select class="form-input" id="status" name="status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <?php foreach (TE_STATUSES as $status): ?>
          <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php
            $teActionHeader = 'View | Edit';
            if ($canApprove) {
                $teActionHeader = 'View | Review | Edit';
            }
            if ($canDelete) {
                $teActionHeader .= ' | Delete';
            }
            table_sort_render_head_row(
                TE_LIST_SORT_COLUMNS,
                '/travel-expense',
                $listFilters,
                ['status'],
                TE_LIST_SORT_NUMERIC,
                'submitted',
                'desc',
                'submitted',
                $teActionHeader
            );
            ?>
          </thead>
          <tbody>
            <?php if ($reports === []): ?>
            <tr>
              <td colspan="7" class="empty-cell">No expense reports found.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($reports as $report): ?>
            <tr>
              <td><a class="btn-text" href="/travel-expense/view.php?id=<?= (int) $report['ReportID'] ?>"><?= htmlspecialchars($report['ReportNumber']) ?></a></td>
              <td><?= htmlspecialchars($report['EmployeeName']) ?></td>
              <td><span class="status-badge <?= te_status_class($report['ReportStatus']) ?>"><?= htmlspecialchars($report['ReportStatus']) ?></span></td>
              <td><?= htmlspecialchars(te_period_label($report)) ?></td>
              <td><?= htmlspecialchars(te_format_money($report['TotalReimbursementDue'])) ?></td>
              <td><?= htmlspecialchars(te_format_date($report['SubmittedAt'] ?? null)) ?></td>
              <?php
              $teActions = [
                  ['href' => '/travel-expense/view.php?id=' . (int) $report['ReportID'], 'label' => 'View'],
              ];
              if ($canApprove && $report['ReportStatus'] === TE_STATUS_SUBMITTED) {
                  $teActions[] = ['href' => '/travel-expense/approve.php?id=' . (int) $report['ReportID'], 'label' => 'Review'];
              }
              $fullReport = te_get_report((int) $report['ReportID']);
              if ($fullReport !== null && te_can_edit_report($fullReport)) {
                  $teActions[] = ['href' => '/travel-expense/edit.php?id=' . (int) $report['ReportID'], 'label' => 'Edit'];
              }
              if ($canDelete && $report['ReportStatus'] === 'Created') {
                  $teActions[] = [
                      'html' => table_action_delete_form(
                          '/travel-expense/delete.php',
                          ['report_id' => (int) $report['ReportID']],
                          'Delete this expense report?'
                      ),
                  ];
              }
              table_actions_cell($teActions);
              ?>
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
