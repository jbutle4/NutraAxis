<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/provider-signup.php';

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
            $result = provider_signup_ops_approve($applicationId, $comments);
            if ($result['ok']) {
                $suffix = 'notice=approved';
                if (!empty($result['warn'])) {
                    $suffix .= '&warn=' . rawurlencode((string) $result['warn']);
                }
                header('Location: ' . $redirect . '&' . $suffix, true, 302);
            } else {
                header('Location: ' . $redirect . '&error=' . rawurlencode($result['error'] ?? 'Unable to approve application.'), true, 302);
            }
            exit;
        case 'provision':
            $result = provider_signup_ops_provision($applicationId);
            if ($result['ok']) {
                $suffix = !empty($result['already']) ? 'notice=already_provisioned' : 'notice=provisioned';
                header('Location: ' . $redirect . '&' . $suffix, true, 302);
            } else {
                header('Location: ' . $redirect . '&error=' . rawurlencode($result['error'] ?? 'Unable to create ACCS company.'), true, 302);
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
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars((string) $_GET['error']) ?></div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if (($_GET['notice'] ?? '') === 'commented'): ?>
      <div class="admin-notice is-success" role="status">Comment saved and provider notified.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'returned'): ?>
      <div class="admin-notice is-success" role="status">Application returned to provider.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'approved'): ?>
      <div class="admin-notice is-success" role="status">Application approved. Use <strong>Create ACCS company</strong> when you are ready to provision the Clinic Store.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'provisioned'): ?>
      <div class="admin-notice is-success" role="status">ACCS company creation completed. The provider has been notified by email.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'already_provisioned'): ?>
      <div class="admin-notice is-success" role="status">This application was already provisioned.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'rejected'): ?>
      <div class="admin-notice is-success" role="status">Application rejected.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'npi_validated'): ?>
      <div class="admin-notice is-success" role="status">NPI validation refreshed.</div>
      <?php elseif (($_GET['notice'] ?? '') === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Application data saved.</div>
      <?php endif; ?>
      <?php if (!empty($_GET['warn'])): ?>
      <div class="admin-notice" role="status"><?= htmlspecialchars((string) $_GET['warn']) ?></div>
      <?php endif; ?>

      <?php if ($canApprove && (string) ($application['Status'] ?? '') === PROVIDER_SIGNUP_STATUS_DRAFT): ?>
      <div class="admin-notice" role="status">This application is in <strong>Draft</strong>. Validate the data and documents, approve it, then create the ACCS company from this page.</div>
      <?php endif; ?>

      <?php if ($canProvision): ?>
      <div class="admin-notice" role="status">This application is <strong>Approved</strong> and ready for ACCS company creation.</div>
      <?php endif; ?>

      <?php if (!empty($application['LastProvisionError']) && (string) ($application['Status'] ?? '') === PROVIDER_SIGNUP_STATUS_APPROVED): ?>
      <div class="admin-notice is-error" role="alert">Last ACCS provisioning attempt failed: <?= htmlspecialchars((string) $application['LastProvisionError']) ?></div>
      <?php endif; ?>

      <?php if ($canUpdate && $canEdit && !$approvalChecklist['complete']): ?>
      <div class="admin-notice is-error" role="alert">
        Complete application data is required before approval: <?= htmlspecialchars(implode(', ', $approvalChecklist['missing'])) ?>.
        <a href="/operations-dashboard/signup-review/edit.php?id=<?= $applicationId ?>">Edit application</a>
      </div>
      <?php endif; ?>

      <?php if ($canUpdate && $canEdit): ?>
      <div class="module-actions" style="margin-bottom: 1.5rem;">
        <a class="btn-secondary" href="/operations-dashboard/signup-review/edit.php?id=<?= $applicationId ?>">Edit application</a>
      </div>
      <?php endif; ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Status</h2>
          <dl>
            <div><dt>Status</dt><dd><span class="<?= htmlspecialchars(provider_signup_status_badge_class((string) $application['Status'])) ?>"><?= htmlspecialchars((string) $application['Status']) ?></span></dd></div>
            <div><dt>Activated</dt><dd><?= htmlspecialchars(provider_signup_format_datetime($application['SubmittedAt'] ?? null)) ?></dd></div>
            <div><dt>Last saved</dt><dd><?= htmlspecialchars(provider_signup_format_datetime($application['LastSavedAt'] ?? null)) ?></dd></div>
            <div><dt>NPI validation</dt><dd><?= htmlspecialchars((string) ($application['NpiValidationStatus'] ?? '—')) ?><?= !empty($application['NpiValidationSummary']) ? ' — ' . htmlspecialchars((string) $application['NpiValidationSummary']) : '' ?></dd></div>
            <div><dt>Banking validation</dt><dd><?= htmlspecialchars((string) ($application['BankingValidationStatus'] ?? '—')) ?><?= !empty($application['BankingValidationSummary']) ? ' — ' . htmlspecialchars((string) $application['BankingValidationSummary']) : '' ?></dd></div>
            <div><dt>ACCS company ID</dt><dd><?= htmlspecialchars((string) ($application['AccsCompanyId'] ?? '—')) ?></dd></div>
            <div><dt>ACCS customer ID</dt><dd><?= htmlspecialchars((string) ($application['AccsCustomerId'] ?? '—')) ?></dd></div>
            <div><dt>Clinic ID</dt><dd><?= htmlspecialchars((string) ($application['AccsClinicId'] ?? '—')) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Company</h2>
          <dl>
            <div><dt>Practice name</dt><dd><?= htmlspecialchars((string) ($application['CompanyName'] ?? '—')) ?></dd></div>
            <div><dt>Legal name</dt><dd><?= htmlspecialchars((string) ($application['CompanyLegalName'] ?? '—')) ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars((string) ($application['CompanyEmail'] ?? '—')) ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars((string) ($application['CompanyPhone'] ?? '—')) ?></dd></div>
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
          <dl>
            <div><dt>Name</dt><dd><?= htmlspecialchars(trim((string) ($application['AdminFirstName'] ?? '') . ' ' . (string) ($application['AdminLastName'] ?? ''))) ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars((string) ($application['AdminEmail'] ?? '—')) ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars((string) ($application['AdminPhone'] ?? '—')) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Compliance &amp; banking</h2>
          <dl>
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
          <dl>
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
        <div class="module-actions">
          <button class="btn-secondary" type="submit" name="action" value="comment">Add comment</button>
          <button class="btn-secondary" type="submit" name="action" value="validate_npi">Re-run NPI validation</button>
          <button class="btn-secondary" type="submit" name="action" value="return">Return to provider</button>
          <button class="btn-secondary" type="submit" name="action" value="reject">Reject</button>
          <button class="btn-primary" type="submit" name="action" value="approve" <?= $canApprove ? '' : 'disabled title="This application cannot be approved in its current status"' ?>>Approve application</button>
          <button class="btn-primary" type="submit" name="action" value="provision" <?= $canProvision ? '' : 'disabled title="Approve the application before creating the ACCS company"' ?>>Create ACCS company</button>
        </div>
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
