<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/delivery-appointment.php';

das_require_update();

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
$error = null;
$emailError = null;
$form = das_to_form($appointment);
$poOptions = das_po_options();
$porOptions = das_por_options();
$supplierOptions = das_supplier_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'save'));
    $returnContext = das_return_context_from_query();

    if ($action === 'email_request') {
        $extraMessage = trim((string) ($_POST['email_message'] ?? ''));
        $emailResult = das_send_scheduling_request_email($apptId, $extraMessage);
        $query = http_build_query(array_merge(
            array_filter([
                'return_to'   => $returnContext['return_to'] ?: null,
                'por_id'      => $returnContext['por_id'] > 0 ? $returnContext['por_id'] : null,
                'jazz_asn_id' => $returnContext['jazz_asn_id'] !== '' ? $returnContext['jazz_asn_id'] : null,
            ]),
            $emailResult['ok']
                ? [
                    'email_notice' => 'sent',
                    'email_recipients' => das_format_scheduling_email_recipients($emailResult),
                ]
                : ['email_error' => $emailResult['error'] ?? 'Unable to send email.']
        ));
        header('Location: /delivery-scheduling-log/edit.php?id=' . $apptId . '&' . $query, true, 303);
        exit;
    }

    $form = array_merge($form, das_from_input($_POST));
    $result = das_save($_POST, $apptId);

    if ($result['ok']) {
        $query = http_build_query(array_filter([
            'notice'      => 'updated',
            'return_to'   => $returnContext['return_to'] ?: null,
            'por_id'      => $returnContext['por_id'] > 0 ? $returnContext['por_id'] : null,
            'jazz_asn_id' => $returnContext['jazz_asn_id'] !== '' ? $returnContext['jazz_asn_id'] : null,
        ]));
        header('Location: /delivery-scheduling-log/edit.php?id=' . $apptId . '&' . $query, true, 303);
        exit;
    }

    $error = $result['error'];
}

$notice = $_GET['notice'] ?? null;
$emailNotice = $_GET['email_notice'] ?? null;
$emailError = isset($_GET['email_error']) ? (string) $_GET['email_error'] : null;
$canEmail = trim((string) ($form['contact_email'] ?? '')) !== '' && filter_var($form['contact_email'], FILTER_VALIDATE_EMAIL);
$emailRecipients = das_scheduling_email_recipients($appointment);
$emailRecipientSummary = das_format_scheduling_email_recipients([
    'to' => array_key_first($emailRecipients['to']),
    'cc' => array_keys($emailRecipients['cc']),
]);

$pageTitle = 'Edit Appointment #' . $apptId . ' | Delivery Scheduling Log';

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
          <h1>Edit appointment #<?= $apptId ?></h1>
          <p class="page-lead">PO <?= htmlspecialchars($appointment['PONumber']) ?> · <?= htmlspecialchars($appointment['CompanyName'] ?? '—') ?></p>
        </div>
        <div class="admin-actions">
          <a class="btn-secondary" href="/delivery-scheduling-log/view.php?id=<?= $apptId ?><?= htmlspecialchars(das_return_query($returnContext)) ?>">View</a>
        </div>
      </div>

      <?php if ($notice === 'updated' || $notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Appointment <?= $notice === 'created' ? 'created' : 'updated' ?> successfully.</div>
      <?php endif; ?>
      <?php if ($emailNotice === 'sent'): ?>
      <div class="admin-notice is-success" role="status">Scheduling request email sent to <?= htmlspecialchars((string) ($_GET['email_recipients'] ?? $form['contact_email'])) ?>.</div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php elseif ($emailError !== null && $emailError !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($emailError) ?></div>
      <?php endif; ?>

      <div class="account-card">
        <h2>Email scheduling request</h2>
        <p class="account-card-lead">
          Send a delivery appointment scheduling request to the supplier contact on this record.
        </p>
        <?php if ($canEmail): ?>
        <form class="admin-form" method="post" action="/delivery-scheduling-log/edit.php?id=<?= $apptId ?><?= htmlspecialchars(das_return_query($returnContext)) ?>">
          <input type="hidden" name="action" value="email_request" />
          <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnContext['return_to'] ?? '') ?>" />
          <input type="hidden" name="por_id" value="<?= (int) ($returnContext['por_id'] ?? 0) ?>" />
          <input type="hidden" name="jazz_asn_id" value="<?= htmlspecialchars($returnContext['jazz_asn_id'] ?? '') ?>" />
          <div class="form-group">
            <label for="email_message">Additional message (optional)</label>
            <textarea class="form-input" id="email_message" name="email_message" rows="4" placeholder="Add any special instructions for the supplier."></textarea>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary" onclick="return confirm('Send scheduling request to <?= htmlspecialchars($emailRecipientSummary) ?>?');">
              Email request to <?= htmlspecialchars($emailRecipientSummary) ?>
            </button>
          </div>
        </form>
        <?php else: ?>
        <div class="admin-notice" role="status">Enter a valid contact email below before sending a scheduling request.</div>
        <?php endif; ?>
      </div>

      <?php
        $isEdit = true;
        $formAction = '/delivery-scheduling-log/edit.php?id=' . $apptId . das_return_query($returnContext);
        require dirname(__DIR__) . '/includes/delivery-appointment-form.php';
      ?>

      <?php das_render_asn_detail_panel(array_merge($appointment, $form), false); ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
