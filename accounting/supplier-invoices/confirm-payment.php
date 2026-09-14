<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
accounting_bind_qbo_environment();
require dirname(__DIR__, 2) . '/includes/admin.php';
require dirname(__DIR__, 2) . '/includes/admin.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice-ap.php';
require dirname(__DIR__, 2) . '/includes/po-payment.php';

accounting_require_update();

$invoiceId = (int) ($_GET['id'] ?? $_POST['invoice_id'] ?? 0);
$invoice = $invoiceId > 0 ? supplier_invoice_get($invoiceId) : null;
if ($invoice === null) {
    header('Location: ' . accounting_path('/accounting/supplier-invoices/'), true, 302);
    exit;
}

if (!supplier_invoice_can_confirm_payment($invoice)) {
    header('Location: ' . accounting_path('/accounting/supplier-invoices/view.php') . '?id=' . $invoiceId, true, 302);
    exit;
}

$error = null;
$remaining = (float) ($invoice['Balance'] ?? $invoice['TotalAmt'] ?? 0);
$form = [
    'payment_date'        => date('Y-m-d'),
    'payment_amount'      => number_format($remaining > 0 ? $remaining : (float) ($invoice['TotalAmt'] ?? 0), 2, '.', ''),
    'payment_type'        => 'ACH',
    'payment_conf_number' => '',
    'payment_made_by'     => (string) (auth_user()['UserName'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['payment_date'] = trim((string) ($_POST['payment_date'] ?? $form['payment_date']));
    $form['payment_amount'] = trim((string) ($_POST['payment_amount'] ?? $form['payment_amount']));
    $form['payment_type'] = trim((string) ($_POST['payment_type'] ?? $form['payment_type']));
    $form['payment_conf_number'] = trim((string) ($_POST['payment_conf_number'] ?? ''));
    $form['payment_made_by'] = trim((string) ($_POST['payment_made_by'] ?? $form['payment_made_by']));

    $result = supplier_invoice_confirm_bill_payment($invoiceId, $form);
    if ($result['ok']) {
        header(
            'Location: ' . accounting_path('/accounting/supplier-invoices/view.php') . '?' . http_build_query([
                'id'     => $invoiceId,
                'notice' => 'payment_confirmed',
            ]),
            true,
            302
        );
        exit;
    }
    $error = (string) ($result['error'] ?? 'Unable to confirm this bill payment.');
}

$activeSlug = $activeSlug ?? 'accounting';
$accountingSection = 'invoices';
$pageTitle = 'Confirm Bill Payment | ' . supplier_invoice_reference($invoice);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => accounting_path('/accounting/supplier-invoices/view.php') . '?id=' . $invoiceId,
          'back_label' => 'Back to Invoice',
          'category'   => 'Finance',
          'title'      => 'Confirm bill payment',
          'lead'       => supplier_invoice_reference($invoice) . ' · ' . $invoice['SupplierName'] . ' · remaining ' . accounting_format_money($remaining),
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/accounting-nav.php'; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="admin-form" action="<?= htmlspecialchars(accounting_path('/accounting/supplier-invoices/confirm-payment.php') . '?id=' . $invoiceId) ?>">
        <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>" />
        <div class="form-group">
          <label for="payment_date">Payment date</label>
          <div class="form-field">
            <input class="form-input" type="date" id="payment_date" name="payment_date" required value="<?= htmlspecialchars($form['payment_date']) ?>" />
          </div>
        </div>
        <div class="form-group">
          <label for="payment_amount">Amount</label>
          <div class="form-field">
            <input class="form-input" type="number" step="0.01" min="0.01" id="payment_amount" name="payment_amount" required value="<?= htmlspecialchars($form['payment_amount']) ?>" />
          </div>
        </div>
        <div class="form-group">
          <label for="payment_type">Method</label>
          <div class="form-field">
            <select class="form-input" id="payment_type" name="payment_type">
              <?php foreach (PO_PAYMENT_TYPES as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= $form['payment_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label for="payment_conf_number">Confirmation #</label>
          <div class="form-field">
            <input class="form-input" type="text" id="payment_conf_number" name="payment_conf_number" value="<?= htmlspecialchars($form['payment_conf_number']) ?>" />
          </div>
        </div>
        <div class="form-group">
          <label for="payment_made_by">Paid by</label>
          <div class="form-field">
            <input class="form-input" type="text" id="payment_made_by" name="payment_made_by" value="<?= htmlspecialchars($form['payment_made_by']) ?>" />
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Post bill payment to QuickBooks</button>
          <a class="btn-secondary" href="<?= htmlspecialchars(accounting_path('/accounting/supplier-invoices/view.php') . '?id=' . $invoiceId) ?>">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
