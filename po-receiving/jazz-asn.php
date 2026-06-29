<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/po-receiving.php';
require dirname(__DIR__) . '/includes/po-receiving-asn.php';
require dirname(__DIR__) . '/includes/delivery-appointment.php';

por_require_read();

$asnId = trim((string) ($_GET['id'] ?? ''));
$jazzAsnsPath = data_profile_page_path('/po-receiving/jazz-asns.php');
if ($asnId === '') {
    header('Location: ' . $jazzAsnsPath, true, 302);
    exit;
}

$activeSlug = $activeSlug ?? 'jazz-asns';
$configError = jazz_oms_config_error();
$result = $configError === null ? jazz_oms_get_asn($asnId) : ['ok' => false, 'error' => $configError, 'row' => null];
$asn = $result['row'] ?? null;
$details = is_array($asn['detail'] ?? null) ? $asn['detail'] : (is_array($asn['details'] ?? null) ? $asn['details'] : []);
$detailColumns = jazz_oms_asn_detail_columns($details);
$headerFields = is_array($asn) ? jazz_oms_asn_header_fields($asn) : [];

$pageTitle = 'Jazz ASN ' . $asnId . ' | PO Receiving';
$pageDescription = 'Advanced shipping notice detail from Jazz OMS.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php if ($configError !== null): ?>
      <?php
      render_list_page_header([
          'back_href'  => $jazzAsnsPath,
          'back_label' => 'Back to Jazz ASNs',
          'category'   => 'Inbound',
          'title'      => 'Jazz ASN ' . $asnId,
      ]);
      ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>

      <?php elseif (!$result['ok'] || $asn === null): ?>
      <?php
      render_list_page_header([
          'back_href'  => $jazzAsnsPath,
          'back_label' => 'Back to Jazz ASNs',
          'category'   => 'Inbound',
          'title'      => 'Jazz ASN ' . $asnId,
      ]);
      ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error'] ?? 'ASN not found.') ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="<?= htmlspecialchars($jazzAsnsPath) ?>">Back to Jazz ASNs</a>
      </div>

      <?php else: ?>
      <?php
      $dasUrl = das_appointment_url_for_jazz_asn($asnId);
      $listToolbar = '<a class="btn-secondary" href="' . htmlspecialchars($dasUrl) . '">Delivery appointment</a>';
      $jazzAsnLead = '';
      if (!empty($asn['status'])) {
          $jazzAsnLead .= '<span class="status-badge status-received">' . htmlspecialchars((string) $asn['status']) . '</span> · ';
      }
      $jazzAsnLead .= 'PO ' . htmlspecialchars((string) ($asn['po_number'] ?? '—')) . ' · Shipment ' . htmlspecialchars((string) ($asn['shipment_number'] ?? '—'));
      render_list_page_header([
          'back_href'  => $jazzAsnsPath,
          'back_label' => 'Back to Jazz ASNs',
          'category'   => 'Inbound',
          'title'      => 'Jazz ASN ' . ($asn['id'] ?? $asnId),
          'lead'       => $jazzAsnLead,
          'lead_html'  => true,
      ]);
      ?>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="detail-grid detail-grid-stacked">
        <section class="detail-card">
          <h2>ASN header</h2>
          <dl class="detail-list">
            <?php foreach ($headerFields as $field => $value): ?>
            <div>
              <dt><?= htmlspecialchars(jazz_oms_asn_column_label((string) $field)) ?></dt>
              <dd><?= htmlspecialchars(jazz_oms_asn_format_field_value($value)) ?></dd>
            </div>
            <?php endforeach; ?>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Line detail</h2>
          <p class="account-card-lead"><?= count($details) ?> line<?= count($details) === 1 ? '' : 's' ?> on this ASN.</p>

          <?php if ($details === []): ?>
          <p class="account-card-lead">No detail lines returned from Jazz OMS.</p>
          <?php else: ?>
          <div class="admin-table-wrap production-status-table-wrap">
            <table class="admin-table production-status-table">
              <thead>
                <tr>
                  <?php foreach ($detailColumns as $column): ?>
                  <th><?= htmlspecialchars(jazz_oms_asn_column_label($column)) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($details as $line): ?>
                <?php if (!is_array($line)) continue; ?>
                <tr>
                  <?php foreach ($detailColumns as $column): ?>
                  <td><?= htmlspecialchars(jazz_oms_asn_format_field_value($line[$column] ?? null)) ?></td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </section>
      </div>

      <div class="module-actions">
        <a class="btn-secondary" href="<?= htmlspecialchars($jazzAsnsPath) ?>">Back to Jazz ASNs</a>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
