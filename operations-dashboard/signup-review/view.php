<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/provider-signup.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

provider_signup_require_read();

$applicationId = (int) ($_GET['id'] ?? 0);
$application = provider_signup_get($applicationId);

if ($application === null) {
    http_response_code(404);
    $pageTitle = 'Application Not Found | NutraAxis Operations';
    require dirname(__DIR__, 2) . '/includes/head.php';
    require dirname(__DIR__, 2) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Application not found</h1><div class="module-actions"><a class="btn-secondary" href="/operations-dashboard/signup-review/">Back to queue</a></div></div></div></main>';
    require dirname(__DIR__, 2) . '/includes/footer.php';
    exit;
}

$activeSlug = 'signup-review';
$error = null;
$canUpdate = provider_signup_can_update();
$canEdit = provider_signup_ops_can_edit($application);
$canApprove = provider_signup_ops_can_approve($application);
$canProvision = provider_signup_ops_can_provision($application);
$canRevert = provider_signup_ops_can_revert($application);
$providerCanEdit = provider_signup_provider_can_edit($application);
$approvalChecklist = provider_signup_submit_checklist(provider_signup_form_from_row($application), $applicationId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canUpdate) {
    $action = (string) ($_POST['action'] ?? '');
    $comments = trim((string) ($_POST['comments'] ?? ''));
    $redirect = '/operations-dashboard/signup-review/view.php?id=' . $applicationId;

    switch ($action) {
        case 'comment':
            $result = provider_signup_ops_comment($applicationId, $comments);
            $suffix = $result['ok'] ? 'notice=commented' : 'error=' . rawurlencode($result['error'] ?? 'Unable to save comment.');
            header('Location: ' . $redirect . '&' . $suffix, true, 302);
            exit;
        case 'return':
            $result = provider_signup_ops_return($applicationId, $comments);
            $suffix = $result['ok'] ? 'notice=returned' : 'error=' . rawurlencode($result['error'] ?? 'Unable to return application.');
            header('Location: ' . $redirect . '&' . $suffix, true, 302);
            exit;
        case 'revert_status':
            $targetStatus = trim((string) ($_POST['target_status'] ?? ''));
            $result = provider_signup_ops_revert_status($applicationId, $targetStatus, $comments);
            if ($result['ok']) {
                $suffix = ($result['target_status'] ?? '') === PROVIDER_SIGNUP_STATUS_DRAFT
                    ? 'notice=reopened'
                    : 'notice=returned';
                header('Location: ' . $redirect . '&' . $suffix, true, 302);
            } else {
                header('Location: ' . $redirect . '&error=' . rawurlencode($result['error'] ?? 'Unable to change application status.'), true, 302);
            }
            exit;
        case 'reject':
            $result = provider_signup_ops_reject($applicationId, $comments);
            $suffix = $result['ok'] ? 'notice=rejected' : 'error=' . rawurlencode($result['error'] ?? 'Unable to reject application.');
            header('Location: ' . $redirect . '&' . $suffix, true, 302);
            exit;
        case 'validate_npi':
            $result = provider_signup_ops_validate_npi($applicationId);
            if ($result['ok']) {
                header('Location: ' . $redirect . '&notice=npi_validated', true, 302);
            } else {
                header('Location: ' . $redirect . '&error=' . rawurlencode($result['error'] ?? $result['summary'] ?? 'NPI validation failed.'), true, 302);
            }
            exit;
        case 'approve':
            $result = provider_signup_ops_approve($applicationId, $comments, $_POST);
            if ($result['ok']) {
                $suffix = 'notice=approved';
                header('Location: ' . $redirect . '&' . $suffix, true, 302);
            } else {
                header('Location: ' . $redirect . '&error=' . rawurlencode($result['error'] ?? 'Unable to approve application.'), true, 302);
            }
            exit;
        case 'provision':
            $result = provider_signup_ops_provision($applicationId, $_POST);
            if ($result['ok']) {
                $suffix = !empty($result['already']) ? 'notice=already_provisioned' : 'notice=provisioned';
                header('Location: ' . $redirect . '&' . $suffix, true, 302);
            } else {
                header('Location: ' . $redirect . '&error=' . rawurlencode($result['error'] ?? 'Unable to create ACCS company.'), true, 302);
            }
            exit;
        case 'mark_config_step':
            $result = provider_signup_ops_mark_config_step($applicationId, (string) ($_POST['step'] ?? ''), [
                'accs_company_id'            => $_POST['accs_company_id'] ?? '',
                'accs_clinic_id'             => $_POST['accs_clinic_id'] ?? '',
                'accs_customer_id'           => $_POST['accs_customer_id'] ?? '',
                'accs_shared_catalog_id'     => $_POST['accs_shared_catalog_id'] ?? '',
                'accs_catalog_category_count'=> $_POST['accs_catalog_category_count'] ?? '',
                'accs_catalog_product_count' => $_POST['accs_catalog_product_count'] ?? '',
                'accs_roles_summary'         => $_POST['accs_roles_summary'] ?? '',
            ]);
            if ($result['ok']) {
                $suffix = !empty($result['configuration_complete'])
                    ? 'notice=config_complete'
                    : 'notice=config_step_marked';
                header('Location: ' . $redirect . '&' . $suffix, true, 302);
            } else {
                header('Location: ' . $redirect . '&error=' . rawurlencode($result['error'] ?? 'Unable to mark configuration step.'), true, 302);
            }
            exit;
        case 'complete_accs_config':
            $result = provider_signup_ops_complete_accs_configuration($applicationId);
            if ($result['ok']) {
                if (!empty($result['already'])) {
                    $suffix = 'notice=config_complete';
                } elseif (!empty($result['configuration_complete'])) {
                    $suffix = 'notice=accs_config_complete';
                } else {
                    $suffix = 'notice=accs_config_partial';
                }
                header('Location: ' . $redirect . '&' . $suffix, true, 302);
            } else {
                header('Location: ' . $redirect . '&error=' . rawurlencode($result['error'] ?? 'Unable to complete ACCS clinic configuration.'), true, 302);
            }
            exit;
        default:
            $error = 'Unknown action.';
    }

    $application = provider_signup_get($applicationId) ?? $application;
}

