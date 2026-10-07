<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/list-page-header.php';
require_once dirname(__DIR__, 2) . '/includes/accs-support-case.php';
require_once dirname(__DIR__, 2) . '/includes/accs-support-purge.php';

auth_require_module_read('accs-order-account-support');
$canDelete = auth_can_delete('AccsOrderAccountSupport');

$caseId = (int) ($_GET['case_id'] ?? $_POST['case_id'] ?? 0);
$error = null;
$preview = null;
$openCases = [];

if ($caseId <= 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $openCases = accs_support_case_list(['status' => 'cloned', 'page' => 1])['rows'];
    $failed = accs_support_case_list(['status' => 'purge_failed', 'page' => 1])['rows'];
    $cartReady = accs_support_case_list(['status' => 'cart_ready', 'page' => 1])['rows'];
    $openCases = array_merge($openCases, $cartReady, $failed);
}

if ($caseId > 0) {
    $preview = accs_support_purge_preview($caseId);
    if (!($preview['ok'] ?? false) && empty($preview['case'])) {
        $error = $preview['error'] ?? 'Unable to load case.';
        $preview = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canDelete) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'purge') {
        $caseId = (int) ($_POST['case_id'] ?? 0);
        $confirm = (string) ($_POST['confirm_name'] ?? '');
        $deleteCustomer = !empty($_POST['delete_customer']);
        $result = accs_support_purge_run($caseId, $confirm, $deleteCustomer);
        if ($result['ok']) {
            header('Location: /accs-order-account-support/cases/view.php?id=' . $caseId . '&notice=purged');
            exit;
        }
        $error = $result['error'] ?? 'Purge failed.';
        $preview = accs_support_purge_preview($caseId);
    }
}

$pageTitle = 'Purge Stage support account | NutraAxis Operations';
$pageDescription = 'Tear down SUPPORT- Stage ACCS resources for a support case.';

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
          'title'      => 'Purge Stage support account',
          'lead'       => 'Delete only SUPPORT- Stage companies created for support cases. Type the Stage company name to confirm.',
          'permission' => auth_module_permission_label('accs-order-account-support'),
      ]);
      ?>

      <?php if (!$canDelete): ?>
      <div class="admin-notice" role="status">You need delete permission on ACCS Order and Account Support to run purge.</div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($caseId <= 0): ?>
      <section class="detail-card">
        <h2>Select a case with Stage resources</h2>
        <?php if ($openCases === []): ?>
        <p class="muted">No cloned cases ready to purge. Open a case from the <a href="/accs-order-account-support/cases/">case list</a>.</p>
        <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>Case</th><th>Status</th><th>Stage company</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($openCases as $row): ?>
              <tr>
                <td>#<?= (int) $row['CaseID'] ?></td>
                <td><?= htmlspecialchars(accs_support_case_status_label((string) $row['Status'])) ?></td>
                <td><?= htmlspecialchars((string) ($row['StageCompanyName'] ?? '—')) ?></td>
                <td><a class="btn-primary" href="/accs-order-account-support/purge/?case_id=<?= (int) $row['CaseID'] ?>">Preview purge</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>
      <?php elseif (is_array($preview)): ?>
      <section class="detail-card">
        <h2>Purge preview — case #<?= $caseId ?></h2>
        <?php if (!empty($preview['blocked'])): ?>
        <div class="admin-notice is-error" role="alert">
          <?= htmlspecialchars(implode(' ', $preview['blocked'])) ?>
        </div>
        <?php endif; ?>
        <dl class="detail-list detail-list-inline">
          <div><dt>Stage company</dt><dd><?= htmlspecialchars((string) ($preview['company']['name'] ?? '—')) ?> <span class="muted">#<?= (int) ($preview['company']['id'] ?? 0) ?></span> <?= !empty($preview['company']['exists']) ? '(exists)' : '(absent)' ?></dd></div>
          <div><dt>Shared catalog</dt><dd><?= htmlspecialchars((string) ($preview['catalog']['name'] ?? '—')) ?> <span class="muted">#<?= (int) ($preview['catalog']['id'] ?? 0) ?></span> <?= !empty($preview['catalog']['exists']) ? '(exists)' : '(absent)' ?></dd></div>
          <div><dt>Catalog prices (SKU count)</dt><dd><?= (int) ($preview['price_count'] ?? 0) ?></dd></div>
          <div><dt>Admin customer</dt><dd><?= htmlspecialchars((string) ($preview['customer']['email'] ?? '—')) ?> <span class="muted">#<?= (int) ($preview['customer']['id'] ?? 0) ?></span> <?= !empty($preview['customer']['exists']) ? '(exists)' : '(absent)' ?></dd></div>
        </dl>

        <?php if (!empty($preview['resources'])): ?>
        <h3 style="margin-top:1rem;">Active ledger resources</h3>
        <ul>
          <?php foreach ($preview['resources'] as $resource): ?>
          <li><?= htmlspecialchars((string) ($resource['ResourceType'] ?? '')) ?>
            <?= htmlspecialchars((string) ($resource['StageEntityId'] ?? '')) ?>
            <?= htmlspecialchars((string) ($resource['StageEntityName'] ?? '')) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if ($canDelete && empty($preview['blocked'])): ?>
        <form class="admin-form" method="post" style="margin-top:1.25rem;">
          <input type="hidden" name="action" value="purge" />
          <input type="hidden" name="case_id" value="<?= $caseId ?>" />
          <div class="form-group">
            <label for="confirm_name">Type Stage company name to confirm</label>
            <div class="form-field">
              <input class="form-input" type="text" id="confirm_name" name="confirm_name" required autocomplete="off" placeholder="<?= htmlspecialchars((string) ($preview['company']['name'] ?? '')) ?>" />
            </div>
          </div>
          <div class="form-group form-group--stacked">
            <label>
              <input type="checkbox" name="delete_customer" value="1" checked />
              Also delete Stage admin customer
            </label>
          </div>
          <button type="submit" class="btn-primary">Purge Stage support account</button>
          <a class="btn-secondary" href="/accs-order-account-support/cases/view.php?id=<?= $caseId ?>">Cancel</a>
        </form>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
