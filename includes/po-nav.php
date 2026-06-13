<?php
/** @var string $activePoSection list|new|view|approvals|import */
$activePoSection = $activePoSection ?? 'list';

require_once __DIR__ . '/po-approval.php';

$pendingApprovalCount = po_can_read_approval_queue() ? po_count_pending_approvals() : 0;
$isApproverPrimary = po_can_read_approval_queue() && !po_can_create();
?>
<nav class="admin-nav" aria-label="PO Management">
  <?php if ($isApproverPrimary || po_can_read_approval_queue()): ?>
  <a href="/po-management/approvals.php" class="<?= $activePoSection === 'approvals' ? 'is-active' : '' ?>">
    Approvals<?php if ($pendingApprovalCount > 0): ?> <span class="nav-badge"><?= $pendingApprovalCount ?></span><?php endif; ?>
  </a>
  <?php endif; ?>
  <?php if (permission_can_read(po_permission_value())): ?>
  <a href="/po-management/" class="<?= $activePoSection === 'list' ? 'is-active' : '' ?>">Purchase Orders</a>
  <?php endif; ?>
  <?php if (po_can_create()): ?>
  <a href="/po-management/new.php" class="<?= $activePoSection === 'new' ? 'is-active' : '' ?>">New PO</a>
  <a href="/po-management/import.php" class="<?= $activePoSection === 'import' ? 'is-active' : '' ?>">Import</a>
  <?php endif; ?>
  <?php if (po_can_update()): ?>
  <a href="/po-management/import-production-status.php" class="<?= $activePoSection === 'production-import' ? 'is-active' : '' ?>">Production Import</a>
  <?php endif; ?>
</nav>
