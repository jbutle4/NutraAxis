<?php

/**
 * Shared Bids / Estimates panel for initiative view + edit pages.
 *
 * Expected in scope:
 * - int $initiativeId
 * - array $initiative
 * - array $bids
 * - bool $canAward
 */
$initiativeStatus = (string) ($initiative['Status'] ?? '');
$canAddBid = bid_can_create() && !in_array($initiativeStatus, ['Awarded', 'Cancelled', 'Closed'], true);
?>
<section class="detail-card<?= !empty($bidsSectionFollow) ? ' detail-card--follow' : '' ?>" id="bids">
  <div class="detail-card-header">
    <div>
      <h2>Bids / Estimates</h2>
      <p class="form-hint">Add supplier estimates here, then open a bid to upload files or award it.</p>
    </div>
    <?php if ($canAddBid): ?>
    <a class="btn-primary" href="/procurement-bids/bid-new.php?initiative_id=<?= (int) $initiativeId ?>">Add Bid</a>
    <?php endif; ?>
  </div>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Vendor</th>
          <th>Amount</th>
          <th>Submitted</th>
          <th>Status</th>
          <th>Files</th>
          <th><?= htmlspecialchars(table_actions_header(['Open', 'Award', 'Invoice'])) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($bids === []): ?>
        <tr>
          <td colspan="6">
            No bids yet.
            <?php if ($canAddBid): ?>
            <a class="btn-text" href="/procurement-bids/bid-new.php?initiative_id=<?= (int) $initiativeId ?>">Add the first supplier estimate</a>
            <?php elseif (in_array($initiativeStatus, ['Awarded', 'Cancelled', 'Closed'], true)): ?>
            This initiative is <?= htmlspecialchars($initiativeStatus) ?>, so new bids cannot be added.
            <?php endif; ?>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($bids as $bid): ?>
        <?php
        $actions = [
            [
                'href'  => '/procurement-bids/bid-edit.php?id=' . (int) $bid['BidEstimateID'],
                'label' => 'Open',
            ],
        ];
        if ($canAward && (string) $bid['Status'] !== 'Selected' && (string) $bid['Status'] !== 'Withdrawn') {
            $actions[] = [
                'html' => '<form method="post" action="/procurement-bids/award.php" class="inline-form table-action-form" onsubmit="return confirm(\'Award this bid? This creates or links a Supplier and a Draft/Estimate Supplier Invoice. No payment request will be created.\');">'
                    . '<input type="hidden" name="bid_id" value="' . (int) $bid['BidEstimateID'] . '" />'
                    . '<button type="submit" class="table-action-btn">Award</button>'
                    . '</form>',
            ];
        }
        if (!empty($bid['AwardedSupplierInvoiceID'])) {
            $actions[] = [
                'href'  => '/accounting/supplier-invoices/view.php?id=' . (int) $bid['AwardedSupplierInvoiceID'],
                'label' => 'Invoice',
            ];
        }
        ?>
        <tr>
          <td>
            <?= htmlspecialchars($bid['VendorName']) ?>
            <?php if (!empty($bid['SupplierCode'])): ?>
            <div class="form-hint"><?= htmlspecialchars($bid['SupplierCode']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars(accounting_format_money($bid['BidAmount'])) ?> <?= htmlspecialchars($bid['CurrencyCode'] ?? 'USD') ?></td>
          <td><?= htmlspecialchars(supplier_invoice_normalize_form_date($bid['SubmittedDate'] ?? null) ?: '—') ?></td>
          <td><span class="status-badge <?= bid_estimate_status_class((string) $bid['Status']) ?>"><?= htmlspecialchars($bid['Status']) ?></span></td>
          <td><?= (int) $bid['AttachmentCount'] ?></td>
          <?php table_actions_cell($actions); ?>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
