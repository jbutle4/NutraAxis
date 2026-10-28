<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/provider-signup.php';
require dirname(__DIR__, 2) . '/includes/clinic-patient.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

provider_signup_require_update();

$activeSlug = 'signup-review';
$env = clinic_patient_normalize_environment($_GET['env'] ?? $_POST['env'] ?? 'stage') ?? 'stage';
$companyId = (int) ($_POST['company_id'] ?? $_GET['company_id'] ?? 0);
$practitionerId = (int) ($_POST['practitioner_id'] ?? $_GET['practitioner_id'] ?? 0);
$form = [
    'firstname' => trim((string) ($_POST['firstname'] ?? '')),
    'lastname'  => trim((string) ($_POST['lastname'] ?? '')),
    'email'     => trim((string) ($_POST['email'] ?? '')),
    'phone'     => trim((string) ($_POST['phone'] ?? '')),
];
$error = null;
$created = null;

$companies = clinic_patient_list_companies($env);
$catalog = $companyId > 0 ? clinic_patient_resolve_catalog($env, $companyId) : null;
$practitioners = $companyId > 0 ? clinic_patient_list_practitioners($env, $companyId) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'create') {
    if ($env === 'production' && empty($_POST['confirm_production'])) {
        $error = 'Production patient create requires the confirmation checkbox.';
    } else {
        $created = clinic_patient_create($env, $companyId, $practitionerId, $form);
        if (empty($created['ok'])) {
            $error = (string) ($created['error'] ?? 'Unable to create the patient.');
            $created = null;
        }
    }
}

$pageTitle = 'Create Clinic Patient | NutraAxis Operations';
$pageDescription = 'Create an ACCS patient and associate them to a clinic shared catalog.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/operations-dashboard/signup-review/',
          'back_label' => 'Back to Provider Signup Queue',
          'category'   => 'Operations',
          'title'      => 'Create Clinic Patient',
          'lead'       => 'Creates an individual ACCS customer on the clinic shared-catalog group, with Clinic ID and Practitioner ID set the same way as QR-code signup.',
          'permission' => permission_label(provider_signup_permission_value()),
      ]);
      ?>

      <div class="admin-notice" role="status">
        The patient is <strong>not</strong> added as a company user. Group is the clinic <code>SC-…</code> catalog group (pricing). Clinic ID is the company ID. Practitioner ID is the doctor’s customer ID (clinic admin order attribution).
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (is_array($created)): ?>
      <div class="admin-notice is-success" role="status">
        Patient created in <?= htmlspecialchars(provider_signup_accs_environment_label($env)) ?> ACCS.
        Customer ID <strong><?= (int) $created['customer_id'] ?></strong>
        · group <?= htmlspecialchars((string) $created['group_code']) ?> (<?= (int) $created['group_id'] ?>)
        · catalog <?= (int) $created['catalog_id'] ?>.
        Temporary password: <code><?= htmlspecialchars((string) $created['password']) ?></code>
        Copy it now; it is not stored here.
      </div>
      <?php endif; ?>

      <form class="admin-form" method="get" action="/operations-dashboard/signup-review/new-patient.php">
        <div class="form-group">
          <label for="env">ACCS environment</label>
          <select class="form-input" id="env" name="env" onchange="this.form.company_id.value=''; this.form.submit()">
            <option value="stage" <?= $env === 'stage' ? 'selected' : '' ?>>Stage</option>
            <option value="production" <?= $env === 'production' ? 'selected' : '' ?>>Production</option>
          </select>
        </div>
        <div class="form-group">
          <label for="company_id">Clinic</label>
          <select class="form-input" id="company_id" name="company_id" onchange="this.form.submit()">
            <option value="">Select a clinic</option>
            <?php foreach ($companies as $company): ?>
            <option value="<?= (int) ($company['id'] ?? 0) ?>" <?= (int) ($company['id'] ?? 0) === $companyId ? 'selected' : '' ?>>
              <?= htmlspecialchars((string) ($company['company_name'] ?? '')) ?>
              (ID <?= (int) ($company['id'] ?? 0) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>

      <?php if ($companyId > 0 && is_array($catalog)): ?>
        <?php if (!empty($catalog['ok'])): ?>
      <div class="admin-notice" role="status">
        Shared catalog <strong><?= htmlspecialchars((string) $catalog['catalog_name']) ?></strong>
        (ID <?= (int) $catalog['catalog_id'] ?>)
        · group <strong><?= htmlspecialchars((string) $catalog['group_code']) ?></strong>
        (<?= (int) $catalog['group_id'] ?>).
        Clinic ID will be <strong><?= $companyId ?></strong>.
      </div>
        <?php else: ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars((string) $catalog['error']) ?></div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($companyId > 0 && !empty($catalog['ok'])): ?>
      <form class="admin-form" method="post" action="/operations-dashboard/signup-review/new-patient.php">
        <input type="hidden" name="action" value="create" />
        <input type="hidden" name="env" value="<?= htmlspecialchars($env) ?>" />
        <input type="hidden" name="company_id" value="<?= $companyId ?>" />

        <div class="form-group">
          <label for="practitioner_id">Practitioner</label>
          <select class="form-input" id="practitioner_id" name="practitioner_id" required>
            <option value="">Select the doctor</option>
            <?php foreach ($practitioners as $row): ?>
            <option value="<?= (int) $row['id'] ?>" <?= (int) $row['id'] === $practitionerId ? 'selected' : '' ?>>
              <?= htmlspecialchars($row['name']) ?>
              (ID <?= (int) $row['id'] ?><?= $row['email'] !== '' ? ' · ' . $row['email'] : '' ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($practitioners === []): ?>
        <p class="form-hint">No Clinic Doctor users found on this company. Set Clinic Doctor = Yes on the provider in ACCS, then reload.</p>
        <?php endif; ?>

        <div class="form-group">
          <label for="firstname">First name</label>
          <input class="form-input" type="text" id="firstname" name="firstname" required value="<?= htmlspecialchars($form['firstname']) ?>" />
        </div>
        <div class="form-group">
          <label for="lastname">Last name</label>
          <input class="form-input" type="text" id="lastname" name="lastname" required value="<?= htmlspecialchars($form['lastname']) ?>" />
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input class="form-input" type="email" id="email" name="email" required value="<?= htmlspecialchars($form['email']) ?>" />
        </div>
        <div class="form-group">
          <label for="phone">Phone</label>
          <input class="form-input" type="text" id="phone" name="phone" value="<?= htmlspecialchars($form['phone']) ?>" />
        </div>

        <?php if ($env === 'production'): ?>
        <div class="form-group form-group--stacked">
          <label class="checkbox-label">
            <input type="checkbox" name="confirm_production" value="1" required />
            Create this patient on <strong>Production</strong> ACCS.
          </label>
        </div>
        <?php endif; ?>

        <div class="module-actions">
          <button class="btn-primary" type="submit" <?= $practitioners === [] ? 'disabled' : '' ?>>Create patient</button>
          <a class="btn-secondary" href="/operations-dashboard/signup-review/">Cancel</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
