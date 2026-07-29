<?php
/** @var array $priorReceipts */
/** @var int|null $excludePorId */
$excludePorId = $excludePorId ?? null;
$rows = [];
foreach ($priorReceipts as $receipt) {
    $porId = (int) ($receipt['PORID'] ?? 0);
    if ($excludePorId !== null && $porId === $excludePorId) {
        continue;
    }
    $rows[] = $receipt;
}

if ($rows === []) {
    return;
}
?>
      <section class="detail-card supplier-po-report production-status-card por-prior-receipts-card">
        <div class="production-status-header">
          <div>
            <h2>Prior receipts on this PO</h2>
            <p class="account-card-lead">
              <?= count($rows) === 1 ? '1 other receipt' : count($rows) . ' other receipts' ?> recorded for this purchase order
            </p>
          </div>
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
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $receipt): ?>
              <tr>
                <td>
                  <a class="btn-text" href="<?= htmlspecialchars(por_page_path('/po-receiving/view.php')) ?>?id=<?= (int) $receipt['PORID'] ?>">
                    Receipt #<?= (int) $receipt['PORID'] ?>
                  </a>
                </td>
                <td><?= htmlspecialchars($receipt['JazzASN'] ?? '—') ?></td>
                <td><span class="status-badge <?= por_status_class($receipt['PORStatus']) ?>"><?= htmlspecialchars($receipt['PORStatus']) ?></span></td>
                <td><?= htmlspecialchars(por_format_scheduled($receipt['ScheduledReceiptDate'] ?? null, $receipt['ScheduledReceiptTime'] ?? null)) ?></td>
                <td><?= htmlspecialchars(por_format_date($receipt['ActualReceiptDate'] ?? null)) ?></td>
                <td><?= htmlspecialchars(por_format_date($receipt['CreateDate'] ?? null)) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
