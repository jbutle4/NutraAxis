<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/list-page-header.php';
require_once dirname(__DIR__, 2) . '/includes/accs-support-case.php';
require_once dirname(__DIR__, 2) . '/includes/accs-support-clone.php';

auth_require_module_read('accs-order-account-support');
$canCreate = auth_can_create('AccsOrderAccountSupport');

$caseId = (int) ($_GET['case_id'] ?? $_POST['case_id'] ?? 0);
$ticketRef = trim((string) ($_POST['ticket_ref'] ?? $_GET['ticket_ref'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$searchQ = trim((string) ($_POST['q'] ?? $_GET['q'] ?? ''));
$prodCompanyId = (int) ($_POST['prod_company_id'] ?? $_GET['prod_company_id'] ?? 0);
$step = (string) ($_POST['step'] ?? $_GET['step'] ?? 'search');
$error = null;
$companies = [];
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canCreate) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'search') {
        $step = 'search';
        $found = accs_support_clone_search_prod_companies($searchQ);
        if (!$found['ok']) {
            $error = $found['error'] ?? 'Search failed.';
        } else {
            $companies = $found['companies'];
            if ($companies === []) {
                $error = 'No Production companies matched that search.';
            } else {
                $step = 'pick';
            }
        }
    } elseif ($action === 'preview' && $prodCompanyId > 0) {
        $preview = accs_support_clone_preview_prod_company($prodCompanyId);
        if (!$preview['ok']) {
            $error = $preview['error'] ?? 'Unable to load Production company.';
            $step = 'search';
        } else {
            $step = 'confirm';
        }
    } elseif ($action === 'clone' && $prodCompanyId > 0) {
        if ($caseId <= 0) {
            $created = accs_support_case_create([
                'ticket_ref'        => $ticketRef,
                'subject'           => $subject,
                'prod_company_id'   => $prodCompanyId,
            ]);
            if (!$created['ok'] || empty($created['id'])) {
                $error = $created['error'] ?? 'Unable to create support case.';
                $step = 'confirm';
                $preview = accs_support_clone_preview_prod_company($prodCompanyId);
            } else {
                $caseId = (int) $created['id'];
            }
        }

        if ($error === null && $caseId > 0) {
            if ($ticketRef !== '' || $subject !== '') {
                accs_support_case_update($caseId, array_filter([
                    'ticket_ref' => $ticketRef !== '' ? $ticketRef : null,
                    'subject'    => $subject !== '' ? $subject : null,
                ], static fn ($v) => $v !== null));
            }

            $result = accs_support_clone_run($caseId, $prodCompanyId);
            if ($result['ok']) {
                header('Location: /accs-order-account-support/cases/view.php?id=' . $caseId . '&notice=cloned');
                exit;
            }
            $error = $result['error'] ?? 'Clone failed.';
            $step = 'confirm';
            $preview = accs_support_clone_preview_prod_company($prodCompanyId);
        }
    }
} elseif ($prodCompanyId > 0 && $step === 'confirm') {
    $preview = accs_support_clone_preview_prod_company($prodCompanyId);
    if (!$preview['ok']) {
        $error = $preview['error'] ?? 'Unable to load Production company.';
        $step = 'search';
    }
}

$existingCase = $caseId > 0 ? accs_support_case_get($caseId) : null;
if ($existingCase !== null && $ticketRef === '') {
    $ticketRef = trim((string) ($existingCase['TicketRef'] ?? ''));
}
if ($existingCase !== null && $subject === '') {
    $subject = trim((string) ($existingCase['Subject'] ?? ''));
}

