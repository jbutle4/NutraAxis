<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/list-page-header.php';
require_once dirname(__DIR__, 2) . '/includes/accs-support-case.php';
require_once dirname(__DIR__, 2) . '/includes/accs-support-cart.php';

auth_require_module_read('accs-order-account-support');
$canCreate = auth_can_create('AccsOrderAccountSupport');

$caseId = (int) ($_GET['case_id'] ?? $_POST['case_id'] ?? 0);
$orderRef = trim((string) ($_POST['order_ref'] ?? $_GET['order_ref'] ?? ''));
$step = (string) ($_POST['step'] ?? $_GET['step'] ?? 'select');
$error = null;
$preview = null;
$eligibleCases = [];

if ($caseId <= 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    foreach (['cloned', 'cart_ready'] as $status) {
        $rows = accs_support_case_list(['status' => $status, 'page' => 1])['rows'];
        foreach ($rows as $row) {
            if (!empty($row['StageCustomerId']) && !empty($row['StageCompanyId'])) {
                $eligibleCases[] = $row;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canCreate) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'preview') {
        $caseId = (int) ($_POST['case_id'] ?? 0);
        $orderRef = trim((string) ($_POST['order_ref'] ?? ''));
        $preview = accs_support_cart_preview($caseId, $orderRef);
        if (!($preview['ok'] ?? false)) {
            $error = $preview['error'] ?? 'Preview failed.';
            $step = 'select';
        } else {
            $step = 'confirm';
        }
    } elseif ($action === 'build') {
        $caseId = (int) ($_POST['case_id'] ?? 0);
        $orderRef = trim((string) ($_POST['order_ref'] ?? ''));
        $setAddresses = !empty($_POST['set_addresses']);
        $result = accs_support_cart_run($caseId, $orderRef, $setAddresses);
        if ($result['ok']) {
            header('Location: /accs-order-account-support/cases/view.php?id=' . $caseId . '&notice=cart');
            exit;
        }
        $error = $result['error'] ?? 'Unable to build Stage cart.';
        $preview = accs_support_cart_preview($caseId, $orderRef);
        $step = ($preview['ok'] ?? false) ? 'confirm' : 'select';
    }
} elseif ($caseId > 0 && $orderRef !== '' && $step === 'confirm') {
    $preview = accs_support_cart_preview($caseId, $orderRef);
    if (!($preview['ok'] ?? false)) {
        $error = $preview['error'] ?? 'Preview failed.';
        $step = 'select';
    }
}

$selectedCase = $caseId > 0 ? accs_support_case_get($caseId) : null;

$pageTitle = 'Recreate order as Stage cart | NutraAxis Operations';
$pageDescription = 'Build an open Stage cart from a Production ACCS order.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/accs-order-account-support/',
          'back_label' => 'Back to ACCS Order and Account Support',
          'category'   => 'IT & Ecommerce',
          'title'      => 'Recreate order as Stage cart',
          'lead'       => 'Load a Production order onto the cloned Stage admin cart and leave it open so you can finish checkout on the Stage storefront.',
          'permission' => auth_module_permission_label('accs-order-account-support'),
      ]);
      ?>

      <?php if (!$canCreate): ?>
      <div class="admin-notice" role="status">You can view this process but need create permission to build a Stage cart.</div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($step === 'select'): ?>
      <section class="detail-card">
        <h2>1. Choose support case and Production order</h2>
        <form class="admin-form" method="post">
          <input type="hidden" name="action" value="preview" />
          <div class="form-group">
            <label for="case_id">Support case</label>
            <div class="form-field">
              <?php if ($selectedCase !== null): ?>
              <input type="hidden" name="case_id" value="<?= $caseId ?>" />
              <input class="form-input" type="text" id="case_id" value="#<?= $caseId ?> — <?= htmlspecialchars((string) ($selectedCase['StageCompanyName'] ?? $selectedCase['ProdCompanyName'] ?? '')) ?>" readonly />
              <?php elseif ($eligibleCases !== []): ?>
              <select class="form-input" id="case_id" name="case_id" required>
                <option value="">Select a cloned case…</option>
                <?php foreach ($eligibleCases as $row): ?>
                <option value="<?= (int) $row['CaseID'] ?>">
                  #<?= (int) $row['CaseID'] ?>
                  — <?= htmlspecialchars((string) ($row['StageCompanyName'] ?? '')) ?>
                  (Prod #<?= (int) ($row['ProdCompanyId'] ?? 0) ?>)
                </option>
                <?php endforeach; ?>
              </select>
              <?php else: ?>
              <p class="muted">No cloned cases with Stage customers found. <a href="/accs-order-account-support/clone/">Clone an account</a> first.</p>
              <?php endif; ?>
            </div>
          </div>
          <div class="form-group">
            <label for="order_ref">Production order #</label>
            <div class="form-field">
              <input class="form-input" type="text" id="order_ref" name="order_ref" value="<?= htmlspecialchars($orderRef) ?>" required placeholder="e.g. 100012345 or entity ID" <?= ($selectedCase !== null || $eligibleCases !== []) ? '' : 'disabled' ?> />
            </div>
          </div>
          <button type="submit" class="btn-primary" <?= $canCreate && ($selectedCase !== null || $eligibleCases !== []) ? '' : 'disabled' ?>>Preview order lines</button>
        </form>
      </section>
      <?php endif; ?>

      <?php if ($step === 'confirm' && is_array($preview) && ($preview['ok'] ?? false)): ?>
      <?php
        $order = $preview['order'] ?? [];
        $available = $preview['available'] ?? [];
        $missing = $preview['missing'] ?? [];
        $caseRow = $preview['case'] ?? $selectedCase;
      ?>
      <section class="detail-card">
        <h2>2. Confirm open Stage cart</h2>
        <dl class="detail-list detail-list-inline">
          <div><dt>Case</dt><dd>#<?= (int) ($caseRow['CaseID'] ?? $caseId) ?> — <?= htmlspecialchars((string) ($caseRow['StageCompanyName'] ?? '')) ?></dd></div>
          <div><dt>Stage customer</dt><dd>#<?= (int) ($caseRow['StageCustomerId'] ?? 0) ?></dd></div>
          <div><dt>Prod order</dt><dd><?= htmlspecialchars((string) ($order['increment_id'] ?? '')) ?> <span class="muted">entity #<?= (int) ($order['entity_id'] ?? 0) ?></span></dd></div>
          <div><dt>Prod status</dt><dd><?= htmlspecialchars((string) ($order['status'] ?? '—')) ?></dd></div>
          <div><dt>Prod customer</dt><dd><?= htmlspecialchars((string) ($order['customer_email'] ?? '—')) ?></dd></div>
          <div><dt>Lines on Stage</dt><dd><?= count($available) ?> of <?= count($available) + count($missing) ?></dd></div>
        </dl>

        <h3 style="margin-top:1rem;">Lines to add</h3>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>SKU</th><th>Name</th><th>Qty</th><th>Stage</th></tr>
            </thead>
            <tbody>
              <?php foreach ($available as $line): ?>
              <tr>
                <td><?= htmlspecialchars((string) $line['sku']) ?></td>
                <td><?= htmlspecialchars((string) $line['name']) ?></td>
                <td><?= htmlspecialchars((string) $line['qty']) ?></td>
                <td>Available</td>
              </tr>
              <?php endforeach; ?>
              <?php foreach ($missing as $line): ?>
              <tr>
                <td><?= htmlspecialchars((string) $line['sku']) ?></td>
                <td><?= htmlspecialchars((string) $line['name']) ?></td>
                <td><?= htmlspecialchars((string) $line['qty']) ?></td>
                <td>Missing on Stage — skipped</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <p class="muted" style="margin-top:0.75rem;">Creates an open Stage cart only — does not place the order. Log in as the Stage admin afterward and finish checkout yourself.</p>

        <form class="admin-form" method="post" style="margin-top:1rem;">
          <input type="hidden" name="action" value="build" />
          <input type="hidden" name="case_id" value="<?= (int) ($caseRow['CaseID'] ?? $caseId) ?>" />
          <input type="hidden" name="order_ref" value="<?= htmlspecialchars((string) ($order['increment_id'] ?? $orderRef)) ?>" />
          <div class="form-group form-group--stacked">
            <label>
              <input type="checkbox" name="set_addresses" value="1" checked />
              Copy billing address from the Production order onto the Stage cart
            </label>
          </div>
          <button type="submit" class="btn-primary" <?= $canCreate && $available !== [] ? '' : 'disabled' ?>>Build open Stage cart</button>
          <a class="btn-secondary" href="/accs-order-account-support/cart/<?= $caseId > 0 ? ('?case_id=' . $caseId) : '' ?>">Start over</a>
        </form>
      </section>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
