<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/delivery-appointment.php';

das_require_update();

$apptId = (int) ($_GET['id'] ?? 0);
$appointment = $apptId > 0 ? das_get($apptId) : null;
$returnContext = das_return_context_from_query();

if ($appointment === null) {
    header('Location: /delivery-scheduling-log/', true, 302);
    exit;
}

$activeSlug = 'delivery-scheduling-log';
$pageContainerClass = 'page-inner--wide';
$contactEmail = trim((string) ($appointment['ContactEmail'] ?? ''));
$canEmail = $contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL);
$emailRecipients = das_scheduling_email_recipients($appointment);
$emailRecipientSummary = das_format_scheduling_email_recipients([
    'to' => array_key_first($emailRecipients['to']),
    'cc' => array_keys($emailRecipients['cc']),
]);
$reminderMessage = (string) ($appointment['AppointmentNotes'] ?? '');
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnContext = das_return_context_from_query();
    $reminderMessage = trim((string) ($_POST['reminder_message'] ?? ''));
    $emailResult = das_send_scheduling_reminder_email($apptId, $reminderMessage);
    $query = http_build_query(array_merge(
        array_filter([
            'return_to'   => $returnContext['return_to'] ?: null,
            'por_id'      => $returnContext['por_id'] > 0 ? $returnContext['por_id'] : null,
            'jazz_asn_id' => $returnContext['jazz_asn_id'] !== '' ? $returnContext['jazz_asn_id'] : null,
        ]),
        $emailResult['ok']
            ? [
                'email_notice' => 'reminder_sent',
                'email_recipients' => das_format_scheduling_email_recipients($emailResult),
            ]
            : ['email_error' => $emailResult['error'] ?? 'Unable to send reminder email.']
    ));
    header('Location: /delivery-scheduling-log/view.php?id=' . $apptId . '&' . $query, true, 303);
    exit;
}

$pageTitle = 'Send Reminder — Appointment #' . $apptId . ' | Delivery Scheduling Log';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner <?= htmlspecialchars($pageContainerClass ?? '') ?>">
      <a class="breadcrumb" href="/delivery-scheduling-log/view.php?id=<?= $apptId ?><?= htmlspecialchars(das_return_query($returnContext)) ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to appointment #<?= $apptId ?>
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Delivery scheduling</div>
          <h1>Send reminder email</h1>
          <p class="page-lead">
            Appointment #<?= $apptId ?> · PO <?= htmlspecialchars($appointment['PONumber']) ?>
            · <?= htmlspecialchars($appointment['CompanyName'] ?? '—') ?>
          </p>
        </div>
        <div class="admin-actions">
          <a class="btn-secondary" href="/delivery-scheduling-log/view.php?id=<?= $apptId ?><?= htmlspecialchars(das_return_query($returnContext)) ?>">View appointment</a>
          <a class="btn-secondary" href="/delivery-scheduling-log/edit.php?id=<?= $apptId ?><?= htmlspecialchars(das_return_query($returnContext)) ?>">Edit appointment</a>
        </div>
      </div>

      <div class="account-card">
        <h2>Email scheduling reminder</h2>
        <p class="account-card-lead">
          Send a reminder to the supplier contact on this record to confirm or propose a delivery appointment.
        </p>
        <?php if ($canEmail): ?>
        <form class="admin-form" method="post" action="/delivery-scheduling-log/send-reminder.php?id=<?= $apptId ?><?= htmlspecialchars(das_return_query($returnContext)) ?>">
          <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnContext['return_to'] ?? '') ?>" />
          <input type="hidden" name="por_id" value="<?= (int) ($returnContext['por_id'] ?? 0) ?>" />
          <input type="hidden" name="jazz_asn_id" value="<?= htmlspecialchars($returnContext['jazz_asn_id'] ?? '') ?>" />
          <div class="form-group">
            <label for="reminder_message">Reminder message</label>
            <textarea class="form-input" id="reminder_message" name="reminder_message" rows="6" placeholder="Add reminder details for the supplier."><?= htmlspecialchars($reminderMessage) ?></textarea>
            <p class="form-hint">Pre-filled from appointment notes. Edit before sending if needed.</p>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary" onclick="return confirm('Send reminder email to <?= htmlspecialchars($emailRecipientSummary) ?>?');">
              Send reminder to <?= htmlspecialchars($emailRecipientSummary) ?>
            </button>
            <a class="btn-secondary" href="/delivery-scheduling-log/view.php?id=<?= $apptId ?><?= htmlspecialchars(das_return_query($returnContext)) ?>">Cancel</a>
          </div>
        </form>
        <?php else: ?>
        <div class="admin-notice" role="status">A valid supplier contact email is required on this appointment before sending a reminder.</div>
        <?php endif; ?>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
