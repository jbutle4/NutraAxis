<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/po-receiving.php';
require dirname(__DIR__) . '/includes/po-receiving-asn.php';
require dirname(__DIR__) . '/includes/delivery-appointment.php';
require dirname(__DIR__) . '/includes/po-receiving-attachments.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

por_require_read();

$porId = (int) ($_GET['id'] ?? 0);
$notice = $_GET['notice'] ?? null;
$error = isset($_GET['error']) ? (string) $_GET['error'] : null;
$warning = isset($_GET['warning']) ? (string) $_GET['warning'] : null;

$receipt = $porId > 0 ? por_get($porId) : null;

if ($receipt === null) {
    header('Location: /po-receiving/', true, 302);
    exit;
}

$syncResult = por_sync_jazz_asn_from_integration($porId);
$receipt = por_get($porId);
$lines = por_get_lines($porId);
$syncWarning = null;
if (!$syncResult['ok'] && !empty($syncResult['error'])) {
    $syncWarning = (string) $syncResult['error'];
} elseif (!empty($syncResult['warning'])) {
    $syncWarning = (string) $syncResult['warning'];
}

$activeSlug = 'po-receiving';
$attachments = por_list_attachments($porId);

$pageTitle = $receipt['PONumber'] . ' Receipt | PO Receiving';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      ob_start();
      $dasUrl = das_appointment_url_for_por($porId, ['return_to' => 'por', 'por_id' => $porId]);
      ?>
          <a class="btn-secondary" href="<?= htmlspecialchars($dasUrl) ?>">Delivery appointment</a>
          <?php if (por_can_transmit($receipt)): ?>
          <a class="btn-primary" href="/po-receiving/asn.php?id=<?= $porId ?>&amp;v=20260611">View ASN &amp; Transmit to Jazz</a>
          <?php else: ?>
          <a class="btn-secondary" href="/po-receiving/asn.php?id=<?= $porId ?>">View ASN Data</a>
          <?php endif; ?>
          <?php if (por_can_edit($receipt)): ?>
          <a class="btn-secondary" href="/po-receiving/edit.php?id=<?= $porId ?>">Edit</a>
          <?php endif; ?>
          <a class="btn-secondary" href="/po-management/view.php?id=<?= (int) $receipt['POID'] ?>">View PO</a>
          <?php if (por_can_delete()): ?>
          <form method="post" action="/po-receiving/delete.php" class="inline-form" onsubmit="return confirm('Delete this receipt record?');">
            <input type="hidden" name="por_id" value="<?= $porId ?>" />
            <button type="submit" class="btn-text btn-text-danger">Delete</button>
          </form>
          <?php endif; ?>
      <?php
      $listToolbar = trim(ob_get_clean());
      $porLead = '<span class="status-badge ' . por_status_class($receipt['PORStatus']) . '">' . htmlspecialchars($receipt['PORStatus']) . '</span> · ' . htmlspecialchars($receipt['SupplierName']);
      render_list_page_header([
          'back_href'  => '/po-receiving/',
          'back_label' => 'Back to PO Receiving',
          'category'   => 'PO Receipt',
          'title'      => $receipt['PONumber'],
          'lead'       => $porLead,
          'lead_html'  => true,
      ]);
      ?>

      <?php if ($syncWarning !== null && $syncWarning !== ''): ?>
      <div class="admin-notice<?= !$syncResult['ok'] ? ' is-error' : '' ?>" role="status"><?= htmlspecialchars($syncWarning) ?></div>
      <?php endif; ?>

      <?php if ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php elseif ($notice === 'created' || $notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Receipt saved successfully.</div>
      <?php elseif ($notice === 'transmitted'): ?>
      <div class="admin-notice is-success" role="status">
        ASN transmitted to Jazz successfully.
        <?php if (!empty($receipt['JazzASN'])): ?>
        Jazz ASN number: <strong><?= htmlspecialchars($receipt['JazzASN']) ?></strong>
        <?php endif; ?>
        · <a href="/po-receiving/jazz-asns.php">View Jazz ASNs</a>
        · <a href="/po-receiving/asn.php?id=<?= $porId ?>">View ASN data</a>
      </div>
      <?php if ($warning !== null && $warning !== ''): ?>
      <div class="admin-notice" role="status"><?= htmlspecialchars($warning) ?></div>
      <?php endif; ?>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
      <?php endif; ?>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="detail-grid detail-grid-stacked">
        <section class="detail-card">
          <h2>Receipt details</h2>
          <dl class="detail-list">
            <div><dt>PO number</dt><dd><a href="/po-management/view.php?id=<?= (int) $receipt['POID'] ?>"><?= htmlspecialchars($receipt['PONumber']) ?></a></dd></div>
            <div><dt>Jazz ASN</dt><dd>
              <?php if (!empty($receipt['JazzASN'])): ?>
              <a class="btn-text" href="<?= htmlspecialchars(jazz_oms_asn_detail_url($receipt['JazzASN'])) ?>"><?= htmlspecialchars($receipt['JazzASN']) ?></a>
              <?php else: ?>
              —
              <?php endif; ?>
            </dd></div>
            <div><dt>Jazz ASN status</dt><dd><?= htmlspecialchars($receipt['JazzASNStatus'] ?? '—') ?></dd></div>
            <div><dt>Jazz ASN updated</dt><dd><?= htmlspecialchars(por_format_datetime($receipt['JazzASNModifiedDate'] ?? null)) ?></dd></div>
            <div><dt>Business type</dt><dd><?= htmlspecialchars($receipt['BusinessType'] ?? '—') ?></dd></div>
            <div><dt>Shipment number</dt><dd><?= htmlspecialchars($receipt['ShipmentNumber'] ?? '—') ?></dd></div>
            <div><dt>Facility</dt><dd><?= htmlspecialchars($receipt['Facility'] ?? '—') ?></dd></div>
            <div><dt>Carrier number</dt><dd><?= htmlspecialchars($receipt['CarrierNumber'] ?? '—') ?></dd></div>
            <div><dt>Seal number</dt><dd><?= htmlspecialchars($receipt['SealNumber'] ?? '—') ?></dd></div>
            <div><dt>Load number</dt><dd><?= htmlspecialchars($receipt['LoadNumber'] ?? '—') ?></dd></div>
            <div><dt>Shipping method</dt><dd><?= htmlspecialchars($receipt['ShippingMethod'] ?? '—') ?></dd></div>
            <div><dt>Shipped at</dt><dd><?= htmlspecialchars(por_format_datetime($receipt['ShippedAt'] ?? null)) ?></dd></div>
            <div><dt>Supplier</dt><dd><?= htmlspecialchars($receipt['SupplierName']) ?></dd></div>
            <div><dt>Expected date</dt><dd><?= htmlspecialchars(por_format_date($receipt['ExpectedDate'] ?? null)) ?></dd></div>
            <div><dt>Scheduled receipt</dt><dd><?= htmlspecialchars(por_format_scheduled($receipt['ScheduledReceiptDate'] ?? null, $receipt['ScheduledReceiptTime'] ?? null)) ?></dd></div>
            <div><dt>Appointment made</dt><dd><?= !empty($receipt['AppointmentMade']) ? 'Yes' : 'No' ?></dd></div>
            <div><dt>Actual receipt date</dt><dd><?= htmlspecialchars(por_format_date($receipt['ActualReceiptDate'] ?? null)) ?></dd></div>
            <div><dt>Delivery address</dt><dd><?= htmlspecialchars($receipt['DeliveryAddress'] ?? '—') ?></dd></div>
            <div><dt>Created</dt><dd><?= htmlspecialchars(admin_format_datetime($receipt['CreateDate'])) ?><?= !empty($receipt['CreatedBy']) ? ' by ' . htmlspecialchars($receipt['CreatedBy']) : '' ?></dd></div>
            <div><dt>Last modified</dt><dd><?= htmlspecialchars(admin_format_datetime($receipt['ModifiedDate'])) ?><?= !empty($receipt['ModifiedBy']) ? ' by ' . htmlspecialchars($receipt['ModifiedBy']) : '' ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Line items</h2>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Line</th>
                  <th>SKU</th>
                  <th>Description</th>
                  <th>Expected</th>
                  <th>Received</th>
                  <th>Case barcode</th>
                  <th>SKU barcode</th>
                  <th>Country of origin</th>
                  <th>On hold</th>
                  <th>Note</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lines as $line): ?>
                <tr>
                  <td><?= (int) $line['LineNumber'] ?></td>
                  <td><?= htmlspecialchars($line['ItemSKU'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($line['ItemDescription']) ?></td>
                  <td><?= htmlspecialchars(por_format_qty($line['QuantityExpected'] ?? null)) ?></td>
                  <td><?= htmlspecialchars(por_format_qty($line['QuantityReceived'] ?? null)) ?></td>
                  <td><?= htmlspecialchars($line['CaseBarcode'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($line['SKUBarcode'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($line['CountryOfOrigin'] ?? '—') ?></td>
                  <td><?= !empty($line['OnHold']) ? 'Yes' : 'No' ?></td>
                  <td><?= htmlspecialchars($line['LINote'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <?php if (!empty($receipt['PORNotes'])): ?>
        <section class="detail-card">
          <h2>Notes</h2>
          <p><?= nl2br(htmlspecialchars($receipt['PORNotes'])) ?></p>
        </section>
        <?php endif; ?>
      </div>

      <?php
        $showUploadForm = por_can_update();
        require dirname(__DIR__) . '/includes/po-receiving-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
