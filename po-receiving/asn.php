<?php
/**
 * PO receipt ASN preview. Jazz transmit uses GET (POST returns nginx 404 on Azure).
 */
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-receiving.php';
require dirname(__DIR__) . '/includes/po-receiving-asn.php';
require dirname(__DIR__) . '/includes/delivery-appointment.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$porId = (int) ($_GET['id'] ?? 0);

if (($_GET['transmit'] ?? '') === '1') {
    por_require_update();

    $token = (string) ($_GET['token'] ?? '');
    if ($porId <= 0 || $token === '' || !hash_equals(por_transmit_token($porId), $token)) {
        header('Location: /po-receiving/asn.php?id=' . $porId . '&v=20260611&error=' . rawurlencode('Invalid transmit request. Refresh the page and try again.'), true, 302);
        exit;
    }

    set_time_limit(120);

    try {
        $result = por_transmit_to_jazz($porId);
    } catch (Throwable $e) {
        error_log('PO receipt ASN transmit failed for POR ' . $porId . ': ' . $e->getMessage());
        $detail = trim($e->getMessage());
        if ($detail === '') {
            $detail = 'Transmit failed unexpectedly. Please try again or contact support.';
        } else {
            $detail = 'Transmit failed: ' . $detail;
        }
        header(
            'Location: /po-receiving/asn.php?id=' . $porId . '&v=20260611&error=' . rawurlencode($detail),
            true,
            303
        );
        exit;
    }

    if ($result['ok']) {
        $params = ['notice' => 'transmitted', 'v' => '20260611'];
        if (isset($result['warning']) && $result['warning'] !== '') {
            $params['warning'] = (string) $result['warning'];
        }
        header('Location: /po-receiving/asn.php?id=' . $porId . '&' . http_build_query($params), true, 303);
        exit;
    }

    header(
        'Location: /po-receiving/asn.php?id=' . $porId . '&v=20260611&error=' . rawurlencode((string) ($result['error'] ?? 'Unable to transmit ASN to Jazz.')),
        true,
        303
    );
    exit;
}

por_require_read();

$notice = $_GET['notice'] ?? null;
$error = isset($_GET['error']) ? (string) $_GET['error'] : null;
$warning = isset($_GET['warning']) ? (string) $_GET['warning'] : null;

$receipt = $porId > 0 ? por_get($porId) : null;

if ($receipt === null) {
    header('Location: /po-receiving/', true, 302);
    exit;
}

$lines = por_get_lines($porId);
$asnRows = por_asn_rows($receipt, $lines);

if (($_GET['format'] ?? '') === 'csv') {
    $csv = por_asn_csv($asnRows);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asn-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $receipt['PONumber']) . '-' . $porId . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

$activeSlug = 'po-receiving';
$canTransmit = por_can_transmit($receipt);
$jazzConfigError = jazz_oms_config_error();

$pageTitle = $receipt['PONumber'] . ' ASN | PO Receiving';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/po-receiving/view.php?id=<?= $porId ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Receipt
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">PO Receipt · ASN</div>
          <h1>ASN Data — <?= htmlspecialchars($receipt['PONumber']) ?></h1>
          <p class="page-lead">
            <span class="status-badge <?= por_status_class($receipt['PORStatus']) ?>"><?= htmlspecialchars($receipt['PORStatus']) ?></span>
            · <?= htmlspecialchars($receipt['SupplierName']) ?>
            <?php if (!empty($receipt['JazzASN'])): ?>
            · Jazz ASN: <strong><?= htmlspecialchars($receipt['JazzASN']) ?></strong>
            <?php endif; ?>
          </p>
        </div>
        <div class="admin-actions">
          <?php
          $dasUrl = das_appointment_url_for_por($porId, ['return_to' => 'asn', 'por_id' => $porId]);
          ?>
          <a class="btn-secondary" href="<?= htmlspecialchars($dasUrl) ?>">Delivery appointment</a>
          <a class="btn-secondary" href="/po-receiving/jazz-asns.php">Jazz ASNs</a>
          <a class="btn-secondary" href="/po-receiving/asn.php?id=<?= $porId ?>&format=csv">Download CSV</a>
          <?php if ($canTransmit): ?>
          <a
            class="btn-primary"
            href="<?= htmlspecialchars(por_transmit_url($porId)) ?>"
            onclick="return confirm('Transmit this ASN to Jazz?');"
            <?= $jazzConfigError !== null ? 'aria-disabled="true" tabindex="-1" style="pointer-events:none;opacity:.55"' : '' ?>
          >Transmit to Jazz</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php elseif ($notice === 'transmitted'): ?>
      <div class="admin-notice is-success" role="status">
        ASN transmitted to Jazz successfully.
        <?php if (!empty($receipt['JazzASN'])): ?>
        Jazz ASN number: <strong><?= htmlspecialchars($receipt['JazzASN']) ?></strong>
        <?php endif; ?>
        · <a href="/po-receiving/jazz-asns.php">View Jazz ASNs</a>
        · <a href="/po-receiving/view.php?id=<?= $porId ?>">View receipt</a>
      </div>
      <?php if ($warning !== null && $warning !== ''): ?>
      <div class="admin-notice" role="status"><?= htmlspecialchars($warning) ?></div>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($canTransmit && $jazzConfigError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($jazzConfigError) ?></div>
      <?php elseif (!$canTransmit): ?>
      <div class="admin-notice" role="status">
        This receipt is in <strong><?= htmlspecialchars($receipt['PORStatus']) ?></strong> status and cannot be transmitted.
        Only Draft or Scheduled receipts can be sent to Jazz.
      </div>
      <?php endif; ?>

      <div class="account-card">
        <h2>ASN preview</h2>
        <p class="account-card-lead">
          Data below follows the Jazz ASN field mapping. One row per receipt line with expected quantity; header fields repeat on every row.
          Lines with zero expected quantity are omitted. Transmitting sends this data to Jazz and stores the returned Jazz ASN number on the receipt.
        </p>

        <div class="admin-table-wrap production-status-table-wrap">
          <table class="admin-table production-status-table">
            <thead>
              <tr>
                <?php foreach (POR_ASN_COLUMNS as $column): ?>
                <th><?= htmlspecialchars($column) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php if ($asnRows === []): ?>
              <tr><td colspan="<?= count(POR_ASN_COLUMNS) ?>">No line items with expected quantity on this receipt.</td></tr>
              <?php else: ?>
              <?php foreach ($asnRows as $row): ?>
              <tr>
                <?php foreach (POR_ASN_COLUMNS as $column): ?>
                <?php
                  $cell = $row[$column] ?? '';
                  if ($column === 'Quantity' && $cell !== '') {
                      $cell = por_format_qty($cell);
                  }
                ?>
                <td><?= htmlspecialchars($cell !== '' ? $cell : '—') ?></td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
