<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/approval.php';
require_once dirname(__DIR__) . '/includes/admin.php';

auth_require_login();

$procurementApprovalTypes = ['PO', 'Payment', 'QBOInsert'];
$allowedTypes = array_values(array_filter(
    $procurementApprovalTypes,
    static fn(string $type): bool => approval_can_read_type($type)
));

if ($allowedTypes === []) {
    auth_render_access_denied('You do not have permission to view procurement approvals.');
}

$activeSlug = 'procurement-approvals';
$hubBack = app_module_hub_back_link('procurement-approvals');
$approvalType = isset($_GET['type']) ? (string) $_GET['type'] : '';
$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : 'all';

if ($approvalType !== '' && !in_array($approvalType, $allowedTypes, true)) {
    $approvalType = '';
}

if (!in_array($statusFilter, ['all', 'pending', 'approved'], true)) {
    $statusFilter = 'all';
}

$listStatus = $statusFilter === 'approved' ? 'completed' : $statusFilter;

$filters = [
    'limit'         => 200,
    'allowed_types' => $allowedTypes,
    'status'        => $listStatus,
];
if ($approvalType !== '') {
    $filters['approval_type'] = $approvalType;
}

$entries = approval_list_combined($filters);

$pendingTotal = 0;
$typePendingCounts = [];
foreach (approval_queue_links_for_user() as $link) {
    $type = (string) ($link['type'] ?? '');
    if (!in_array($type, $allowedTypes, true)) {
        continue;
    }
    $count = (int) ($link['pending'] ?? 0);
    $typePendingCounts[$type] = $count;
    $pendingTotal += $count;
}

$pageTitle = 'Approvals Queue | Procurement';
$pageDescription = 'Verify pending and completed purchase order and supplier invoice approvals across Payment and QBO Insert recovery flows.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Procurement',
          'title'      => 'Approvals Queue',
          'lead'       => 'Confirm what is still waiting and what has already been approved for purchase orders and supplier invoices (Payment approval and QBO Insert recovery).',
      ]); ?>

      <?php if ($pendingTotal > 0): ?>
      <div class="admin-notice" role="status"><?= (int) $pendingTotal ?> item<?= $pendingTotal === 1 ? '' : 's' ?> awaiting approval. Use the <strong>Pending only</strong> filter or review highlighted rows below.</div>
      <?php endif; ?>

      <form class="admin-filters page-list-filters" method="get" action="">
        <label>
          Approval type
          <select name="type">
            <option value="">All types</option>
            <?php foreach ($allowedTypes as $type): ?>
            <?php $typePending = (int) ($typePendingCounts[$type] ?? 0); ?>
            <option value="<?= htmlspecialchars($type) ?>"<?= $approvalType === $type ? ' selected' : '' ?>>
              <?= htmlspecialchars(approval_type_label($type)) ?><?= $typePending > 0 ? ' (' . $typePending . ' pending)' : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Status
          <select name="status">
            <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>All activity</option>
            <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Pending only<?= $pendingTotal > 0 ? ' (' . $pendingTotal . ')' : '' ?></option>
            <option value="approved"<?= $statusFilter === 'approved' ? ' selected' : '' ?>>Approved / completed</option>
          </select>
        </label>
        <button type="submit" class="btn-secondary">Apply</button>
        <?php if ($approvalType !== '' || $statusFilter !== 'all'): ?>
        <a class="btn-text" href="/procurement-approvals/">Clear filters</a>
        <?php endif; ?>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Reference</th>
              <th>Approver</th>
              <th>Result</th>
              <th>Comments</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($entries === []): ?>
            <tr>
              <td colspan="7" class="empty-cell">No approval entries match the current filters.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($entries as $entry):
                $display = approval_format_log_row_for_display($entry);
                $isPending = !empty($entry['IsPending']);
                $href = $isPending
                    ? approval_pending_href((string) $entry['ApprovalType'], (int) $entry['EntityID'], $entry)
                    : ($display['href'] ?? null);
            ?>
            <tr<?= $isPending ? ' class="is-pending-row"' : '' ?>>
              <td><?= htmlspecialchars(admin_format_datetime($entry['LogDate'])) ?></td>
              <td><?= htmlspecialchars(approval_type_label((string) $entry['ApprovalType'])) ?></td>
              <td><?= htmlspecialchars($display['reference']) ?></td>
              <td><?= htmlspecialchars((string) $entry['ApproverName']) ?></td>
              <td><?php if ($isPending): ?>
                <span class="status-badge status-submitted">Pending approval</span>
              <?php else: ?>
                <?= htmlspecialchars((string) $entry['ApproverResult']) ?>
              <?php endif; ?></td>
              <td><?php
                $comments = trim(admin_db_to_string($entry['ApproverComments'] ?? null));
                echo $comments !== '' ? nl2br(htmlspecialchars($comments)) : '—';
              ?></td>
              <td>
                <?php if (!empty($href)): ?>
                <a href="<?= htmlspecialchars($href) ?>"><?= $isPending ? 'Review' : 'Open' ?></a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
