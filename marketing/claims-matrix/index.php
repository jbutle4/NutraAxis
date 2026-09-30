<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-claims.php';

auth_require_module_read('research-claims');

$activeSlug = 'research-claims';
$baseHref = '/marketing/claims-matrix/';
$tabs = ['claims' => 'Claims', 'products' => 'Products', 'rules' => 'Claims check rules'];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'claims';
$error = null;
$editId = (int) ($_GET['edit'] ?? 0) ?: null;
$editing = $editId !== null ? mkt_claim_get($editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $returnQuery = array_filter(['product_id' => (string) ($_POST['return_product'] ?? '')]);
    if ($action === 'save') {
        $id = (int) ($_POST['claim_id'] ?? 0) ?: null;
        $id === null ? marketing_require_create() : marketing_require_update();
        $result = mkt_claim_save($_POST, $id);
        if ($result['ok']) {
            $notice = $id === null ? 'Claim added as draft.' : ($result['reapproval'] ? 'Claim updated — wording changed, so it is back in draft for re-approval.' : 'Claim updated.');
            marketing_redirect($baseHref, $returnQuery + ['notice' => $notice]);
        }
        $error = $result['error'];
        $editing = array_merge($id !== null ? (mkt_claim_get($id) ?? []) : [], [
            'ClaimID' => $id, 'ProductID' => $_POST['product_id'] ?? null, 'ClaimText' => $_POST['claim_text'] ?? '',
            'ClaimType' => $_POST['claim_type'] ?? 'benefit', 'Ingredient' => $_POST['ingredient'] ?? '',
            'EvidenceTier' => $_POST['evidence_tier'] ?? 'clinical', 'ReferenceNumbers' => $_POST['reference_numbers'] ?? '',
            'Audience' => $_POST['audience'] ?? 'both', 'RequiresDisclaimer' => !empty($_POST['requires_disclaimer']),
            'SourceDocument' => $_POST['source_document'] ?? '', 'Notes' => $_POST['notes'] ?? '',
        ]);
        if ($id === null) {
            $editing['ClaimID'] = null;
        }
    }
    if ($action === 'approve') {
        marketing_require_admin();
        $result = mkt_claim_approve((int) ($_POST['claim_id'] ?? 0));
        if ($result['ok']) {
            marketing_redirect($baseHref, $returnQuery + ['notice' => 'Claim approved.']);
        }
        $error = $result['error'];
    }
}

$products = mkt_products_list();
$productNames = array_column($products, 'Name', 'ProductID');
$areas = marketing_setting_lines('taxonomy.therapeutic_areas');
$filters = [
    'product_id' => (string) ($_GET['product_id'] ?? ''),
    'area'       => (string) ($_GET['area'] ?? ''),
    'type'       => (string) ($_GET['type'] ?? ''),
    'status'     => (string) ($_GET['status'] ?? ''),
    'evidence'   => (string) ($_GET['evidence'] ?? ''),
    'q'          => trim((string) ($_GET['q'] ?? '')),
];
$claims = $tab === 'claims' ? mkt_claims_list($filters) : [];
$showForm = $tab === 'claims' && (marketing_can_create() || ($editing !== null && marketing_can_update()));

