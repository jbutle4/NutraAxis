<?php
/** @var array $approvalLog */
require_once __DIR__ . '/admin.php';
$approvalLog = $approvalLog ?? [];
if ($approvalLog === []) {
    return;
}
?>
<section class="detail-card">
  <h2>Approval history</h2>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Date</th><th>Approver</th><th>Result</th><th>Comments</th></tr></thead>
      <tbody>
        <?php foreach ($approvalLog as $entry): ?>
        <tr>
          <td><?= htmlspecialchars(admin_format_datetime($entry['LogDate'])) ?></td>
          <td><?= htmlspecialchars($entry['ApproverName']) ?></td>
          <td><?= htmlspecialchars($entry['ApproverResult']) ?></td>
          <td><?= nl2br(htmlspecialchars(supplier_invoice_format_log_comments($entry['ApproverComments'] ?? null))) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
