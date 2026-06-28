<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/sales-reporting.php';
require dirname(__DIR__) . '/includes/jazz-oms.php';

sales_reporting_require_read();

$activeSlug = $activeSlug ?? 'jazz-order-report';
$reportListPath = data_profile_page_path('/sales-reporting/jazz-order-report/');
$orderNumber = trim($_GET['order'] ?? '');
$configError = jazz_oms_config_error();
$order = null;
$error = $configError;
$contact = [];
$shipToContact = [];
$accsOrderNumber = null;
$lineItems = [];
$itemSortColumns = [
    'line_number'   => 'Line',
    'sku'           => 'SKU',
    'qty_ordered'   => 'Qty ordered',
    'qty_allocated' => 'Allocated',
    'qty_shipped'   => 'Shipped',
    'current_price' => 'Unit price',
];
$itemSortFilters = table_sort_state($itemSortColumns, 'line_number', 'asc', $_GET);

if ($error === null) {
    if ($orderNumber === '') {
        header('Location: ' . $reportListPath, true, 302);
        exit;
    }

    $result = jazz_oms_get_order($orderNumber);
    if ($result['ok']) {
        $order = $result['row'];
        $contacts = jazz_oms_order_resolve_contacts($order);
        $contact = $contacts['customer'];
        $shipToContact = $contacts['ship_to'];
        $accsOrderNumber = $contacts['accs_order_number'];
        $itemSortAccessors = [
            'line_number'   => fn(array $item) => $item['line_number'] ?? 0,
            'sku'           => fn(array $item): string => (string) ($item['sku'] ?? $item['sku_code'] ?? ''),
            'qty_ordered'   => fn(array $item) => $item['qty_ordered'] ?? $item['quantity'] ?? $item['ordered'] ?? 0,
            'qty_allocated' => fn(array $item) => $item['qty_allocated'] ?? $item['allocated'] ?? 0,
            'qty_shipped'   => fn(array $item) => $item['qty_shipped'] ?? $item['shipped'] ?? 0,
            'current_price' => fn(array $item) => $item['current_price'] ?? $item['price'] ?? $item['unit_price'] ?? 0,
        ];
        $lineItems = jazz_oms_order_line_items($order);
        if ($lineItems !== []) {
            $lineItems = table_sort_rows(
                $lineItems,
                $itemSortFilters,
                $itemSortAccessors,
                ['line_number', 'qty_ordered', 'qty_allocated', 'qty_shipped', 'current_price'],
                'line_number',
                'asc'
            );
        }
    } else {
        $error = $result['error'];
    }
}

$pageTitle = ($orderNumber !== '' ? 'Order ' . $orderNumber : 'Order Detail') . ' | Jazz Order Report';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';