$attachments = provider_signup_list_attachments($applicationId);
$reviewLog = provider_signup_list_review_log($applicationId);
$npiSnapshotBundle = provider_signup_npi_get_snapshot_bundle(
    isset($application['LatestNpiSnapshotID']) ? (int) $application['LatestNpiSnapshotID'] : null
);
$taxId = provider_signup_decrypt($application['TaxIdEncrypted'] ?? null);
$accountNumber = provider_signup_decrypt($application['AchAccountNumberEncrypted'] ?? null);
$configSteps = provider_signup_config_steps($application);
$canMarkConfig = $canUpdate && provider_signup_ops_can_mark_config_step($application);
$canRunAccsConfig = provider_signup_ops_can_run_accs_configuration($application);
$accsConfigLooksComplete = !empty($application['AccsConfigurationComplete'])
    && provider_signup_config_steps_complete($application);
$reviewWarnings = provider_signup_ops_review_warnings(
    $application,
    $applicationId,
    $canApprove || $canProvision
);
$showReviewOverride = $canUpdate && $reviewWarnings !== [] && ($canApprove || $canProvision);
$targetAccsEnvironment = provider_signup_accs_normalize_environment((string) ($application['AccsEnvironment'] ?? ''));
$serverAccsEnvironment = provider_signup_accs_target_environment();
$provisionErrorMessage = provider_signup_accs_format_provision_error((string) ($application['LastProvisionError'] ?? ''));

