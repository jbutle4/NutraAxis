<?php
/** @var int $poId */
/** @var array $poReceipts */
?>
      <section class="detail-card supplier-po-report production-status-card">
        <div class="production-status-header">
          <div>
            <h2>PO receipts</h2>
            <p class="account-card-lead">
              <?= count($poReceipts) === 1 ? '1 receipt' : count($poReceipts) . ' receipts' ?> recorded against this purchase order
            </p>
          </div>
          <?php if (por_can_create()): ?>
          <div class="module-actions">
            <a class="btn-secondary" href="/po-receiving/new.php?po_id=<?= $poId ?>">New PO Receipt</a>
          </div>
          <?php endif; ?>
        </div>

        <div class="admin-table-wrap production-status-table-wrap">
          <table class="admin-table production-status-table">
            <thead>
              <tr>
                <th>Receipt</th>
                <th>Jazz ASN</th>
                <th>Status</th>
                <th>Scheduled</th>
                <th>Actual receipt</th>
                <th>Appointment</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($poReceipts === []): ?>
              <tr><td colspan="7">No receipts recorded for this purchase order.</td></tr>
              <?php else: ?>
              <?php foreach ($poReceipts as $receipt): ?>
              <tr>
                <td><a class="btn-text" href="/po-receiving/view.php?id=<?= (int) $receipt['PORID'] ?>">Receipt #<?= (int) $receipt['PORID'] ?></a></td>
                <td><?= htmlspecialchars($receipt['JazzASN'] ?? '—') ?></td>
                <td><span class="status-badge <?= por_status_class($receipt['PORStatus']) ?>"><?= htmlspecialchars($receipt['PORStatus']) ?></span></td>
                <td><?= htmlspecialchars(por_format_scheduled($receipt['ScheduledReceiptDate'] ?? null, $receipt['ScheduledReceiptTime'] ?? null)) ?></td>
                <td><?= htmlspecialchars(por_format_date($receipt['ActualReceiptDate'] ?? null)) ?></td>
                <td><?= !empty($receipt['AppointmentMade']) ? 'Yes' : 'No' ?></td>
                <td><?= htmlspecialchars(por_format_date($receipt['CreateDate'] ?? null)) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
