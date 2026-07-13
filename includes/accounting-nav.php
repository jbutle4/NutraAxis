<?php
/** @var string $accountingSection */
$accountingSection = $accountingSection ?? 'overview';

require_once __DIR__ . '/accounting.php';
require_once __DIR__ . '/approval.php';
$approvalPending = approval_count_pending_for_user();
?>
<nav class="admin-nav" aria-label="Accounting">
  <?php foreach (ACCOUNTING_SECTIONS as $slug => $section): ?>
  <a href="<?= htmlspecialchars($section['href']) ?>" class="<?= $accountingSection === $slug ? 'is-active' : '' ?>"><?= htmlspecialchars($section['title']) ?></a>
  <?php endforeach; ?>
  <?php if ($approvalPending > 0 || approval_can_read_type('QBOInsert') || approval_can_read_type('Payment')): ?>
  <a href="<?= htmlspecialchars(approval_index_url(null, $approvalPending > 0 ? 'pending' : null)) ?>" class="<?= ($accountingSection ?? '') === 'approvals' ? 'is-active' : '' ?>">
    Approvals<?php if ($approvalPending > 0): ?> <span class="nav-badge"><?= $approvalPending ?></span><?php endif; ?>
  </a>
  <?php endif; ?>
</nav>
