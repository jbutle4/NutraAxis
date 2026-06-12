<?php
/** @var array $order */
/** @var array $lines */
/** @var array $productionByLine keyed by POLineID */
/** @var bool $canEditProduction */
$productionByLine = $productionByLine ?? [];
$canEditProduction = $canEditProduction ?? false;
$showProduction = in_array($order['POStatus'] ?? '', PO_PRODUCTION_EDITABLE_STATUSES, true);
?>
      <div class="account-card production-status-card">
        <div class="production-status-header">
          <div>
            <h2>Production status</h2>
            <p class="account-card-lead">Track manufacturing, packaging, testing, and shipping progress for each PO line.</p>
          </div>
          <?php if ($canEditProduction): ?>
          <div class="module-actions">
            <a class="btn-secondary" href="/po-management/production-status.php?id=<?= (int) $order['POID'] ?>">Edit production status</a>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!$showProduction): ?>
        <div class="admin-notice" role="status">Production tracking is available after the purchase order is approved.</div>
        <?php elseif ($lines === []): ?>
        <p class="account-card-lead">No line items on this purchase order.</p>
        <?php else: ?>
        <div class="admin-table-wrap production-status-table-wrap">
          <table class="admin-table production-status-table">
            <thead>
              <tr>
                <th>Line</th>
                <th>Product</th>
                <th>SKU</th>
                <th>MFG</th>
                <th>Bottle / pkg</th>
                <th>Bulk test</th>
                <th>Bottle test</th>
                <th>Target ship</th>
                <th>Actual ship</th>
                <th>Pallets</th>
                <th>Est. lbs</th>
                <th>Comments</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lines as $line): ?>
              <?php
                $poLineId = (int) $line['POLineID'];
                $production = po_production_for_line($poLineId, $productionByLine[$poLineId] ?? null);
                $comments = trim((string) ($production['Comments'] ?? ''));
                $commentsPreview = $comments !== '' ? (strlen($comments) > 48 ? substr($comments, 0, 45) . '…' : $comments) : '—';
              ?>
              <tr>
                <td><?= (int) $line['LineNumber'] ?></td>
                <td>
                  <span class="production-product-cell"><?= htmlspecialchars($line['ItemDescription']) ?></span>
                  <?php if (!empty($line['QuoteNumber'])): ?>
                  <span class="text-muted production-product-sku">Quote · <?= htmlspecialchars($line['QuoteNumber']) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= !empty($line['ItemSKU']) ? htmlspecialchars($line['ItemSKU']) : '—' ?></td>
                <td><?= htmlspecialchars($production['MfgStatus']) ?></td>
                <td><?= htmlspecialchars($production['BottlePackagingStatus']) ?></td>
                <td><?= htmlspecialchars($production['BulkTestStatus']) ?></td>
                <td><?= htmlspecialchars($production['BottleTestStatus']) ?></td>
                <td><?= htmlspecialchars(po_format_date($production['TargetShipDate'])) ?></td>
                <td><?= htmlspecialchars(po_format_date($production['ActualShipDate'])) ?></td>
                <td><?= $production['PalletCount'] !== null ? (int) $production['PalletCount'] : '—' ?></td>
                <td><?= $production['EstWeightLbs'] !== null ? htmlspecialchars(number_format((float) $production['EstWeightLbs'], 2)) : '—' ?></td>
                <td class="production-comments-cell"<?= $comments !== '' ? ' title="' . htmlspecialchars($comments) . '"' : '' ?>><?= htmlspecialchars($commentsPreview) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
