<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/delivery-appointment.php';

das_require_read();

$activeSlug = 'delivery-scheduling-log';
$pageContainerClass = 'page-inner--wide';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$appointments = das_list([
    'status' => $statusFilter !== '' ? $statusFilter : null,
    'q'      => $search !== '' ? $search : null,
]);
$notice = $_GET['notice'] ?? null;
$emailNotice = $_GET['email_notice'] ?? null;
$emailError = isset($_GET['email_error']) ? (string) $_GET['email_error'] : null;

$pageTitle = 'Delivery Scheduling Log | Supply Chain Management';
$pageDescription = 'Track inbound delivery appointments and scheduling updates.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner <?= htmlspecialchars($pageContainerClass ?? '') ?>">
      <a class="breadcrumb" href="/inventory-management/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Supply Chain Management
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Supply Chain</div>
          <h1>Delivery Scheduling Log</h1>
          <p class="page-lead">Track inbound delivery appointments, carrier updates, and warehouse scheduling for purchase order receipts.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(auth_module_permission_label('delivery-scheduling-log')) ?></p>
        </div>
        <?php if (das_can_create()): ?>
        <a class="btn-primary" href="/delivery-scheduling-log/new.php">New appointment</a>
        <?php endif; ?>
      </div>

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

      <form class="po-filter audit-filter" method="get" action="/delivery-scheduling-log/">
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

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Appointment</th>
              <th>PO number</th>
              <th>Supplier</th>
              <th>Contact</th>
              <th>Status</th>
              <th>ASN #</th>
              <th><?= htmlspecialchars(table_actions_header(das_can_update() ? ['View', 'Edit'] : ['View'])) ?></th>
            </tr>
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
