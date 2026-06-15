<?php
/** @var string $activeTeSection list|new|view|approvals */
$activeTeSection = $activeTeSection ?? 'list';

require_once __DIR__ . '/te-approval.php';

$pendingApprovalCount = te_can_read_approval_queue() ? te_count_pending_approvals() : 0;
?>
<nav class="admin-nav" aria-label="Travel & Expense">
  <?php if (te_can_read()): ?>
  <a href="/travel-expense/" class="<?= $activeTeSection === 'list' ? 'is-active' : '' ?>">Reports</a>
  <?php endif; ?>
  <?php if (te_can_read_approval_queue()): ?>
  <a href="/travel-expense/approvals.php" class="<?= $activeTeSection === 'approvals' ? 'is-active' : '' ?>">
    Approvals<?php if ($pendingApprovalCount > 0): ?> <span class="nav-badge"><?= $pendingApprovalCount ?></span><?php endif; ?>
  </a>
  <?php endif; ?>
  <?php if (te_can_create()): ?>
  <a href="/travel-expense/new.php" class="<?= $activeTeSection === 'new' ? 'is-active' : '' ?>">New Report</a>
  <?php endif; ?>
</nav>
