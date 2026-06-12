<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/legal.php';
require dirname(__DIR__) . '/includes/legal-attachments.php';

legal_require_read();

$contractId = (int) ($_GET['id'] ?? 0);
$contract = $contractId > 0 ? legal_get_contract($contractId) : null;

if ($contract === null) {
    header('Location: /legal-agreements/', true, 302);
    exit;
}

$activeSlug = 'legal-agreements';
$notice = $_GET['notice'] ?? null;
$attachments = legal_list_attachments($contractId);

$pageTitle = $contract['ContractNumber'] . ' | Legal Agreements';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/legal-agreements/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Contract Register
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Contract</div>
          <h1><?= htmlspecialchars($contract['ContractName']) ?></h1>
          <p class="page-lead">
            <span class="status-badge <?= legal_status_class($contract['ContractStatus']) ?>"><?= htmlspecialchars($contract['ContractStatus']) ?></span>
            · <?= htmlspecialchars($contract['ContractNumber']) ?>
            · <?= htmlspecialchars($contract['Counterparty']) ?>
          </p>
        </div>
        <div class="admin-actions">
          <?php if (legal_can_update()): ?>
          <a class="btn-primary" href="/legal-agreements/edit.php?id=<?= $contractId ?>">Edit</a>
          <?php endif; ?>
          <?php if (legal_can_delete()): ?>
          <form method="post" action="/legal-agreements/delete.php" class="inline-form" onsubmit="return confirm('Delete this contract from the register?');">
            <input type="hidden" name="contract_id" value="<?= $contractId ?>" />
            <button type="submit" class="btn-text btn-text-danger">Delete</button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($notice === 'created' || $notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Contract saved successfully.</div>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
      <?php endif; ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Agreement details</h2>
          <dl class="detail-list">
            <div><dt>Contract ID</dt><dd><?= htmlspecialchars($contract['ContractNumber']) ?></dd></div>
            <div><dt>Contract type</dt><dd><?= htmlspecialchars($contract['ContractType']) ?></dd></div>
            <div><dt>Counterparty</dt><dd><?= htmlspecialchars($contract['Counterparty']) ?></dd></div>
            <div><dt>Related supplier</dt><dd><?= htmlspecialchars($contract['RelatedSupplier'] ?? '—') ?></dd></div>
            <div><dt>Internal owner</dt><dd><?= htmlspecialchars($contract['InternalOwnerName'] ?? '—') ?></dd></div>
            <div><dt>External signatory</dt><dd><?= htmlspecialchars($contract['ExternalSignatory'] ?? '—') ?></dd></div>
            <div><dt>Governing law</dt><dd><?= htmlspecialchars($contract['GoverningLaw'] ?? '—') ?></dd></div>
            <div><dt>Annual value</dt><dd><?= htmlspecialchars(legal_format_money($contract['AnnualValue'])) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Dates &amp; renewal</h2>
          <dl class="detail-list">
            <div><dt>Effective date</dt><dd><?= htmlspecialchars(legal_format_date($contract['EffectiveDate'])) ?></dd></div>
            <div><dt>Expiration date</dt><dd><?= htmlspecialchars(legal_format_date($contract['ExpirationDate'])) ?></dd></div>
            <div><dt>Expiration notes</dt><dd><?= htmlspecialchars($contract['ExpirationNotes'] ?? '—') ?></dd></div>
            <div><dt>Auto-renewal</dt><dd><?= !empty($contract['AutoRenewal']) ? 'Yes' : 'No' ?></dd></div>
            <div><dt>Renewal notice</dt><dd><?= $contract['RenewalNoticeDays'] !== null ? (int) $contract['RenewalNoticeDays'] . ' days' : '—' ?></dd></div>
            <div><dt>Confidentiality period</dt><dd><?= $contract['ConfidentialityMonths'] !== null ? (int) $contract['ConfidentialityMonths'] . ' months' : '—' ?></dd></div>
            <div><dt>Last modified</dt><dd><?= htmlspecialchars(admin_format_datetime($contract['ModifiedDate'])) ?><?= !empty($contract['ModifiedByName']) ? ' by ' . htmlspecialchars($contract['ModifiedByName']) : '' ?></dd></div>
          </dl>
        </section>

        <?php if (!empty($contract['KeyObligationsSummary'])): ?>
        <section class="detail-card">
          <h2>Key obligations</h2>
          <p><?= nl2br(htmlspecialchars($contract['KeyObligationsSummary'])) ?></p>
        </section>
        <?php endif; ?>

        <section class="detail-card">
          <h2>Documents &amp; notes</h2>
          <dl class="detail-list">
            <div>
              <dt>Document link</dt>
              <dd>
                <?php if (!empty($contract['DocumentLink'])): ?>
                <a href="<?= htmlspecialchars($contract['DocumentLink']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($contract['DocumentLink']) ?></a>
                <?php else: ?>
                —
                <?php endif; ?>
              </dd>
            </div>
          </dl>
          <?php if (!empty($contract['AmendmentLinks'])): ?>
          <h3 class="production-line-header">Amendments / addenda</h3>
          <p><?= nl2br(htmlspecialchars($contract['AmendmentLinks'])) ?></p>
          <?php endif; ?>
          <?php if (!empty($contract['Notes'])): ?>
          <h3 class="production-line-header">Notes</h3>
          <p><?= nl2br(htmlspecialchars($contract['Notes'])) ?></p>
          <?php endif; ?>
        </section>
      </div>

      <?php
        $showUploadForm = false;
        require dirname(__DIR__) . '/includes/legal-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
