<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/approval.php';
require_once dirname(__DIR__) . '/includes/admin.php';

approval_require_any();

$activeSlug = 'approvals';
$approvalType = isset($_GET['type']) ? (string) $_GET['type'] : '';
$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : 'all';
$allowedTypes = approval_types_for_user();
$queueLinks = approval_queue_links_for_user();
$pendingTotal = approval_count_pending_for_user();

if ($approvalType !== '' && !in_array($approvalType, $allowedTypes, true)) {
    $approvalType = '';
}

if (!in_array($statusFilter, ['all', 'pending', 'completed'], true)) {
    $statusFilter = 'all';
}

$filters = [
    'limit'         => 200,
    'allowed_types' => $allowedTypes,
    'status'        => $statusFilter,
];
if ($approvalType !== '') {
    $filters['approval_type'] = $approvalType;
}

$entries = approval_list_combined($filters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Approvals | NutraAxis Operations';
$pageDescription = 'Review pending approval requests and approval history across PO, T&E, payment, and QBO insert workflows.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations',
          'category'   => 'Workflow',
          'title'      => 'Approvals',
          'lead'       => 'All pending requests and approval history in one place. Filter by type or status to focus on what you need.',
      ]); ?>

      <?php if ($notice === 'actioned'): ?>
      <div class="admin-notice is-success" role="status">Approval action recorded successfully.</div>
      <?php endif; ?>

      <?php if ($pendingTotal > 0): ?>
      <div class="admin-notice" role="status"><?= (int) $pendingTotal ?> item<?= $pendingTotal === 1 ? '' : 's' ?> awaiting approval. Use the <strong>Pending only</strong> filter or review highlighted rows below.</div>
      <?php endif; ?>

      <form class="admin-filters page-list-filters" method="get" action="">
        <label>
          Approval type
          <select name="type">
            <option value="">All types</option>
            <?php foreach ($allowedTypes as $type): ?>
            <?php
              $typePending = 0;
              foreach ($queueLinks as $link) {
                  if (($link['type'] ?? '') === $type) {
                      $typePending = (int) ($link['pending'] ?? 0);
                      break;
                  }
              }
            ?>
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
            <option value="completed"<?= $statusFilter === 'completed' ? ' selected' : '' ?>>Completed only</option>
          </select>
        </label>
        <button type="submit" class="btn-secondary">Apply</button>
        <?php if ($approvalType !== '' || $statusFilter !== 'all'): ?>
        <a class="btn-text" href="/approvals/">Clear filters</a>
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