$pageTitle = 'Provider Application #' . $applicationId . ' | NutraAxis Operations';
$pageDescription = 'Review provider signup application details.';

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
          'title'      => 'Application #' . $applicationId,
          'lead'       => trim((string) ($application['CompanyName'] ?? 'Provider application')) . ' · ' . (string) ($application['ProviderEmail'] ?? ''),
      ]);
      ?>

      <?php if (!empty($_GET['error'])): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars(provider_signup_accs_format_provision_error((string) $_GET['error'])) ?></div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if (($_GET['notice'] ?? '') === 'commented'): ?>
      <div class="admin-notice is-success" role="status">Comment saved and provider notified.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'returned'): ?>
      <div class="admin-notice is-success" role="status">Application returned to provider for edits. They were emailed with your notes.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'reopened'): ?>
      <div class="admin-notice is-success" role="status">Application reopened as draft. The provider was emailed and can edit again.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'approved'): ?>
      <div class="admin-notice is-success" role="status">Application approved. Use <strong>Create Clinic Store</strong> when you are ready to provision. The provider is not emailed until provisioning completes.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'provisioned'): ?>
      <div class="admin-notice is-success" role="status">ACCS company creation completed. The provider has been notified by email.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'already_provisioned'): ?>
      <div class="admin-notice is-success" role="status">This application was already provisioned.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'rejected'): ?>
      <div class="admin-notice is-success" role="status">Application rejected. The provider was emailed with your notes.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'npi_validated'): ?>
      <div class="admin-notice is-success" role="status">NPI validation refreshed.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Application data saved.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'created'): ?>
      <div class="admin-notice is-success" role="status">Clinic application created. Review the details, approve if needed, then use <strong>Create Clinic Store</strong> to provision ACCS.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'created_approved'): ?>
      <div class="admin-notice is-success" role="status">Clinic application created and approved. Use <strong>Create Clinic Store</strong> when you are ready to provision ACCS.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'config_step_marked'): ?>
      <div class="admin-notice is-success" role="status">Clinic configuration step saved.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'config_complete'): ?>
      <div class="admin-notice is-success" role="status">All clinic configuration steps are complete.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'accs_config_complete'): ?>
      <div class="admin-notice is-success" role="status">ACCS clinic configuration completed (shared catalog, categories/products, and roles).</div>
      <?php elseif (($_GET['notice'] ?? '') === 'accs_config_partial'): ?>
      <div class="admin-notice is-success" role="status">ACCS clinic configuration updated. Review remaining checklist items if any are still pending.</div>
      <?php endif; ?>
      <?php if (!empty($_GET['warn'])): ?>
      <div class="admin-notice" role="status"><?= htmlspecialchars((string) $_GET['warn']) ?></div>
      <?php endif; ?>

      <?php if ($targetAccsEnvironment !== null): ?>
      <div class="admin-notice" role="status">
        This application is tagged for <strong><?= htmlspecialchars(provider_signup_accs_environment_label($targetAccsEnvironment)) ?> ACCS</strong> provisioning.
        <?php if ($targetAccsEnvironment !== $serverAccsEnvironment): ?>
        Server default is <?= htmlspecialchars(provider_signup_accs_environment_label($serverAccsEnvironment)) ?>.
        <?php endif; ?>
      </div>
      <?php elseif ((string) ($application['Status'] ?? '') !== PROVIDER_SIGNUP_STATUS_PROVISIONED): ?>
      <div class="admin-notice" role="status">
        No ACCS environment tag on this application. Provisioning will use the server default:
        <strong><?= htmlspecialchars(provider_signup_accs_environment_label($serverAccsEnvironment)) ?></strong>.
        For UAT clinics, have the provider restart from
        <code><?= htmlspecialchars('https://provider-signup.nutraaxislabs.com/provider-signup/application.php?accs_env=stage') ?></code>.
      </div>
      <?php endif; ?>

      <?php if ($canApprove && (string) ($application['Status'] ?? '') === PROVIDER_SIGNUP_STATUS_DRAFT): ?>
      <div class="admin-notice" role="status">This application is in <strong>Draft</strong>. Validate the data and documents, approve it, then create the ACCS company from this page.</div>
      <?php endif; ?>

      <?php if ($canProvision): ?>
      <div class="admin-notice" role="status">This application is <strong>Approved</strong> and ready for Clinic Store creation.</div>
      <?php endif; ?>

      <?php if ($providerCanEdit): ?>
      <div class="admin-notice" role="status">The provider can currently edit this application online.</div>
      <?php endif; ?>

      <?php if (!empty($application['LastProvisionError']) && (string) ($application['Status'] ?? '') === PROVIDER_SIGNUP_STATUS_APPROVED): ?>
      <div class="admin-notice is-error" role="alert">Last ACCS provisioning attempt failed: <?= htmlspecialchars($provisionErrorMessage) ?></div>
      <?php endif; ?>

      <?php if ($canUpdate && $canEdit && !$approvalChecklist['complete']): ?>
      <div class="admin-notice is-error" role="alert">
        Complete application data is required before approval: <?= htmlspecialchars(implode(', ', $approvalChecklist['missing'])) ?>.
        <a href="/operations-dashboard/signup-review/application-form.php?id=<?= $applicationId ?>">Edit application</a>
      </div>
      <?php endif; ?>

      <?php if ($reviewWarnings !== []): ?>
      <section class="detail-card detail-card--wide provider-signup-review-warnings">
        <h2>Review warnings</h2>
        <p class="form-hint">These items should be resolved before approval or provisioning. You may proceed with an explicit override when appropriate (for example, internal test clinics).</p>
        <ul class="provider-signup-review-warnings-list">
          <?php foreach ($reviewWarnings as $warning): ?>
          <li>
            <strong><?= htmlspecialchars((string) $warning['label']) ?>:</strong>
            <?= htmlspecialchars((string) $warning['message']) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <?php if ($canUpdate && $canEdit): ?>
      <div class="module-actions" style="margin-bottom: 1.5rem;">
        <a class="btn-secondary" href="/operations-dashboard/signup-review/application-form.php?id=<?= $applicationId ?>">Edit application</a>
      </div>
      <?php endif; ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Status</h2>
          <dl class="detail-list detail-list-inline">
            <div><dt>Status</dt><dd><span class="<?= htmlspecialchars(provider_signup_status_badge_class((string) $application['Status'])) ?>"><?= htmlspecialchars((string) $application['Status']) ?></span></dd></div>
            <div><dt>Date created</dt><dd><?= htmlspecialchars(provider_signup_format_datetime($application['CreatedAt'] ?? null)) ?></dd></div>
            <div><dt>Activated</dt><dd><?= htmlspecialchars(provider_signup_format_datetime($application['SubmittedAt'] ?? null)) ?></dd></div>
            <div><dt>Last saved</dt><dd><?= htmlspecialchars(provider_signup_format_datetime($application['LastSavedAt'] ?? null)) ?></dd></div>
            <div><dt>Policy acknowledged</dt><dd><?php
              if (!empty($application['PolicyAcknowledgedAt'])) {
                  echo htmlspecialchars(provider_signup_format_datetime($application['PolicyAcknowledgedAt']));
                  if (!empty($application['PolicyAcknowledgedByEmail'])) {
                      echo ' · ' . htmlspecialchars((string) $application['PolicyAcknowledgedByEmail']);
                  }
                  if (!empty($application['PolicyVersion'])) {
                      echo ' · v' . htmlspecialchars((string) $application['PolicyVersion']);
                  }
              } else {
                  echo '—';
              }
            ?></dd></div>
            <div><dt>NPI validation</dt><dd><?= htmlspecialchars((string) ($application['NpiValidationStatus'] ?? '—')) ?><?= !empty($application['NpiValidationSummary']) ? ' — ' . htmlspecialchars((string) $application['NpiValidationSummary']) : '' ?></dd></div>
            <div><dt>Banking validation</dt><dd><?= htmlspecialchars((string) ($application['BankingValidationStatus'] ?? '—')) ?><?= !empty($application['BankingValidationSummary']) ? ' — ' . htmlspecialchars((string) $application['BankingValidationSummary']) : '' ?></dd></div>
            <div><dt>ACCS environment</dt><dd><?php
              if ($targetAccsEnvironment !== null) {
                  echo htmlspecialchars(provider_signup_accs_environment_label($targetAccsEnvironment));
              } elseif ((string) ($application['Status'] ?? '') === PROVIDER_SIGNUP_STATUS_PROVISIONED) {
                  echo '—';
              } else {
                  echo htmlspecialchars(provider_signup_accs_environment_label($serverAccsEnvironment) . ' (default)');
              }
            ?></dd></div>
            <div><dt>ACCS company ID</dt><dd><?= htmlspecialchars((string) ($application['AccsCompanyId'] ?? '—')) ?></dd></div>
            <div><dt>ACCS customer ID</dt><dd><?= htmlspecialchars((string) ($application['AccsCustomerId'] ?? '—')) ?></dd></div>
            <div><dt>Clinic ID</dt><dd><?= htmlspecialchars((string) ($application['AccsClinicId'] ?? '—')) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card detail-card--wide">
          <h2>Clinic configuration</h2>
          <?php if (!empty($application['AccsConfigurationComplete'])): ?>
          <div class="admin-notice is-success" role="status">
            All configuration steps complete
            <?php if (!empty($application['AccsConfigurationCompletedAt'])): ?>
            · <?= htmlspecialchars(provider_signup_format_datetime($application['AccsConfigurationCompletedAt'])) ?>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <p class="form-hint">Track ACCS clinic setup after approval. <strong>Create Clinic Store</strong> marks clinic and admin automatically; shared catalog, categories/products, and roles can be completed automatically or marked manually.</p>
          <?php endif; ?>
          <?php if ($canRunAccsConfig): ?>
          <form class="admin-form provider-signup-config-automation-form" method="post" action="/operations-dashboard/signup-review/view.php?id=<?= $applicationId ?>">
            <input type="hidden" name="action" value="complete_accs_config" />
            <div class="module-actions">
              <button class="btn-primary" type="submit"><?= $accsConfigLooksComplete ? 'Re-run ACCS clinic configuration' : 'Complete ACCS clinic configuration' ?></button>
            </div>
            <p class="form-hint">Creates or reuses <code>SC-{clinic name}</code>, clones master catalog categories/products, assigns the clinic company to that shared catalog, and clones template roles. Safe to re-run to repair a missing shared catalog link.</p>
          </form>
          <?php endif; ?>
          <dl class="detail-list detail-list-inline">
            <?php foreach ($configSteps as $step): ?>
            <div>
              <dt><?= htmlspecialchars($step['label']) ?></dt>
              <dd>
                <?php if ($step['done']): ?>
                <span class="status-badge status-approved">Complete</span>
                <?php if (!empty($step['completed_at'])): ?>
                · <?= htmlspecialchars(provider_signup_format_datetime($step['completed_at'])) ?>
                <?php endif; ?>
                <?php if ($step['detail'] !== ''): ?>
                · <?= htmlspecialchars($step['detail']) ?>
                <?php endif; ?>
                <?php else: ?>
                <span class="status-badge status-draft">Pending</span>
                <?php endif; ?>
              </dd>
            </div>
            <?php endforeach; ?>
          </dl>

          <?php if ($canMarkConfig): ?>
          <?php foreach ($configSteps as $step): ?>
          <?php if ($step['done']) {
              continue;
          } ?>
          <form class="admin-form provider-signup-config-step-form" method="post" action="/operations-dashboard/signup-review/view.php?id=<?= $applicationId ?>">
            <input type="hidden" name="action" value="mark_config_step" />
            <input type="hidden" name="step" value="<?= htmlspecialchars($step['key']) ?>" />
            <h3 class="admin-form-subhead">Mark complete: <?= htmlspecialchars($step['label']) ?></h3>
            <div class="form-grid form-grid-compact">
              <?php if ($step['key'] === 'clinic'): ?>
              <div class="form-group form-group-inline">
                <label for="accs_company_id_<?= htmlspecialchars($step['key']) ?>">ACCS company ID</label>
                <input class="form-input" type="number" min="1" id="accs_company_id_<?= htmlspecialchars($step['key']) ?>" name="accs_company_id" value="<?= (int) ($application['AccsCompanyId'] ?? 0) > 0 ? (int) $application['AccsCompanyId'] : '' ?>" required />
              </div>
              <div class="form-group form-group-inline">
                <label for="accs_clinic_id_<?= htmlspecialchars($step['key']) ?>">Clinic ID (optional)</label>
                <input class="form-input" type="text" id="accs_clinic_id_<?= htmlspecialchars($step['key']) ?>" name="accs_clinic_id" value="<?= htmlspecialchars((string) ($application['AccsClinicId'] ?? '')) ?>" />
              </div>
              <?php elseif ($step['key'] === 'admin'): ?>
              <div class="form-group form-group-inline">
                <label for="accs_customer_id_<?= htmlspecialchars($step['key']) ?>">ACCS customer ID</label>
                <input class="form-input" type="number" min="1" id="accs_customer_id_<?= htmlspecialchars($step['key']) ?>" name="accs_customer_id" value="<?= (int) ($application['AccsCustomerId'] ?? 0) > 0 ? (int) $application['AccsCustomerId'] : '' ?>" required />
              </div>
              <?php elseif ($step['key'] === 'shared_catalog'): ?>
              <div class="form-group form-group-inline form-group-inline--wide">
                <label for="accs_shared_catalog_id_<?= htmlspecialchars($step['key']) ?>">Shared catalog ID</label>
                <input class="form-input" type="number" min="1" id="accs_shared_catalog_id_<?= htmlspecialchars($step['key']) ?>" name="accs_shared_catalog_id" value="<?= (int) ($application['AccsSharedCatalogId'] ?? 0) > 0 ? (int) $application['AccsSharedCatalogId'] : '' ?>" required />
              </div>
              <?php elseif ($step['key'] === 'catalog_assign'): ?>
              <div class="form-group form-group-inline">
                <label for="accs_catalog_category_count_<?= htmlspecialchars($step['key']) ?>">Category count</label>
                <input class="form-input" type="number" min="0" id="accs_catalog_category_count_<?= htmlspecialchars($step['key']) ?>" name="accs_catalog_category_count" value="<?= $application['AccsCatalogCategoryCount'] !== null ? (int) $application['AccsCatalogCategoryCount'] : '' ?>" />
              </div>
              <div class="form-group form-group-inline">
                <label for="accs_catalog_product_count_<?= htmlspecialchars($step['key']) ?>">Product count</label>
                <input class="form-input" type="number" min="0" id="accs_catalog_product_count_<?= htmlspecialchars($step['key']) ?>" name="accs_catalog_product_count" value="<?= $application['AccsCatalogProductCount'] !== null ? (int) $application['AccsCatalogProductCount'] : '' ?>" />
              </div>
              <?php elseif ($step['key'] === 'roles'): ?>
              <div class="form-group form-group-inline form-group-inline--wide">
                <label for="accs_roles_summary_<?= htmlspecialchars($step['key']) ?>">Roles summary (optional)</label>
                <input class="form-input" type="text" id="accs_roles_summary_<?= htmlspecialchars($step['key']) ?>" name="accs_roles_summary" maxlength="500" placeholder="e.g. Default User, Owner, Company_Admin, Provider, Affiliated Patients" value="<?= htmlspecialchars((string) ($application['AccsRolesSummary'] ?? '')) ?>" />
              </div>
              <?php endif; ?>
            </div>
            <div class="module-actions">
              <button class="btn-secondary" type="submit">Mark <?= htmlspecialchars($step['label']) ?> complete</button>
            </div>
          </form>
          <?php endforeach; ?>
          <?php endif; ?>
        </section>

        <section class="detail-card">
          <h2>Company</h2>
          <dl class="detail-list detail-list-inline">
            <div><dt>Practice name</dt><dd><?= htmlspecialchars((string) ($application['CompanyName'] ?? '—')) ?></dd></div>
            <div><dt>Legal name</dt><dd><?= htmlspecialchars((string) ($application['CompanyLegalName'] ?? '—')) ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars((string) ($application['CompanyEmail'] ?? '—')) ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars((string) ($application['CompanyPhone'] ?? '—')) ?></dd></div>
            <div><dt>Clinic type</dt><dd><?= htmlspecialchars((string) ($application['ClinicType'] ?? '—')) ?></dd></div>
            <div><dt>Address</dt><dd><?= htmlspecialchars(trim(implode(', ', array_filter([
                (string) ($application['StreetAddress'] ?? ''),
                (string) ($application['City'] ?? ''),
                (string) ($application['StateCode'] ?? ''),
                (string) ($application['PostalCode'] ?? ''),
            ])))) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Admin user</h2>
          <dl class="detail-list detail-list-inline">
            <div><dt>Name</dt><dd><?= htmlspecialchars(trim((string) ($application['AdminFirstName'] ?? '') . ' ' . (string) ($application['AdminLastName'] ?? ''))) ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars((string) ($application['AdminEmail'] ?? '—')) ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars((string) ($application['AdminPhone'] ?? '—')) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Compliance &amp; banking</h2>
          <dl class="detail-list detail-list-inline">
            <div><dt>NPI</dt><dd><?= htmlspecialchars((string) ($application['NpiNumber'] ?? '—')) ?></dd></div>
            <div><dt>Tax ID type</dt><dd><?= htmlspecialchars((string) ($application['TaxIdType'] ?? '—')) ?></dd></div>
            <div><dt>Tax ID</dt><dd><?= htmlspecialchars(provider_signup_mask_sensitive($taxId)) ?></dd></div>
            <div><dt>ACH routing</dt><dd><?= htmlspecialchars((string) ($application['AchRoutingNumber'] ?? '—')) ?></dd></div>
            <div><dt>ACH account</dt><dd><?= htmlspecialchars(provider_signup_mask_sensitive($accountNumber)) ?></dd></div>
            <div><dt>ACH account type</dt><dd><?= htmlspecialchars((string) ($application['AchAccountType'] ?? '—')) ?></dd></div>
          </dl>
        </section>

        <?php if ($npiSnapshotBundle !== null): ?>
        <?php $npiSnapshot = $npiSnapshotBundle['snapshot']; ?>
        <section class="detail-card detail-card--wide">
          <h2>NPI registry snapshot</h2>
          <p class="form-hint">Fetched <?= htmlspecialchars(provider_signup_format_datetime($npiSnapshot['FetchedAt'] ?? null)) ?> from CMS NPPES.</p>
          <dl class="detail-list detail-list-inline">
            <div><dt>Registry status</dt><dd><?= htmlspecialchars((string) ($npiSnapshot['RegistryStatus'] ?? '—')) ?></dd></div>
            <div><dt>Enumeration type</dt><dd><?= htmlspecialchars((string) ($npiSnapshot['EnumerationType'] ?? '—')) ?></dd></div>
            <div><dt>Provider name</dt><dd><?= htmlspecialchars((string) ($npiSnapshot['ProviderName'] ?? '—')) ?></dd></div>
            <?php if (!empty($npiSnapshot['OrganizationName'])): ?>
            <div><dt>Organization</dt><dd><?= htmlspecialchars((string) $npiSnapshot['OrganizationName']) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($npiSnapshot['AuthorizedOfficialFirstName']) || !empty($npiSnapshot['AuthorizedOfficialLastName'])): ?>
            <div><dt>Authorized official</dt><dd><?= htmlspecialchars(trim((string) ($npiSnapshot['AuthorizedOfficialFirstName'] ?? '') . ' ' . (string) ($npiSnapshot['AuthorizedOfficialLastName'] ?? ''))) ?><?= !empty($npiSnapshot['AuthorizedOfficialTitle']) ? ' · ' . htmlspecialchars((string) $npiSnapshot['AuthorizedOfficialTitle']) : '' ?></dd></div>
            <?php endif; ?>
            <div><dt>Name match</dt><dd><span class="<?= htmlspecialchars(provider_signup_npi_match_badge_class((string) ($npiSnapshot['NameMatchStatus'] ?? ''))) ?>"><?= htmlspecialchars((string) ($npiSnapshot['NameMatchStatus'] ?? '—')) ?></span></dd></div>
            <div><dt>Address match</dt><dd><span class="<?= htmlspecialchars(provider_signup_npi_match_badge_class((string) ($npiSnapshot['AddressMatchStatus'] ?? ''))) ?>"><?= htmlspecialchars((string) ($npiSnapshot['AddressMatchStatus'] ?? '—')) ?></span></dd></div>
            <div><dt>License match</dt><dd><span class="<?= htmlspecialchars(provider_signup_npi_match_badge_class((string) ($npiSnapshot['LicenseMatchStatus'] ?? ''))) ?>"><?= htmlspecialchars((string) ($npiSnapshot['LicenseMatchStatus'] ?? '—')) ?></span></dd></div>
            <div><dt>Comparison</dt><dd><?= htmlspecialchars((string) ($npiSnapshot['ComparisonSummary'] ?? '—')) ?></dd></div>
          </dl>

          <?php if ($npiSnapshotBundle['addresses'] !== []): ?>
          <h3 class="admin-form-subhead">Registry addresses</h3>
          <div class="detail-grid detail-grid-stacked">
            <?php foreach ($npiSnapshotBundle['addresses'] as $address): ?>
            <div class="detail-list">
              <div><strong><?= htmlspecialchars((string) ($address['AddressPurpose'] ?? 'Address')) ?></strong></div>
              <div><?= htmlspecialchars(trim(implode(', ', array_filter([
                  (string) ($address['Address1'] ?? ''),
                  (string) ($address['Address2'] ?? ''),
                  (string) ($address['City'] ?? ''),
                  (string) ($address['StateCode'] ?? ''),
                  (string) ($address['PostalCode'] ?? ''),
              ])))) ?></div>
              <?php if (!empty($address['TelephoneNumber'])): ?>
              <div>Phone: <?= htmlspecialchars((string) $address['TelephoneNumber']) ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if ($npiSnapshotBundle['taxonomies'] !== []): ?>
          <h3 class="admin-form-subhead">Taxonomy &amp; license</h3>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Primary</th>
                <th>Code</th>
                <th>Description</th>
                <th>License</th>
                <th>State</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($npiSnapshotBundle['taxonomies'] as $taxonomy): ?>
              <tr>
                <td><?= !empty($taxonomy['IsPrimary']) ? 'Yes' : 'No' ?></td>
                <td><?= htmlspecialchars((string) ($taxonomy['TaxonomyCode'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($taxonomy['TaxonomyDescription'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($taxonomy['LicenseNumber'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($taxonomy['LicenseStateCode'] ?? '—')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </section>
        <?php elseif ($canUpdate): ?>
        <section class="detail-card">
          <h2>NPI registry snapshot</h2>
          <p>No NPPES registry data stored yet. Use <strong>Re-run NPI validation</strong> after the application has a valid 10-digit NPI.</p>
        </section>
        <?php endif; ?>

        <section class="detail-card">
          <h2>Documents</h2>
          <?php if ($attachments === []): ?>
          <p>No documents uploaded.</p>
          <?php else: ?>
          <ul>
            <?php foreach ($attachments as $attachment): ?>
            <li>
              <a href="/operations-dashboard/signup-review/attachment.php?id=<?= (int) $attachment['AttachmentID'] ?>">
                <?= htmlspecialchars((string) $attachment['FileName']) ?>
              </a>
              (<?= htmlspecialchars(provider_signup_format_datetime($attachment['UploadDate'] ?? null)) ?>)
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </section>
      </div>

      <?php if ($canUpdate): ?>
      <form class="admin-form" method="post" action="/operations-dashboard/signup-review/view.php?id=<?= $applicationId ?>">
        <h2 class="admin-form-subhead">Reviewer actions</h2>
        <div class="form-group">
          <label for="comments">Comments / return notes</label>
          <textarea class="form-input form-textarea" id="comments" name="comments" rows="4"></textarea>
        </div>
        <?php if ($showReviewOverride): ?>
        <div class="form-group form-group--stacked provider-signup-review-override">
          <label class="checkbox-label">
            <input type="checkbox" name="review_override" value="1" />
            Acknowledge review warnings and proceed
          </label>
          <p class="form-hint">Required to approve or create the Clinic Store while review warnings are present.</p>
        </div>
        <?php endif; ?>
        <div class="module-actions">
          <button class="btn-secondary" type="submit" name="action" value="comment">Add comment</button>
          <button class="btn-secondary" type="submit" name="action" value="validate_npi">Re-run NPI validation</button>
          <button class="btn-secondary" type="submit" name="action" value="return" <?= $canRevert ? '' : 'disabled title="Only applications that are submitted for review, approved, or rejected can be sent back to the provider"' ?>>Return to provider</button>
          <button class="btn-secondary" type="submit" name="action" value="reject">Reject</button>
          <button class="btn-primary" type="submit" name="action" value="approve" <?= $canApprove ? '' : 'disabled title="This application cannot be approved in its current status"' ?>>Approve application</button>
          <button class="btn-primary" type="submit" name="action" value="provision" <?= $canProvision ? '' : 'disabled title="Approve the application before creating the Clinic Store"' ?>>Create Clinic Store</button>
        </div>

        <?php if ($canRevert): ?>
        <h2 class="admin-form-subhead">Send back to provider for edits</h2>
        <p class="form-hint">Use this to undo approval or reopen a rejected application. The provider will be able to edit online again.</p>
        <div class="form-grid">
          <div class="form-group">
            <label for="target_status">New status</label>
            <select class="form-input" id="target_status" name="target_status">
              <option value="<?= htmlspecialchars(PROVIDER_SIGNUP_STATUS_RETURNED) ?>">Returned — provider must address your notes</option>
              <option value="<?= htmlspecialchars(PROVIDER_SIGNUP_STATUS_DRAFT) ?>">Draft — provider can edit again</option>
            </select>
          </div>
        </div>
        <div class="module-actions">
          <button class="btn-secondary" type="submit" name="action" value="revert_status">Apply status change</button>
        </div>
        <?php endif; ?>
      </form>
      <?php endif; ?>

      <section class="admin-table-wrap" style="margin-top: 2rem;">
        <h2 class="admin-form-subhead">Review history</h2>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Action</th>
              <th>Reviewer</th>
              <th>Comments</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($reviewLog === []): ?>
            <tr><td colspan="4">No review activity yet.</td></tr>
            <?php else: ?>
            <?php foreach ($reviewLog as $entry): ?>
            <tr>
              <td><?= htmlspecialchars(provider_signup_format_datetime($entry['LogDate'] ?? null)) ?></td>
              <td><?= htmlspecialchars((string) ($entry['ReviewAction'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($entry['ReviewerName'] ?? 'System')) ?></td>
              <td><?= htmlspecialchars((string) ($entry['Comments'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
