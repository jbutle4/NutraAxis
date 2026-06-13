<?php
/** @var string $accountingSection */
$accountingSection = $accountingSection ?? 'overview';
?>
<nav class="admin-nav" aria-label="Accounting">
  <?php foreach (ACCOUNTING_SECTIONS as $slug => $section): ?>
  <a href="<?= htmlspecialchars($section['href']) ?>" class="<?= $accountingSection === $slug ? 'is-active' : '' ?>"><?= htmlspecialchars($section['title']) ?></a>
  <?php endforeach; ?>
</nav>
