<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-claims.php';

auth_require_module_read('research-claims');

$activeSlug = 'research-claims';
$listHref = '/marketing/claims-matrix/?tab=products';
$id = (int) ($_GET['id'] ?? 0) ?: null;
$product = $id !== null ? mkt_product_get($id) : null;
if ($id !== null && $product === null) {
    marketing_redirect($listHref, ['notice' => 'Product not found.']);
}
$canEdit = $id === null ? marketing_can_create() : marketing_can_update();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id === null ? marketing_require_create() : marketing_require_update();
    $result = mkt_product_save($_POST, $id);
    if ($result['ok']) {
        marketing_redirect('/marketing/claims-matrix/product.php', ['id' => $result['id'], 'notice' => $id === null ? 'Product added.' : 'Product saved.']);
    }
    $error = $result['error'];
}

$form = [
    'name'             => (string) ($_POST['name'] ?? $product['Name'] ?? ''),
    'therapeutic_area' => (string) ($_POST['therapeutic_area'] ?? $product['TherapeuticArea'] ?? ''),
    'headline'         => (string) ($_POST['headline'] ?? $product['Headline'] ?? ''),
    'summary'          => (string) ($_POST['summary'] ?? $product['Summary'] ?? ''),
    'formula'          => (string) ($_POST['formula'] ?? $product['Formula'] ?? ''),
    'suggested_use'    => (string) ($_POST['suggested_use'] ?? $product['SuggestedUse'] ?? ''),
    'intended_use'     => (string) ($_POST['intended_use'] ?? $product['IntendedUse'] ?? ''),
    'reference_list'   => (string) ($_POST['reference_list'] ?? $product['ReferenceList'] ?? ''),
    'source_document'  => (string) ($_POST['source_document'] ?? $product['SourceDocument'] ?? ''),
    'notes'            => (string) ($_POST['notes'] ?? $product['Notes'] ?? ''),
    'status'           => (string) ($_POST['status'] ?? $product['Status'] ?? 'active'),
];
$areas = marketing_setting_lines('taxonomy.therapeutic_areas');
$claims = $id !== null ? mkt_claims_list(['product_id' => $id]) : [];

