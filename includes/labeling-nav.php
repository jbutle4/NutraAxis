<?php
/** @var string $activeLabelSection overview|templates|batch-printing|compliance|versions|white-label|oad-batch-po|oad-inventory|oad-demand */
$activeLabelSection = $activeLabelSection ?? 'overview';
?>
<nav class="admin-nav" aria-label="<?= htmlspecialchars(label_module_title()) ?>">
  <a href="/labeling-operations/" class="<?= $activeLabelSection === 'overview' ? 'is-active' : '' ?>">Overview</a>
  <a href="/labeling-operations/templates/" class="<?= $activeLabelSection === 'templates' ? 'is-active' : '' ?>">Label Templates</a>
  <a href="/labeling-operations/batch-printing/" class="<?= $activeLabelSection === 'batch-printing' ? 'is-active' : '' ?>">Label Batch Printing</a>
  <a href="/labeling-operations/compliance/" class="<?= $activeLabelSection === 'compliance' ? 'is-active' : '' ?>">Label Compliance Review</a>
  <a href="/labeling-operations/versions/" class="<?= $activeLabelSection === 'versions' ? 'is-active' : '' ?>">Version Control</a>
  <a href="/labeling-operations/white-label-orders/" class="<?= $activeLabelSection === 'white-label' ? 'is-active' : '' ?>">White Label Orders</a>
  <a href="/labeling-operations/one-a-day-pack-batch-order-po/" class="<?= $activeLabelSection === 'oad-batch-po' ? 'is-active' : '' ?>">One-A-Day Pack Batch PO</a>
  <a href="/labeling-operations/one-a-day-pack-inventory/" class="<?= $activeLabelSection === 'oad-inventory' ? 'is-active' : '' ?>">One-A-Day Pack Inventory</a>
  <a href="/labeling-operations/one-a-day-pack-demand/" class="<?= $activeLabelSection === 'oad-demand' ? 'is-active' : '' ?>">One-A-Day Pack Demand</a>
</nav>
