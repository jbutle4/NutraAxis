<?php
/** @var array $order */
/** @var array $lines */
?>
<article class="po-print-document">
  <header class="po-print-header">
    <div class="po-print-brand">
      <img src="/assets/logos/nutraaxis-logo.svg" alt="NutraAxis" class="po-print-logo" width="180" height="40" />
      <p class="po-print-doc-type">Purchase Order</p>
    </div>
    <div class="po-print-meta">
      <h1 class="po-print-po-number"><?= htmlspecialchars($order['PONumber']) ?></h1>
      <p><strong>Status:</strong> <?= htmlspecialchars($order['POStatus']) ?></p>
      <p><strong>PO date:</strong> <?= htmlspecialchars(po_format_date($order['OrderDate'])) ?></p>
      <p><strong>Expected delivery:</strong> <?= htmlspecialchars(po_format_date($order['ExpectedDeliveryDate'])) ?></p>
    </div>
  </header>

  <section class="po-print-parties">
    <div class="po-print-party">
      <h2>Buyer</h2>
      <p class="po-print-party-name"><?= htmlspecialchars($order['BuyerName'] ?? '—') ?></p>
      <p><?= nl2br(htmlspecialchars($order['BuyerAddress'] ?? '—')) ?></p>
      <?php if (!empty($order['BuyerContactName'])): ?>
      <p><strong>Contact:</strong> <?= htmlspecialchars($order['BuyerContactName']) ?></p>
      <?php endif; ?>
      <?php if (!empty($order['BuyerContactEmail'])): ?>
      <p><strong>Email:</strong> <?= htmlspecialchars($order['BuyerContactEmail']) ?></p>
      <?php endif; ?>
      <?php if (!empty($order['BuyerContactPhone'])): ?>
      <p><strong>Phone:</strong> <?= htmlspecialchars($order['BuyerContactPhone']) ?></p>
      <?php endif; ?>
    </div>

    <div class="po-print-party">
      <h2>Supplier</h2>
      <p class="po-print-party-name"><?= htmlspecialchars($order['SupplierName']) ?></p>
      <p><?= nl2br(htmlspecialchars($order['SupplierAddress'] ?? $order['SupplierTableAddress'] ?? '—')) ?></p>
      <?php if (!empty($order['ContactName'])): ?>
      <p><strong>Contact:</strong> <?= htmlspecialchars($order['ContactName']) ?></p>
      <?php endif; ?>
      <?php if (!empty($order['ContactEmail'])): ?>
      <p><strong>Email:</strong> <?= htmlspecialchars($order['ContactEmail']) ?></p>
      <?php endif; ?>
      <?php if (!empty($order['ContactPhone'])): ?>
      <p><strong>Phone:</strong> <?= htmlspecialchars($order['ContactPhone']) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="po-print-terms">
    <div class="po-print-terms-grid">
      <div><strong>Payment terms</strong><br /><?= htmlspecialchars($order['PaymentTerms'] ?? '—') ?></div>
      <div><strong>Delivery terms</strong><br /><?= htmlspecialchars($order['DeliveryTerms'] ?? '—') ?></div>
      <div><strong>Delivery address</strong><br /><?= nl2br(htmlspecialchars($order['DeliveryAddress'] ?? '—')) ?></div>
      <div><strong>Reference documents</strong><br /><?= nl2br(htmlspecialchars($order['ReferenceDocuments'] ?? '—')) ?></div>
    </div>
  </section>

  <section class="po-print-lines">
    <h2>Line items</h2>
    <table class="po-print-table">
      <thead>
        <tr>
          <th>Line</th>
          <th>Product / bottle title</th>
          <th>SKU</th>
          <th>Quote #</th>
          <th>Unit price</th>
          <th>Exp date</th>
          <th>Qty</th>
          <th>Line total</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($lines === []): ?>
        <tr>
          <td colspan="8">No line items.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($lines as $line): ?>
        <tr>
          <td><?= (int) $line['LineNumber'] ?></td>
          <td><?= htmlspecialchars($line['ItemDescription']) ?></td>
          <td><?= !empty($line['ItemSKU']) ? htmlspecialchars($line['ItemSKU']) : '—' ?></td>
          <td><?= !empty($line['QuoteNumber']) ? htmlspecialchars($line['QuoteNumber']) : '—' ?></td>
          <td><?= htmlspecialchars(po_format_money($line['UnitPrice'])) ?></td>
          <td><?= htmlspecialchars(po_format_date($line['ExpirationDate'] ?? null)) ?></td>
          <td><?= htmlspecialchars(po_format_qty($line['Quantity'] ?? null)) ?></td>
          <td><?= htmlspecialchars(po_format_money($line['LineTotal'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="po-print-totals">
    <dl class="po-print-totals-list">
      <div><dt>Subtotal</dt><dd><?= htmlspecialchars(po_format_money($order['Subtotal'])) ?></dd></div>
      <div><dt>Shipping &amp; handling</dt><dd><?= $order['ShippingHandling'] !== null ? htmlspecialchars(po_format_money($order['ShippingHandling'])) : 'TBD' ?></dd></div>
      <div class="po-print-total-due"><dt>Total due</dt><dd><?= htmlspecialchars(po_format_money($order['TotalDue'] ?? $order['Subtotal'])) ?></dd></div>
    </dl>
  </section>

  <?php if (!empty($order['SpecialInstructions'])): ?>
  <section class="po-print-notes">
    <h2>Special instructions</h2>
    <p><?= nl2br(htmlspecialchars($order['SpecialInstructions'])) ?></p>
  </section>
  <?php endif; ?>

  <?php if (!empty($order['Notes'])): ?>
  <section class="po-print-notes">
    <h2>Notes</h2>
    <p><?= nl2br(htmlspecialchars($order['Notes'])) ?></p>
  </section>
  <?php endif; ?>

  <footer class="po-print-footer">
    <p>Created by <?= htmlspecialchars($order['CreatedByName']) ?> · Printed <?= htmlspecialchars(gmdate('M j, Y g:i A')) ?> UTC</p>
  </footer>
</article>
