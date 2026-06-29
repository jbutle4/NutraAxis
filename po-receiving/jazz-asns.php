<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/po-receiving.php';
require dirname(__DIR__) . '/includes/po-receiving-asn.php';
require dirname(__DIR__) . '/includes/delivery-appointment.php';

por_require_read();

$activeSlug = $activeSlug ?? 'jazz-asns';
$jazzAsnsPath = data_profile_page_path('/po-receiving/jazz-asns.php');
$configError = jazz_oms_config_error();
$listResult = $configError === null ? jazz_oms_list_asns() : ['ok' => true, 'error' => null, 'rows' => []];
$rows = $listResult['rows'] ?? [];
$columns = jazz_oms_asn_columns($rows);
$asnPreferredOrder = ['id', 'asn_number', 'po_number', 'supplier', 'status', 'expected_date', 'created_at'];
$asnSortColumns = [];
foreach ($asnPreferredOrder as $preferred) {
    if (in_array($preferred, $columns, true)) {
        $asnSortColumns[$preferred] = jazz_oms_asn_column_label($preferred);
    }
}
foreach ($columns as $column) {
    if (!isset($asnSortColumns[$column])) {
        $asnSortColumns[$column] = jazz_oms_asn_column_label($column);
    }
}
$asnDefaultSort = array_key_first($asnSortColumns) ?: 'id';
$listFilters = table_sort_state($asnSortColumns, $asnDefaultSort, 'desc', $_GET);
$asnSortAccessors = [];
foreach (array_keys($asnSortColumns) as $column) {
    $asnSortAccessors[$column] = fn(array $row) => jazz_oms_asn_cell_value($row[$column] ?? null);
}
if ($rows !== [] && $asnSortAccessors !== []) {
    $rows = table_sort_rows($rows, $listFilters, $asnSortAccessors, [], $asnDefaultSort, 'desc');
}
$notice = $_GET['notice'] ?? null;
$transmittedPorId = (int) ($_GET['por_id'] ?? 0);
$transmittedReceipt = $transmittedPorId > 0 ? por_get($transmittedPorId) : null;

$pageTitle = 'Jazz ASNs | PO Receiving';
$pageDescription = 'Advanced shipping notices currently in Jazz OMS.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $jazzAsnsPath,
          'back_label' => 'Back to Jazz ASNs',
          'category'   => 'Inbound',
          'title'      => 'Jazz ASNs',
          'lead'       => 'Advanced shipping notices on file in Jazz OMS.',
          'permission' => permission_label(po_permission_value()),
      ]);
      ?>

      <?php if ($notice === 'transmitted'): ?>
      <div class="admin-notice is-success" role="status">
        ASN transmitted to Jazz successfully.
        <?php if ($transmittedReceipt !== null && !empty($transmittedReceipt['JazzASN'])): ?>
        Jazz ASN number: <strong><?= htmlspecialchars($transmittedReceipt['JazzASN']) ?></strong>
        <?php endif; ?>
        <?php if ($transmittedPorId > 0): ?>
        · <a href="/po-receiving/view.php?id=<?= $transmittedPorId ?>">View receipt</a>
        <?php endif; ?>
      </div>
      <?php if (!empty($_GET['warning'])): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $_GET['warning']) ?></div>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php elseif (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong>Jazz OMS connected</strong>
          <p><?= count($rows) ?> ASN record<?= count($rows) === 1 ? '' : 's' ?> loaded from <?= htmlspecialchars(jazz_oms_base_url() . por_asn_endpoint()) ?> · tenant <?= htmlspecialchars(jazz_oms_tenant_code()) ?></p>
        </div>
      </div>

      <div class="admin-table-wrap production-status-table-wrap">
        <table class="admin-table production-status-table">
          <thead>
            <tr>
              <?php if ($columns === []): ?>
              <th>ASN</th>
              <th>Appointment</th>
              <?php else: ?>
              <?php foreach ($columns as $column): ?>
              <?php if (isset($asnSortColumns[$column])): ?>
              <?php table_sort_render_th(
                  $column,
                  jazz_oms_asn_column_label($column),
                  $jazzAsnsPath,
                  $asnSortColumns,
                  $listFilters,
                  [],
                  [],
                  $asnDefaultSort,
                  'desc'
              ); ?>
              <?php else: ?>
              <th><?= htmlspecialchars(jazz_oms_asn_column_label($column)) ?></th>
              <?php endif; ?>
              <?php endforeach; ?>
              <th>Appointment</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="<?= max(2, count($columns) + 1) ?>">No ASN records returned from Jazz OMS.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <?php foreach ($columns as $column): ?>
              <td>
                <?php if ($column === 'id' && ($row['id'] ?? '') !== '' && ($row['id'] ?? '') !== null): ?>
                <a class="btn-text" href="<?= htmlspecialchars(jazz_oms_asn_detail_url($row['id'])) ?>"><?= htmlspecialchars(jazz_oms_asn_cell_value($row[$column] ?? null)) ?></a>
                <?php else: ?>
                <?= htmlspecialchars(jazz_oms_asn_cell_value($row[$column] ?? null)) ?>
                <?php endif; ?>
              </td>
              <?php endforeach; ?>
              <td>
                <?php if (($row['id'] ?? '') !== '' && ($row['id'] ?? '') !== null): ?>
                <a class="btn-text" href="<?= htmlspecialchars(das_appointment_url_for_jazz_asn((string) $row['id'])) ?>">Schedule</a>
                <?php else: ?>
                —
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