$pageTitle = 'Clone ACCS account to Stage | NutraAxis Operations';
$pageDescription = 'Mirror a Production clinic onto Stage for ACCS support.';

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
          'title'      => 'Clone account to Stage',
          'lead'       => 'Search Production, preview catalog membership and prices, then create a SUPPORT- Stage clone with Prod mirror.',
          'permission' => auth_module_permission_label('accs-order-account-support'),
      ]);
      ?>

      <?php if (!$canCreate): ?>
      <div class="admin-notice" role="status">You can view this process but do not have create permission to run a clone.</div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($step === 'search' || $step === 'pick'): ?>
      <section class="detail-card">
        <h2>1. Find Production company</h2>
        <form class="admin-form" method="post">
          <input type="hidden" name="action" value="search" />
          <?php if ($caseId > 0): ?><input type="hidden" name="case_id" value="<?= $caseId ?>" /><?php endif; ?>
          <div class="form-group">
            <label for="ticket_ref">Ticket / Zendesk ref</label>
            <div class="form-field"><input class="form-input" type="text" id="ticket_ref" name="ticket_ref" value="<?= htmlspecialchars($ticketRef) ?>" /></div>
          </div>
          <div class="form-group">
            <label for="subject">Case subject</label>
            <div class="form-field"><input class="form-input" type="text" id="subject" name="subject" value="<?= htmlspecialchars($subject) ?>" placeholder="Optional — defaults from company name" /></div>
          </div>
          <div class="form-group">
            <label for="q">Prod company ID or name</label>
            <div class="form-field"><input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($searchQ) ?>" required placeholder="e.g. 12345 or Clinic Name" /></div>
          </div>
          <button type="submit" class="btn-primary" <?= $canCreate ? '' : 'disabled' ?>>Search Production</button>
        </form>
      </section>

      <?php if ($companies !== []): ?>
      <section class="detail-card">
        <h2>Select company</h2>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>ID</th><th>Name</th><th>Email</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($companies as $company): ?>
              <tr>
                <td><?= (int) ($company['id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($company['company_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($company['company_email'] ?? '')) ?></td>
                <td>
                  <form method="post">
                    <input type="hidden" name="action" value="preview" />
                    <input type="hidden" name="prod_company_id" value="<?= (int) ($company['id'] ?? 0) ?>" />
                    <input type="hidden" name="ticket_ref" value="<?= htmlspecialchars($ticketRef) ?>" />
                    <input type="hidden" name="subject" value="<?= htmlspecialchars($subject) ?>" />
                    <?php if ($caseId > 0): ?><input type="hidden" name="case_id" value="<?= $caseId ?>" /><?php endif; ?>
                    <button type="submit" class="btn-primary" <?= $canCreate ? '' : 'disabled' ?>>Preview</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($step === 'confirm' && is_array($preview) && ($preview['ok'] ?? false)): ?>
      <?php
        $company = $preview['company'] ?? [];
        $admin = $preview['admin'] ?? [];
        $catalog = $preview['catalog'] ?? [];
      ?>
      <section class="detail-card">
        <h2>2. Confirm Prod → Stage clone</h2>
        <dl class="detail-list detail-list-inline">
          <div><dt>Prod company</dt><dd><?= htmlspecialchars((string) ($company['company_name'] ?? '')) ?> <span class="muted">#<?= (int) ($company['id'] ?? 0) ?></span></dd></div>
          <div><dt>Admin</dt><dd><?= htmlspecialchars((string) ($admin['email'] ?? '—')) ?> <?php if (!empty($admin['id'])): ?><span class="muted">#<?= (int) $admin['id'] ?></span><?php endif; ?></dd></div>
          <div><dt>Shared catalog</dt><dd><?php if (!empty($catalog['id'])): ?><?= htmlspecialchars((string) ($catalog['name'] ?? '')) ?> <span class="muted">#<?= (int) $catalog['id'] ?></span><?php else: ?>None found on admin patient_shared_catalog_id<?php endif; ?></dd></div>
          <div><dt>SKUs</dt><dd><?= (int) ($preview['sku_count'] ?? 0) ?></dd></div>
          <div><dt>Categories</dt><dd><?= (int) ($preview['category_count'] ?? 0) ?></dd></div>
          <div><dt>Tier prices</dt><dd><?= (int) ($preview['price_count'] ?? 0) ?></dd></div>
          <div><dt>Stage name</dt><dd><?php if ($caseId > 0): ?><?= htmlspecialchars(accs_support_clone_stage_company_name((string) ($company['company_name'] ?? ''), $caseId)) ?><?php else: ?><?= htmlspecialchars(ACCS_SUPPORT_CLONE_NAME_PREFIX . trim((string) ($company['company_name'] ?? 'Clinic')) . '-{caseId}') ?> <span class="muted">(case ID assigned on confirm)</span><?php endif; ?></dd></div>
        </dl>
        <p class="muted" style="margin-top:0.75rem;">Writes Stage only. Admin uses the same Prod email (no provider welcome email from this portal). SKUs missing on Stage are skipped and reported on the case.</p>
        <form class="admin-form" method="post" style="margin-top:1rem;">
          <input type="hidden" name="action" value="clone" />
          <input type="hidden" name="prod_company_id" value="<?= (int) ($company['id'] ?? 0) ?>" />
          <?php if ($caseId > 0): ?><input type="hidden" name="case_id" value="<?= $caseId ?>" /><?php endif; ?>
          <div class="form-group">
            <label for="ticket_ref2">Ticket / Zendesk ref</label>
            <div class="form-field"><input class="form-input" type="text" id="ticket_ref2" name="ticket_ref" value="<?= htmlspecialchars($ticketRef) ?>" /></div>
          </div>
          <div class="form-group">
            <label for="subject2">Case subject</label>
            <div class="form-field"><input class="form-input" type="text" id="subject2" name="subject" value="<?= htmlspecialchars($subject) ?>" /></div>
          </div>
          <button type="submit" class="btn-primary" <?= $canCreate ? '' : 'disabled' ?>>Create Stage clone</button>
          <a class="btn-secondary" href="/accs-order-account-support/clone/">Start over</a>
        </form>
      </section>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
