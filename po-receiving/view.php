<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/po-receiving.php';

por_bind_page_environments();
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
    header('Location: ' . por_page_path('/po-receiving/'), true, 302);
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

$activeSlug = $activeSlug ?? 'po-receiving';
$attachments = por_list_attachments($porId);

$pageTitle = $receipt['PONumber'] . ' Receipt | PO Receiving';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <a class="breadcrumb" href="<?= htmlspecialchars(por_page_path('/po-receiving/')) ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to PO Receiving
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">PO Receipt</div>
          <h1><?= htmlspecialchars($receipt['PONumber']) ?></h1>
          <p class="page-lead">
            <span class="status-badge <?= por_status_class($receipt['PORStatus']) ?>"><?= htmlspecialchars($receipt['PORStatus']) ?></span>
            · <?= htmlspecialchars($receipt['SupplierName']) ?>
          </p>
        </div>
        <div class="admin-actions">
          <?php
          $dasUrl = das_appointment_url_for_por($porId, ['return_to' => 'por', 'por_id' => $porId]);
          ?>
          <a class="btn-secondary" href="<?= htmlspecialchars($dasUrl) ?>">Delivery appointment</a>
          <?php if (por_can_transmit($receipt)): ?>
          <a class="btn-primary" href="<?= htmlspecialchars(por_page_path('/po-receiving/asn.php')) ?>?id=<?= $porId ?>&amp;v=20260611">View ASN &amp; Transmit to Jazz</a>
          <?php else: ?>
          <a class="btn-secondary" href="<?= htmlspecialchars(por_page_path('/po-receiving/asn.php')) ?>?id=<?= $porId ?>">View ASN Data</a>
          <?php endif; ?>
          <?php if (por_can_edit($receipt)): ?>
          <a class="btn-secondary" href="<?= htmlspecialchars(por_page_path('/po-receiving/edit.php')) ?>?id=<?= $porId ?>">Edit</a>
          <?php endif; ?>
          <a class="btn-secondary" href="/po-management/view.php?id=<?= (int) $receipt['POID'] ?>">View PO</a>
          <?php if (por_can_delete()): ?>
          <form method="post" action="<?= htmlspecialchars(por_page_path('/po-receiving/delete.php')) ?>" class="inline-form" onsubmit="return confirm('Delete this receipt record?');">
            <input type="hidden" name="por_id" value="<?= $porId ?>" />
            <button type="submit" class="btn-text btn-text-danger">Delete</button>
          </form>
          <?php endif; ?>
        </div>
      </div>

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
        · <a href="<?= htmlspecialchars(por_page_path('/po-receiving/jazz-asns.php')) ?>">View Jazz ASNs</a>
        · <a href="<?= htmlspecialchars(por_page_path('/po-receiving/asn.php')) ?>?id=<?= $porId ?>">View ASN data</a>
      </div>
      <?php if ($warning !== null && $warning !== ''): ?>
      <div class="admin-notice" role="status"><?= htmlspecialchars($warning) ?></div>
      <?php endif; ?>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
      <?php endif; ?>

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
          <div class="admin-table-wrap por-receiving-table-wrap">
            <table class="admin-table por-receiving-lines-table por-receiving-lines-table--view">
              <thead>
                <tr>
                  <th class="por-sticky-col">LN#</th>
                  <th class="por-sticky-col">SKU</th>
                  <th class="por-sticky-col">Desc</th>
                  <th>QTY ORD</th>
                  <th>QTY SCHED</th>
                  <th>QTY REM</th>
                  <th>LOT#</th>
                  <th>QTY EXP</th>
                  <th>QTY REC</th>
                  <th>CS BC</th>
                  <th>SKU BC</th>
                  <th>COO</th>
                  <th>On HLD</th>
                  <th>Note</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $displayLines = por_prepare_form_lines(array_map(static function (array $line): array {
                      return [
                          'po_line_id'         => (int) $line['POLineID'],
                          'line_number'        => (int) $line['LineNumber'],
                          'item_sku'           => (string) ($line['ItemSKU'] ?? ''),
                          'item_description'   => (string) $line['ItemDescription'],
                          'lot_number'         => (string) ($line['LotNumber'] ?? ''),
                          'quantity_expected'  => por_format_qty($line['QuantityExpected'] ?? null),
                          'quantity_received'  => por_format_qty($line['QuantityReceived'] ?? null),
                          'case_barcode'       => (string) ($line['CaseBarcode'] ?? ''),
                          'sku_barcode'        => (string) ($line['SKUBarcode'] ?? ''),
                          'country_of_origin'  => (string) ($line['CountryOfOrigin'] ?? ''),
                          'on_hold'            => !empty($line['OnHold']),
                          'li_note'            => (string) ($line['LINote'] ?? ''),
                      ];
                  }, $lines), (int) $receipt['POID'], (int) $receipt['PORID']);
                ?>
                <?php foreach ($displayLines as $line): ?>
                <?php $showMeta = !empty($line['show_line_meta']); ?>
                <tr class="<?= $showMeta ? 'por-lot-row' : 'por-lot-row por-lot-continuation' ?>">
                  <td class="por-sticky-col"><?= $showMeta ? (int) ($line['line_number'] ?? 0) : '' ?></td>
                  <td class="por-sticky-col"><?= $showMeta ? htmlspecialchars($line['item_sku'] ?? '—') : '' ?></td>
                  <td class="por-sticky-col"><?= $showMeta ? htmlspecialchars($line['item_description'] ?? '') : '' ?></td>
                  <td><?= $showMeta ? htmlspecialchars($line['quantity_ordered'] ?? '—') : '' ?></td>
                  <td><?= $showMeta ? htmlspecialchars($line['quantity_scheduled'] ?? '0') : '' ?></td>
                  <td><?= $showMeta ? htmlspecialchars($line['quantity_remaining'] ?? '0') : '' ?></td>
                  <td><?= htmlspecialchars(($line['lot_number'] ?? '') !== '' ? $line['lot_number'] : '—') ?></td>
                  <td><?= htmlspecialchars($line['quantity_expected'] ?? '0') ?></td>
                  <td><?= htmlspecialchars($line['quantity_received'] ?? '0') ?></td>
                  <td><?= htmlspecialchars(($line['case_barcode'] ?? '') !== '' ? $line['case_barcode'] : '—') ?></td>
                  <td><?= htmlspecialchars(($line['sku_barcode'] ?? '') !== '' ? $line['sku_barcode'] : '—') ?></td>
                  <td><?= htmlspecialchars(($line['country_of_origin'] ?? '') !== '' ? $line['country_of_origin'] : '—') ?></td>
                  <td><?= !empty($line['on_hold']) ? 'Yes' : 'No' ?></td>
                  <td><?= htmlspecialchars(($line['li_note'] ?? '') !== '' ? $line['li_note'] : '—') ?></td>
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
