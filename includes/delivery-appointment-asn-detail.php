<?php
/** @var array $asnContext */
if (!isset($asnContext) || trim((string) ($asnContext['asn_number'] ?? '')) === '') {
    // Do not use return here; this partial is included from view.php at top level.
} else {
$asnSource = (string) ($asnContext['source'] ?? 'jazz');
$hasReceiptTable = !empty($asnContext['asn_rows']);
$hasJazzLineTable = !$hasReceiptTable && !empty($asnContext['details']);
$asnPanelClass = 'delivery-appointment-asn-panel detail-card';
?>
      <section class="<?= htmlspecialchars($asnPanelClass) ?>">
          <h2>Jazz ASN details</h2>
          <p class="account-card-lead">
            Read-only ASN data for <?= htmlspecialchars((string) $asnContext['asn_number']) ?>.
            <?php if ($asnSource === 'jazz' && $asnContext['ok']): ?>
            · <a class="btn-text" href="<?= htmlspecialchars(jazz_oms_asn_detail_url($asnContext['asn_number'])) ?>">Open full ASN view</a>
            <?php elseif ($hasReceiptTable && ($asnContext['line_source'] ?? '') === 'receipt'): ?>
            · Line items loaded from linked PO receipt.
            <?php elseif ($hasReceiptTable): ?>
            · Loaded from linked PO receipt<?= !empty($asnContext['jazz_error']) ? ' (Jazz OMS lookup unavailable)' : '' ?>.
            <?php endif; ?>
          </p>

          <?php if (!empty($asnContext['jazz_error'])): ?>
          <div class="admin-notice" role="status"><?= htmlspecialchars((string) $asnContext['jazz_error']) ?> Showing PO receipt ASN data instead.</div>
          <?php endif; ?>

          <?php if (!$asnContext['ok']): ?>
          <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($asnContext['error'] ?? 'Unable to load ASN details.') ?></div>
          <?php else: ?>
          <?php if ($asnContext['header_fields'] !== []): ?>
          <h3>ASN header</h3>
          <dl class="detail-list">
            <?php foreach ($asnContext['header_fields'] as $field => $value): ?>
            <div>
              <dt><?= htmlspecialchars(das_asn_display_label((string) $field, $asnSource)) ?></dt>
              <dd><?= htmlspecialchars(das_asn_display_value($value, $asnSource)) ?></dd>
            </div>
            <?php endforeach; ?>
          </dl>
          <?php endif; ?>
          <?php endif; ?>

        <?php if ($asnContext['ok']): ?>
          <h3>Line detail</h3>
          <?php if ($hasReceiptTable): ?>
          <p class="account-card-lead"><?= count($asnContext['asn_rows']) ?> line<?= count($asnContext['asn_rows']) === 1 ? '' : 's' ?> on this ASN.</p>
          <div class="admin-table-wrap production-status-table-wrap">
            <table class="admin-table production-status-table">
              <thead>
                <tr>
                  <?php foreach ($asnContext['asn_columns'] as $column): ?>
                  <th><?= htmlspecialchars($column) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($asnContext['asn_rows'] as $row): ?>
                <?php if (!is_array($row)) continue; ?>
                <tr>
                  <?php foreach ($asnContext['asn_columns'] as $column): ?>
                  <td><?= htmlspecialchars((string) ($row[$column] ?? '')) ?></td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php elseif ($hasJazzLineTable): ?>
          <p class="account-card-lead"><?= count($asnContext['details']) ?> line<?= count($asnContext['details']) === 1 ? '' : 's' ?> on this ASN.</p>
          <div class="admin-table-wrap production-status-table-wrap">
            <table class="admin-table production-status-table">
              <thead>
                <tr>
                  <?php foreach ($asnContext['detail_columns'] as $column): ?>
                  <th><?= htmlspecialchars(das_asn_display_label($column, $asnSource)) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($asnContext['details'] as $line): ?>
                <?php if (!is_array($line)) continue; ?>
                <tr>
                  <?php foreach ($asnContext['detail_columns'] as $column): ?>
                  <td><?= htmlspecialchars(das_asn_display_value($line[$column] ?? null, $asnSource)) ?></td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <p class="account-card-lead">No detail lines returned.</p>
          <?php endif; ?>
        <?php endif; ?>
      </section>
<?php } ?>
