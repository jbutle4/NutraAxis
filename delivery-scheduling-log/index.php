<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/delivery-appointment.php';

das_require_read();

$activeSlug = 'delivery-scheduling-log';
$pageContainerClass = 'page-inner--wide';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'status' => $statusFilter !== '' ? $statusFilter : null,
    'q'      => $search !== '' ? $search : null,
] + table_sort_state(DAS_LIST_SORT_COLUMNS, 'appointment', 'desc', $_GET);
$appointments = das_list($listFilters);
$notice = $_GET['notice'] ?? null;
$emailNotice = $_GET['email_notice'] ?? null;
$emailError = isset($_GET['email_error']) ? (string) $_GET['email_error'] : null;

$pageTitle = 'Delivery Scheduling Log | Inbound & Receiving';
$pageDescription = 'Track inbound delivery appointments and scheduling updates.';
$hubBack = app_module_hub_back_link($activeSlug);

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner <?= htmlspecialchars($pageContainerClass ?? '') ?>">
      <?php
      $listToolbar = das_can_create() ? '<a class="btn-primary" href="/delivery-scheduling-log/new.php">New appointment</a>' : '';
      render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Inbound',
          'title'      => 'Delivery Scheduling Log',
          'lead'       => 'Track inbound delivery appointments, carrier updates, and warehouse scheduling for purchase order receipts.',
          'permission' => auth_module_permission_label('delivery-scheduling-log'),
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Appointment created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Appointment updated successfully.</div>
      <?php elseif ($emailNotice === 'sent'): ?>
      <div class="admin-notice is-success" role="status">Scheduling request email sent successfully.</div>
      <?php endif; ?>

      <?php if ($emailError !== null && $emailError !== ''): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($emailError) ?></div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/delivery-scheduling-log/">
        <?php table_sort_hidden_inputs($listFilters, 'appointment', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (DAS_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="PO number, supplier, contact, ASN, or address" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/delivery-scheduling-log/">Clear</a>
        </div>
      </form>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                DAS_LIST_SORT_COLUMNS,
                '/delivery-scheduling-log',
                $listFilters,
                ['status', 'q'],
                [],
                'appointment',
                'desc',
                'appointment',
                table_actions_header(das_can_update() ? ['View', 'Edit'] : ['View'])
            ); ?>
          </thead>
          <tbody>
            <?php if ($appointments === []): ?>
            <tr><td colspan="7">No appointments match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($appointments as $appointment): ?>
            <tr>
              <td><?= htmlspecialchars(das_format_datetime($appointment['AppointmentDateTime'] ?? null)) ?></td>
              <td><a class="btn-text" href="/po-management/view.php?id=<?= (int) $appointment['POID'] ?>"><?= htmlspecialchars($appointment['PONumber']) ?></a></td>
              <td><?= htmlspecialchars($appointment['CompanyName'] ?? '—') ?></td>
              <td>
                <?= htmlspecialchars($appointment['ContactName'] ?? '—') ?>
                <?php if (!empty($appointment['ContactEmail'])): ?>
                <br /><span class="admin-meta"><?= htmlspecialchars($appointment['ContactEmail']) ?></span>
                <?php endif; ?>
              </td>
              <td><span class="status-badge <?= das_status_class((string) $appointment['AppointmentStatus']) ?>"><?= htmlspecialchars($appointment['AppointmentStatus']) ?></span></td>
              <td><?= htmlspecialchars($appointment['AppointmentASNNumber'] ?? '—') ?></td>
              <?php
              $actions = [
                  ['href' => '/delivery-scheduling-log/view.php?id=' . (int) $appointment['ApptID'], 'label' => 'View'],
              ];
              if (das_can_update()) {
                  $actions[] = ['href' => '/delivery-scheduling-log/edit.php?id=' . (int) $appointment['ApptID'], 'label' => 'Edit'];
              }
              table_actions_cell($actions);
              ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
