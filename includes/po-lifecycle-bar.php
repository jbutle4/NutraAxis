<?php
/** @var array $poLifecycleSteps */
?>
      <section class="po-lifecycle-bar" aria-label="PO lifecycle status">
        <div class="admin-table-wrap po-lifecycle-bar-wrap">
          <table class="po-lifecycle-table">
            <thead>
              <tr>
                <?php foreach ($poLifecycleSteps as $step): ?>
                <?php
                  $headerClass = 'po-lifecycle-step-header';
                  if (!($step['applicable'] ?? true)) {
                      $headerClass .= ' is-not-applicable';
                  } elseif ($step['complete'] ?? false) {
                      $headerClass .= ' is-complete';
                  } else {
                      $headerClass .= ' is-pending';
                  }
                ?>
                <th scope="col" class="<?= htmlspecialchars($headerClass) ?>"><?= htmlspecialchars((string) $step['label']) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <tr>
                <?php foreach ($poLifecycleSteps as $step): ?>
                <td class="po-lifecycle-step-date">
                  <?php if (!($step['applicable'] ?? true)): ?>
                  <span class="po-lifecycle-na">N/A</span>
                  <?php else: ?>
                  <?= htmlspecialchars(po_lifecycle_format_date($step['date'] ?? null)) ?>
                  <?php endif; ?>
                </td>
                <?php endforeach; ?>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