$displayValue = static function (?string $value): string {
    $value = trim((string) $value);
    return $value !== '' ? $value : '—';
};
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      $jazzLead = '';
      if ($order !== null) {
          $jazzLead = (string) ($order['status'] ?? '');
          if (!empty($order['order_date'])) {
              $jazzLead .= ($jazzLead !== '' ? ' · ' : '') . substr((string) $order['order_date'], 0, 19);
          }
          if (!empty($order['source_code'])) {
              $jazzLead .= ' · Source ' . (string) $order['source_code'];
          }
      }
      render_list_page_header([
          'back_href'  => $reportListPath,
          'back_label' => 'Back to Jazz Order Report',
          'category'   => 'Jazz OMS',
          'title'      => $order !== null ? 'Order #' . ($order['order_number'] ?? $orderNumber) : 'Order Detail',
          'lead'       => $jazzLead,
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="<?= htmlspecialchars($reportListPath) ?>">Back to Orders</a>
      </div>
      <?php elseif ($order !== null): ?>

      <?php if ($accsOrderNumber !== null): ?>
      <div class="admin-notice" role="status">
        Customer and ship-to details loaded from ACCS order #<?= htmlspecialchars($accsOrderNumber) ?> when Jazz order status does not include address fields.
      </div>
      <?php endif; ?>

      <div class="status-banner">
        <div>
          <strong>Quantity summary</strong>
          <p>
            Ordered <?= jazz_oms_order_item_qty($order) ?>
            <?php if (isset($order['qty_allocated']) || isset($order['allocated'])): ?>
            · Allocated <?= (int) ($order['qty_allocated'] ?? $order['allocated'] ?? 0) ?>
            <?php endif; ?>
            <?php if (isset($order['qty_printed']) || isset($order['printed'])): ?>
            · Printed <?= (int) ($order['qty_printed'] ?? $order['printed'] ?? 0) ?>
            <?php endif; ?>
            <?php if (isset($order['qty_shipped']) || isset($order['shipped'])): ?>
            · Shipped <?= (int) ($order['qty_shipped'] ?? $order['shipped'] ?? 0) ?>
            <?php endif; ?>
          </p>
        </div>
      </div>

      <div class="detail-grid detail-grid-order-contacts">
        <section class="detail-card">
          <h2>Order</h2>
          <dl class="detail-list detail-list-inline">
            <div><dt>Order #</dt><dd><?= htmlspecialchars((string) ($order['order_number'] ?? '')) ?></dd></div>
            <div><dt>PO #</dt><dd><?= htmlspecialchars($displayValue(jazz_oms_order_field($order, ['po_number']))) ?></dd></div>
            <div><dt>Status</dt><dd><?= htmlspecialchars($displayValue(jazz_oms_order_field($order, ['status']))) ?></dd></div>
            <div><dt>Order date</dt><dd><?= htmlspecialchars(jazz_oms_format_cell($order['order_date'] ?? null)) ?></dd></div>
            <div><dt>Ship date</dt><dd><?= htmlspecialchars(jazz_oms_format_cell($order['ship_date'] ?? $order['shipped_at'] ?? null)) ?></dd></div>
            <div><dt>Pack date</dt><dd><?= htmlspecialchars(jazz_oms_format_cell($order['pack_date'] ?? null)) ?></dd></div>
            <div><dt>Customer #</dt><dd><?= htmlspecialchars($displayValue($contact['customer_number'] ?? '')) ?></dd></div>
            <div><dt>Source</dt><dd><?= htmlspecialchars($displayValue(jazz_oms_order_field($order, ['source_code']))) ?></dd></div>
            <div><dt>Offer</dt><dd><?= htmlspecialchars($displayValue(jazz_oms_order_field($order, ['offer_code', 'offer']))) ?></dd></div>
            <div><dt>Business type</dt><dd><?= htmlspecialchars($displayValue(jazz_oms_order_field($order, ['business_type_code', 'business_type']))) ?></dd></div>
            <div><dt>Created by</dt><dd><?= htmlspecialchars($displayValue(jazz_oms_order_field($order, ['created_by']))) ?></dd></div>
          </dl>
        </section>
        <div class="detail-grid-stack">
        <section class="detail-card">
          <h2>Customer</h2>
          <dl class="detail-list detail-list-inline">
            <div><dt>Name</dt><dd><?= htmlspecialchars($displayValue($contact['name'] ?? '')) ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars($displayValue($contact['email'] ?? '')) ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars($displayValue($contact['phone'] ?? '')) ?></dd></div>
            <div><dt>Company</dt><dd><?= htmlspecialchars($displayValue($contact['company'] ?? '')) ?></dd></div>
          </dl>
        </section>
        <section class="detail-card">
          <h2>Ship To</h2>
          <dl class="detail-list detail-list-inline">
            <div><dt>Name</dt><dd><?= htmlspecialchars($displayValue($shipToContact['name'] ?? '')) ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars($displayValue($shipToContact['email'] ?? '')) ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars($displayValue($shipToContact['phone'] ?? '')) ?></dd></div>
            <div><dt>Company</dt><dd><?= htmlspecialchars($displayValue($shipToContact['company'] ?? '')) ?></dd></div>
            <div><dt>Address</dt><dd><?= htmlspecialchars($displayValue(trim(($shipToContact['address1'] ?? '') . ' ' . ($shipToContact['address2'] ?? '')))) ?></dd></div>
            <div><dt>City</dt><dd><?= htmlspecialchars($displayValue($shipToContact['city'] ?? '')) ?></dd></div>
            <div><dt>State</dt><dd><?= htmlspecialchars($displayValue($shipToContact['state'] ?? '')) ?></dd></div>
            <div><dt>Zip</dt><dd><?= htmlspecialchars($displayValue($shipToContact['zip'] ?? '')) ?></dd></div>
          </dl>
        </section>
        </div>
      </div>

      <?php if ($lineItems !== []): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                $itemSortColumns,
                data_profile_page_path('/sales-reporting/jazz-order.php') . '?order=' . rawurlencode($orderNumber),
                $itemSortFilters,
                [],
                ['line_number', 'qty_ordered', 'qty_allocated', 'qty_shipped', 'current_price'],
                'line_number',
                'asc'
            ); ?>
          </thead>
          <tbody>
            <?php foreach ($lineItems as $item): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($item['line_number'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($item['sku'] ?? $item['sku_code'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($item['qty_ordered'] ?? $item['quantity'] ?? $item['ordered'] ?? '0')) ?></td>
              <td><?= htmlspecialchars((string) ($item['qty_allocated'] ?? $item['allocated'] ?? '0')) ?></td>
              <td><?= htmlspecialchars((string) ($item['qty_shipped'] ?? $item['shipped'] ?? '0')) ?></td>
              <td><?= htmlspecialchars((string) ($item['current_price'] ?? $item['unit_price'] ?? $item['price'] ?? '0')) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="admin-notice" role="status">No line items were returned for this order. Jazz may require a separate detail request for SKU-level data.</div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
