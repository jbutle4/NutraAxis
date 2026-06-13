<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/delivery-appointment.php';

das_require_read();

$apptId = (int) ($_GET['id'] ?? 0);
$appointment = $apptId > 0 ? das_get($apptId) : null;
$returnContext = das_return_context_from_query();
$breadcrumb = das_breadcrumb($returnContext);

if ($appointment === null) {
    header('Location: /delivery-scheduling-log/', true, 302);
    exit;
}

$activeSlug = 'delivery-scheduling-log';
$pageContainerClass = 'page-inner--wide';
$emailNotice = $_GET['email_notice'] ?? null;
$emailError = isset($_GET['email_error']) ? (string) $_GET['email_error'] : null;
$emailRecipients = isset($_GET['email_recipients']) ? (string) $_GET['email_recipients'] : '';
$asnContext = das_view_asn_context($appointment);

$pageTitle = 'Appointment #' . $apptId . ' | Delivery Scheduling Log';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner <?= htmlspecialchars($pageContainerClass ?? '') ?>">
      <a class="breadcrumb" href="<?= htmlspecialchars($breadcrumb['href']) ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        <?= htmlspecialchars($breadcrumb['label']) ?>
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Delivery scheduling</div>
          <h1>Appointment #<?= $apptId ?></h1>
          <p class="page-lead">
            <span class="status-badge <?= das_status_class((string) $appointment['AppointmentStatus']) ?>"><?= htmlspecialchars($appointment['AppointmentStatus']) ?></span>
            · PO <?= htmlspecialchars($appointment['PONumber']) ?>
            · <?= htmlspecialchars($appointment['CompanyName'] ?? '—') ?>
          </p>
        </div>
        <div class="admin-actions">
          <?php if (das_can_update()): ?>
          <a class="btn-primary" href="/delivery-scheduling-log/edit.php?id=<?= $apptId ?><?= htmlspecialchars(das_return_query($returnContext)) ?>">Edit</a>
          <a class="btn-secondary" href="<?= htmlspecialchars(das_send_reminder_url($apptId, $returnContext)) ?>">Send reminder</a>
          <?php endif; ?>
          <?php if (das_record_int($appointment, 'POReceiptID') > 0): ?>
          <a class="btn-secondary" href="/po-receiving/view.php?id=<?= das_record_int($appointment, 'POReceiptID') ?>">PO Receipt</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($emailNotice === 'sent'): ?>
      <div class="admin-notice is-success" role="status">Scheduling request email sent<?= $emailRecipients !== '' ? ' to ' . htmlspecialchars($emailRecipients) : ' successfully' ?>.</div>
      <?php elseif ($emailNotice === 'reminder_sent'): ?>
      <div class="admin-notice is-success" role="status">Reminder email sent<?= $emailRecipients !== '' ? ' to ' . htmlspecialchars($emailRecipients) : ' successfully' ?>.</div>
      <?php endif; ?>
      <?php if ($emailError !== null && $emailError !== ''): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($emailError) ?></div>
      <?php endif; ?>

      <div class="detail-grid detail-grid-stacked">
        <section class="detail-card">
          <h2>Appointment details</h2>
          <dl class="detail-list">
            <div><dt>PO number</dt><dd><a href="/po-management/view.php?id=<?= das_record_int($appointment, 'POID') ?>"><?= htmlspecialchars($appointment['PONumber']) ?></a></dd></div>
            <div><dt>PO receipt</dt><dd>
              <?php if (das_record_int($appointment, 'POReceiptID') > 0): ?>
              <a href="/po-receiving/view.php?id=<?= das_record_int($appointment, 'POReceiptID') ?>">Receipt #<?= das_record_int($appointment, 'POReceiptID') ?></a>
              <?php else: ?>
              —
              <?php endif; ?>
            </dd></div>
            <div><dt>Appointment date/time</dt><dd><?= htmlspecialchars(das_format_datetime($appointment['AppointmentDateTime'] ?? null)) ?></dd></div>
            <div><dt>Appointment company</dt><dd><?= htmlspecialchars($appointment['AppointmentCompanyName'] ?? '—') ?></dd></div>
            <div><dt>Appointment address</dt><dd><?= htmlspecialchars($appointment['AppointmentAddress'] ?? '—') ?></dd></div>
            <div><dt>ASN created</dt><dd><?= !empty($appointment['AppointmentASNCreated']) ? 'Yes' : 'No' ?></dd></div>
            <div><dt>ASN number</dt><dd><?= htmlspecialchars((string) (das_record_value($appointment, 'AppointmentASNNumber') ?? '—')) ?></dd></div>
            <div><dt>Created</dt><dd><?= htmlspecialchars(das_format_datetime($appointment['CreateDate'] ?? null)) ?><?= !empty($appointment['CreatedBy']) ? ' by ' . htmlspecialchars($appointment['CreatedBy']) : '' ?></dd></div>
            <div><dt>Last modified</dt><dd><?= htmlspecialchars(das_format_datetime($appointment['ModifiedDate'] ?? null)) ?><?= !empty($appointment['ModifiedBy']) ? ' by ' . htmlspecialchars($appointment['ModifiedBy']) : '' ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Receiving company contact</h2>
          <dl class="detail-list">
            <div><dt>Contact</dt><dd><?= htmlspecialchars((string) (das_record_value($appointment, 'ReceivingCompanyContact') ?? '—')) ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars((string) (das_record_value($appointment, 'ReceivingCompanyEmail') ?? '—')) ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars((string) (das_record_value($appointment, 'ReceivingCompanyPhone') ?? '—')) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Supplier contact</h2>
          <dl class="detail-list">
            <div><dt>Company</dt><dd><?= htmlspecialchars($appointment['CompanyName'] ?? '—') ?></dd></div>
            <div><dt>Contact name</dt><dd><?= htmlspecialchars($appointment['ContactName'] ?? '—') ?></dd></div>
            <div><dt>Contact email</dt><dd><?= htmlspecialchars($appointment['ContactEmail'] ?? '—') ?></dd></div>
            <div><dt>Contact phone</dt><dd><?= htmlspecialchars($appointment['ContactPhone'] ?? '—') ?></dd></div>
          </dl>
        </section>

        <?php if (($appointment['AppointmentNotes'] ?? '') !== ''): ?>
        <section class="detail-card">
          <h2>Notes</h2>
          <p><?= nl2br(htmlspecialchars($appointment['AppointmentNotes'])) ?></p>
        </section>
        <?php endif; ?>

        <?php if ($asnContext !== null): ?>
        <?php require dirname(__DIR__) . '/includes/delivery-appointment-asn-detail.php'; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
