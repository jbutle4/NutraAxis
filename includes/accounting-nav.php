<?php
/** @var string $accountingSection */
$accountingSection = $accountingSection ?? 'overview';

require_once __DIR__ . '/accounting.php';
$approvalPending = 0;
$approvalNavAvailable = is_file(__DIR__ . '/approval.php');
if ($approvalNavAvailable) {
    require_once __DIR__ . '/approval.php';
    $approvalPending = approval_count_pending_for_user();
}

$canReadAccounting = !auth_is_logged_in() || accounting_can_read();
$canReadApprovalsQueue = $approvalNavAvailable && (
    approval_can_read_type('PO')
    || approval_can_read_type('Payment')
    || approval_can_read_type('QBOInsert')
);
$canReadApprovals = $approvalNavAvailable && approval_types_for_user() !== [];
?>
<nav class="admin-nav" aria-label="Accounting">
  <?php foreach (ACCOUNTING_SECTIONS as $slug => $section): ?>
  <?php
    if ($slug === 'procurement-approvals') {
        if (!$canReadApprovalsQueue) {
            continue;
        }
    } elseif (!$canReadAccounting) {
        continue;
    }
  ?>
  <a href="<?= htmlspecialchars($section['href']) ?>" class="<?= $accountingSection === $slug ? 'is-active' : '' ?>"><?= htmlspecialchars($section['title']) ?></a>
  <?php endforeach; ?>
  <?php if ($canReadApprovals): ?>
  <a href="<?= htmlspecialchars(approval_index_url(null, $approvalPending > 0 ? 'pending' : null)) ?>" class="<?= ($accountingSection ?? '') === 'approvals' ? 'is-active' : '' ?>">
    Approvals<?php if ($approvalPending > 0): ?> <span class="nav-badge"><?= $approvalPending ?></span><?php endif; ?>
  </a>
  <?php endif; ?>
</nav>