$title = $product !== null ? (string) $product['Name'] : 'Add product';
$pageTitle = $title . ' | Claims Matrix | NutraAxis Operations';
$pageDescription = 'Marketing product record and its claims.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$e = static fn(string $key): string => htmlspecialchars($form[$key]);
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $listHref,
          'back_label' => 'Back to Claims Matrix',
          'category'   => 'Marketing & Research',
          'title'      => $title,
          'lead'       => $product !== null ? (string) ($product['Headline'] ?? '') : 'Products anchor claims, product lines, and pillar mapping.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $error);
      if ($product !== null && !empty($product['Notes'])) {
          echo '<div class="admin-notice is-error is-detail" role="note">' . nl2br(htmlspecialchars((string) $product['Notes'])) . '</div>';
      }
      ?>

      <?php if ($canEdit): ?>
      <form class="admin-form" method="post" action="/marketing/claims-matrix/product.php<?= $id !== null ? '?id=' . $id : '' ?>">
        <div class="form-grid">
          <div class="form-group"><label for="name">Name</label><input class="form-input" id="name" name="name" required maxlength="100" value="<?= $e('name') ?>" /></div>
          <div class="form-group">
            <label for="therapeutic_area">Therapeutic area</label>
            <select class="form-input" id="therapeutic_area" name="therapeutic_area">
              <option value="">—</option>
              <?php foreach ($areas as $area): ?>
              <option value="<?= htmlspecialchars($area) ?>" <?= $form['therapeutic_area'] === $area ? 'selected' : '' ?>><?= htmlspecialchars($area) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full"><label for="headline">Headline</label><input class="form-input" id="headline" name="headline" maxlength="300" value="<?= $e('headline') ?>" /></div>
          <div class="form-group form-grid-full"><label for="summary">Summary</label><textarea class="form-input" id="summary" name="summary" rows="3" maxlength="2000"><?= $e('summary') ?></textarea></div>
          <div class="form-group form-grid-full"><label for="formula">Formula (per serving)</label><textarea class="form-input" id="formula" name="formula" rows="4" maxlength="2000"><?= $e('formula') ?></textarea></div>
          <div class="form-group form-grid-full"><label for="suggested_use">Suggested use</label><textarea class="form-input" id="suggested_use" name="suggested_use" rows="2" maxlength="1000"><?= $e('suggested_use') ?></textarea></div>
          <div class="form-group form-grid-full"><label for="intended_use">Intended use (one per line)</label><textarea class="form-input" id="intended_use" name="intended_use" rows="5" maxlength="2000"><?= $e('intended_use') ?></textarea></div>
          <div class="form-group form-grid-full"><label for="reference_list">Flyer references (numbered, one per line)</label><textarea class="form-input" id="reference_list" name="reference_list" rows="8"><?= $e('reference_list') ?></textarea></div>
          <div class="form-group"><label for="source_document">Source document</label><input class="form-input" id="source_document" name="source_document" maxlength="200" value="<?= $e('source_document') ?>" /></div>
          <div class="form-group">
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <?php foreach (MKT_RECORD_STATUSES as $key => $label): ?>
              <option value="<?= $key ?>" <?= $form['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full"><label for="notes">Review notes</label><textarea class="form-input" id="notes" name="notes" rows="2" maxlength="2000"><?= $e('notes') ?></textarea></div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $id !== null ? 'Save product' : 'Add product' ?></button>
          <a class="btn-secondary" href="<?= htmlspecialchars($listHref) ?>">Cancel</a>
        </div>
      </form>
      <?php elseif ($product !== null): ?>
      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <dt>Therapeutic area</dt><dd><?= $e('therapeutic_area') ?: '—' ?></dd>
          <dt>Summary</dt><dd><?= nl2br($e('summary')) ?: '—' ?></dd>
          <dt>Formula</dt><dd><?= nl2br($e('formula')) ?: '—' ?></dd>
          <dt>Suggested use</dt><dd><?= nl2br($e('suggested_use')) ?: '—' ?></dd>
          <dt>Intended use</dt><dd><?= nl2br($e('intended_use')) ?: '—' ?></dd>
          <dt>References</dt><dd><?= nl2br($e('reference_list')) ?: '—' ?></dd>
          <dt>Source</dt><dd><?= $e('source_document') ?: '—' ?></dd>
        </dl>
      </div>
      <?php endif; ?>

      <?php if ($id !== null): ?>
      <h2 class="hub-section-title">Claims (<?= count($claims) ?>)</h2>
      <?php render_list_page_toolbar('<a class="btn-secondary" href="/marketing/claims-matrix/?product_id=' . $id . '">Manage claims</a>'); ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Type</th><th>Claim wording</th><th>Evidence</th><th>Refs</th><th>Status</th></tr></thead>
          <tbody>
            <?php if ($claims === []): ?><tr><td colspan="5">No claims yet.</td></tr><?php endif; ?>
            <?php foreach ($claims as $row): ?>
            <tr>
              <td><?= htmlspecialchars(MKT_CLAIM_TYPES[(string) $row['ClaimType']] ?? (string) $row['ClaimType']) ?></td>
              <td><?= nl2br(htmlspecialchars((string) $row['ClaimText'])) ?></td>
              <td><?= htmlspecialchars(MKT_EVIDENCE_TIERS[(string) $row['EvidenceTier']] ?? '') ?></td>
              <td><?= htmlspecialchars((string) ($row['ReferenceNumbers'] ?? '')) ?></td>
              <td><?= htmlspecialchars(MKT_CLAIM_STATUSES[(string) $row['Status']] ?? (string) $row['Status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