$pageTitle = 'Claims Matrix | NutraAxis Operations';
$pageDescription = 'Approved claim wording per product, evidence tiers, and claims-check rules.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$val = static fn(string $key, $default = '') => htmlspecialchars((string) ($editing[$key] ?? $default));
$select = static function (string $name, array $options, string $current, string $id = ''): void {
    echo '<select class="form-input" id="' . htmlspecialchars($id ?: $name) . '" name="' . htmlspecialchars($name) . '">';
    foreach ($options as $key => $label) {
        echo '<option value="' . htmlspecialchars((string) $key) . '"' . ((string) $key === $current ? ' selected' : '') . '>' . htmlspecialchars((string) $label) . '</option>';
    }
    echo '</select>';
};
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Claims Matrix',
          'lead'       => 'The only claim wording generated content may use. Seeded from the practitioner flyers; drafts need approval by a full Marketing admin who did not edit them.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $error);
      marketing_render_tabs($baseHref, $tabs, $tab);
      ?>

      <?php if ($tab === 'claims'): ?>
        <?php if ($showForm): ?>
        <h2 class="hub-section-title"><?= !empty($editing['ClaimID']) ? 'Edit claim' : 'Add claim' ?></h2>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>">
          <input type="hidden" name="action" value="save" />
          <input type="hidden" name="return_product" value="<?= htmlspecialchars($filters['product_id']) ?>" />
          <?php if (!empty($editing['ClaimID'])): ?><input type="hidden" name="claim_id" value="<?= (int) $editing['ClaimID'] ?>" /><?php endif; ?>
          <?php marketing_render_field_guide_link('claim'); ?>
          <div class="form-grid">
            <div class="form-group">
              <label for="product_id">Product</label>
              <?php $select('product_id', ['' => 'General (no product)'] + $productNames, (string) ($editing['ProductID'] ?? $filters['product_id'])); ?>
            </div>
            <div class="form-group">
              <label for="claim_type">Type</label>
              <?php $select('claim_type', MKT_CLAIM_TYPES, (string) ($editing['ClaimType'] ?? 'benefit')); ?>
            </div>
            <div class="form-group form-grid-full"><label for="claim_text">Claim wording</label><textarea class="form-input" id="claim_text" name="claim_text" rows="3" maxlength="1000" required><?= $val('ClaimText') ?></textarea></div>
            <div class="form-group"><label for="ingredient">Ingredient</label><input class="form-input" id="ingredient" name="ingredient" maxlength="150" value="<?= $val('Ingredient') ?>" /></div>
            <div class="form-group">
              <label for="evidence_tier">Evidence tier</label>
              <?php $select('evidence_tier', MKT_EVIDENCE_TIERS, (string) ($editing['EvidenceTier'] ?? 'clinical')); ?>
            </div>
            <div class="form-group"><label for="reference_numbers">Flyer references</label><input class="form-input" id="reference_numbers" name="reference_numbers" maxlength="100" value="<?= $val('ReferenceNumbers') ?>" placeholder="e.g. 1-3, 7-8" /></div>
            <div class="form-group">
              <label for="audience">Audience</label>
              <?php $select('audience', MKT_CLAIM_AUDIENCES, (string) ($editing['Audience'] ?? 'both')); ?>
            </div>
            <div class="form-group">
              <label for="claim_status">Status</label>
              <?php
              $current = (string) ($editing['Status'] ?? 'draft');
              $statusOptions = $current === 'approved' ? ['approved' => 'Approved (edits return it to draft)', 'retired' => 'Retired'] : ['draft' => 'Draft', 'retired' => 'Retired'];
              $select('status', $statusOptions, $current, 'claim_status');
              ?>
            </div>
            <div class="form-group"><label for="source_document">Source document</label><input class="form-input" id="source_document" name="source_document" maxlength="200" value="<?= $val('SourceDocument') ?>" /></div>
            <div class="form-group form-group--stacked">
              <label><input type="checkbox" name="requires_disclaimer" value="1" <?= !array_key_exists('RequiresDisclaimer', $editing ?? []) || !empty($editing['RequiresDisclaimer']) ? 'checked' : '' ?> /> Requires DSHEA disclaimer</label>
            </div>
            <div class="form-group form-grid-full"><label for="notes">Notes</label><input class="form-input" id="notes" name="notes" maxlength="1000" value="<?= $val('Notes') ?>" /></div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary"><?= !empty($editing['ClaimID']) ? 'Save claim' : 'Add claim' ?></button>
            <?php if ($editing !== null): ?><a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>">Cancel</a><?php endif; ?>
          </div>
        </form>
        <?php endif; ?>

        <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
          <div class="audit-filter-grid">
            <div><label for="f_product">Product</label><?php $select('product_id', ['' => 'All', 'general' => 'General (no product)'] + $productNames, $filters['product_id'], 'f_product'); ?></div>
            <div><label for="f_area">Therapeutic area</label><?php $select('area', ['' => 'All'] + array_combine($areas, $areas), $filters['area'], 'f_area'); ?></div>
            <div><label for="f_type">Type</label><?php $select('type', ['' => 'All'] + MKT_CLAIM_TYPES, $filters['type'], 'f_type'); ?></div>
            <div><label for="f_evidence">Evidence</label><?php $select('evidence', ['' => 'All'] + MKT_EVIDENCE_TIERS, $filters['evidence'], 'f_evidence'); ?></div>
            <div><label for="f_status">Status</label><?php $select('status', ['' => 'All'] + MKT_CLAIM_STATUSES, $filters['status'], 'f_status'); ?></div>
            <div class="audit-filter-wide"><label for="f_q">Search</label><input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Wording, ingredient, product, or notes" /></div>
          </div>
          <div class="audit-filter-actions">
            <button type="submit" class="btn-primary">Apply Filters</button>
            <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>">Clear</a>
          </div>
        </form>

        <p class="form-hint"><?= count($claims) ?> claim(s). Reference numbers point to the product's flyer reference list (Products tab).</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Product</th><th>Type</th><th>Claim wording</th><th>Ingredient</th><th>Evidence</th><th>Refs</th><th>Audience</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if ($claims === []): ?>
              <tr><td colspan="9">No claims match.</td></tr>
              <?php endif; ?>
              <?php foreach ($claims as $row): ?>
              <tr>
                <td><?= htmlspecialchars((string) ($row['ProductName'] ?? 'General')) ?></td>
                <td><?= htmlspecialchars(MKT_CLAIM_TYPES[(string) $row['ClaimType']] ?? (string) $row['ClaimType']) ?></td>
                <td>
                  <?= nl2br(htmlspecialchars((string) $row['ClaimText'])) ?><?= !empty($row['RequiresDisclaimer']) ? ' <span title="Requires DSHEA disclaimer">‡</span>' : '' ?>
                  <?php if (!empty($row['Notes'])): ?><div class="form-hint"><?= htmlspecialchars((string) $row['Notes']) ?></div><?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) ($row['Ingredient'] ?? '')) ?></td>
                <td><?= htmlspecialchars(MKT_EVIDENCE_TIERS[(string) $row['EvidenceTier']] ?? (string) $row['EvidenceTier']) ?></td>
                <td><?= htmlspecialchars((string) ($row['ReferenceNumbers'] ?? '')) ?></td>
                <td><?= htmlspecialchars(MKT_CLAIM_AUDIENCES[(string) $row['Audience']] ?? (string) $row['Audience']) ?></td>
                <td><?= mkt_render_badge((string) $row['Status'] === 'approved' ? 'active' : ((string) $row['Status'] === 'retired' ? 'retired' : 'draft'), ['active' => 'Approved', 'retired' => 'Retired', 'draft' => 'Draft']) ?></td>
                <td>
                  <?php if (marketing_can_update()): ?><a href="<?= htmlspecialchars($baseHref) ?>?<?= htmlspecialchars(http_build_query(array_filter(['edit' => (int) $row['ClaimID'], 'product_id' => $filters['product_id']]))) ?>">Edit</a><?php endif; ?>
                  <?php if ((string) $row['Status'] === 'draft' && marketing_can_admin()): ?>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
                    <input type="hidden" name="action" value="approve" />
                    <input type="hidden" name="claim_id" value="<?= (int) $row['ClaimID'] ?>" />
                    <input type="hidden" name="return_product" value="<?= htmlspecialchars($filters['product_id']) ?>" />
                    <button type="submit" class="btn-text">Approve</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'products'): ?>
        <?php if (marketing_can_create()): ?>
        <?php render_list_page_toolbar('<a class="btn-primary" href="/marketing/claims-matrix/product.php">Add product</a>'); ?>
        <?php endif; ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Product</th><th>Therapeutic area</th><th>Headline</th><th>Formula</th><th>Approved claims</th><th>Draft claims</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if ($products === []): ?>
              <tr><td colspan="8">No products yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($products as $row): ?>
              <tr>
                <td><?= htmlspecialchars((string) $row['Name']) ?><?php if (!empty($row['Notes'])): ?> <span title="<?= htmlspecialchars((string) $row['Notes']) ?>">⚠</span><?php endif; ?></td>
                <td><?= htmlspecialchars((string) ($row['TherapeuticArea'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['Headline'] ?? '')) ?></td>
                <td><?= htmlspecialchars(mb_strimwidth((string) ($row['Formula'] ?? ''), 0, 120, '…')) ?></td>
                <td><a href="<?= htmlspecialchars($baseHref) ?>?<?= htmlspecialchars(http_build_query(['product_id' => (int) $row['ProductID'], 'status' => 'approved'])) ?>"><?= (int) $row['ApprovedClaims'] ?></a></td>
                <td><a href="<?= htmlspecialchars($baseHref) ?>?<?= htmlspecialchars(http_build_query(['product_id' => (int) $row['ProductID'], 'status' => 'draft'])) ?>"><?= (int) $row['DraftClaims'] ?></a></td>
                <td><?= mkt_render_badge((string) $row['Status'], MKT_RECORD_STATUSES) ?></td>
                <td><a href="/marketing/claims-matrix/product.php?id=<?= (int) $row['ProductID'] ?>"><?= marketing_can_update() ? 'View / edit' : 'View' ?></a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php else: ?>
        <?php $flagTerms = mkt_claims_flag_terms(); ?>
        <div class="detail-card">
          <h2 class="hub-section-title">How the claims check uses this matrix</h2>
          <p>Every Campaign Studio and Content Pipeline asset is checked against the <strong>approved</strong> claims for the products it mentions. Wording outside the matrix, or any flag term below, lowers the score and routes the asset to medical review. Assets scoring below <strong><?= htmlspecialchars((string) marketing_setting('claims.min_score', '7')) ?></strong> cannot leave draft.</p>
          <dl class="detail-list detail-list-inline">
            <dt>DSHEA disclaimer</dt><dd><?= htmlspecialchars((string) marketing_setting('claims.disclaimer', '')) ?></dd>
            <dt>Flag terms (<?= count($flagTerms) ?>)</dt><dd><?= htmlspecialchars(implode(', ', $flagTerms)) ?></dd>
          </dl>
          <?php if (marketing_can_admin()): ?><p><a href="/marketing/admin/?tab=settings">Edit these in Admin &amp; Jobs → Settings</a> (<code>claims.*</code>).</p><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
