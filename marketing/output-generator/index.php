<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-outputs.php';

auth_require_module_read('research-output');

$activeSlug = 'research-output';
$docHref = '/marketing/output-generator/document.php';

$products = db()->query("SELECT p.ProductID, p.Name, (SELECT COUNT(*) FROM dbo.MktClaim c WHERE c.ProductID = p.ProductID AND c.Status = N'approved') AS Claims
    FROM dbo.MktProduct p ORDER BY p.Name")->fetchAll(PDO::FETCH_ASSOC);
$claims = db()->query("SELECT c.ClaimID, c.ClaimText, c.EvidenceTier, p.Name AS ProductName FROM dbo.MktClaim c JOIN dbo.MktProduct p ON p.ProductID = c.ProductID
    WHERE c.Status = N'approved' ORDER BY p.Name, c.SortOrder, c.ClaimID")->fetchAll(PDO::FETCH_ASSOC);
$topics = db()->query("SELECT TopicID, Title, Status FROM dbo.MktTopic WHERE Status IN (N'accepted', N'proposed', N'parked')
    ORDER BY CASE Status WHEN N'accepted' THEN 0 WHEN N'proposed' THEN 1 ELSE 2 END, UpdatedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
$pieces = db()->query("SELECT ContentID, Title, Stage FROM dbo.MktContent WHERE CurrentVersionID IS NOT NULL AND Stage <> N'archived' ORDER BY UpdatedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
$areas = marketing_setting_lines('taxonomy.therapeutic_areas');
$librarySize = (int) db()->query("SELECT COUNT(*) FROM dbo.MktLitSource WHERE Status = N'active'")->fetchColumn();
$recent = mkt_output_recent(15);

$pageTitle = 'Output Generator | NutraAxis Operations';
$pageDescription = 'Word and PDF documents from the evidence library, claims, topics and content.';
$hubBack = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$options = static function (array $options, string $placeholder = ''): string {
    $html = $placeholder !== '' ? '<option value="">' . htmlspecialchars($placeholder) . '</option>' : '';
    foreach ($options as $value => $label) {
        if (is_array($label)) {
            $html .= '<optgroup label="' . htmlspecialchars((string) $value) . '">';
            foreach ($label as $v => $l) {
                $html .= '<option value="' . htmlspecialchars((string) $v) . '">' . htmlspecialchars((string) $l) . '</option>';
            }
            $html .= '</optgroup>';
            continue;
        }
        $html .= '<option value="' . htmlspecialchars((string) $value) . '">' . htmlspecialchars((string) $label) . '</option>';
    }

    return $html;
};
$buttons = '<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.25rem">'
    . '<button type="submit" name="format" value="print" class="btn-primary" formtarget="_blank">Open (print / PDF)</button>'
    . '<button type="submit" name="format" value="docx" class="btn-secondary">Download Word</button></div>';
$card = static function (string $type, string $fields) use ($docHref, $buttons): void {
    [$title, $purpose] = MKT_OUTPUT_TYPES[$type];
    echo '<form class="admin-form detail-card" method="get" action="' . htmlspecialchars($docHref) . '" style="margin:0">'
        . '<h3 style="margin:0 0 .35rem">' . htmlspecialchars($title) . '</h3>'
        . '<p class="form-hint" style="margin:0 0 .75rem">' . htmlspecialchars($purpose) . '</p>'
        . '<input type="hidden" name="type" value="' . htmlspecialchars($type) . '" />' . $fields . $buttons . '</form>';
};
$claimOptions = [];
foreach ($claims as $c) {
    $claimOptions[(string) $c['ProductName']][(int) $c['ClaimID']] = ucfirst((string) $c['EvidenceTier']) . ': ' . mb_strimwidth((string) $c['ClaimText'], 0, 90, '…');
}
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Output Generator',
          'lead'       => 'Word and PDF documents built from the evidence library, the Claims Matrix, topics and the Content Pipeline — always from the current data.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      ?>

      <div class="status-banner">
        <div>
          <strong>Pick a document, then open it to print or save as PDF, or download it for Word</strong>
          <p class="form-hint">Evidence documents draw on <?= number_format($librarySize) ?> library source<?= $librarySize === 1 ? '' : 's' ?> and the flyer references matched in Literature &amp; Intelligence; the more that are matched, the fuller they are. Every evidence document is marked internal use only.</p>
        </div>
        <div><a class="btn-text" href="/marketing/literature/?tab=flyers">Match flyer references</a></div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:1rem;margin-top:1rem">
        <?php
        $productOptions = [];
        foreach ($products as $p) {
            $productOptions[(int) $p['ProductID']] = $p['Name'] . ' (' . (int) $p['Claims'] . ' claim' . ((int) $p['Claims'] === 1 ? '' : 's') . ')';
        }
        $card('evidence-pack', '<div class="form-group"><label for="o_product">Product</label><select class="form-input" id="o_product" name="product_id" required>' . $options($productOptions, 'Choose a product') . '</select></div>');

        $card('claim', '<div class="form-group"><label for="o_claim">Claim</label><select class="form-input" id="o_claim" name="claim_id" required>' . $options($claimOptions, 'Choose an approved claim') . '</select></div>');

        $topicOptions = [];
        foreach ($topics as $t) {
            $topicOptions[ucfirst((string) $t['Status'])][(int) $t['TopicID']] = mb_strimwidth((string) $t['Title'], 0, 90, '…');
        }
        $card('topic', '<div class="form-group"><label for="o_topic">Topic</label><select class="form-input" id="o_topic" name="topic_id" required>' . $options($topicOptions, 'Choose a topic') . '</select></div>');

        $card('bibliography',
            '<div class="form-group"><label for="o_bprod">Product</label><select class="form-input" id="o_bprod" name="product">' . $options(array_combine(array_column($products, 'Name'), array_column($products, 'Name')), 'All products') . '</select></div>'
            . '<div class="form-group"><label for="o_barea">Therapeutic area</label><select class="form-input" id="o_barea" name="area">' . $options($areas !== [] ? array_combine($areas, $areas) : [], 'All areas') . '</select></div>'
            . '<div class="form-group"><label for="o_blevel">Evidence level</label><select class="form-input" id="o_blevel" name="level">' . $options(MKT_LIT_LEVELS, 'All levels') . '</select></div>');

        $pieceOptions = [];
        foreach ($pieces as $piece) {
            $pieceOptions[(int) $piece['ContentID']] = (in_array($piece['Stage'], ['approved', 'published', 'monitoring'], true) ? 'Approved: ' : (MKT_CONTENT_STAGES[$piece['Stage']] ?? $piece['Stage']) . ': ') . mb_strimwidth((string) $piece['Title'], 0, 80, '…');
        }
        $card('article', '<div class="form-group"><label for="o_piece">Piece</label><select class="form-input" id="o_piece" name="content_id" required>' . $options($pieceOptions, 'Choose a piece') . '</select></div>');
        ?>
      </div>
      <?php marketing_render_field_guide_link('output-generate'); ?>

      <h2 class="section-title" style="margin-top:1.5rem">Recently generated</h2>
      <?php if ($recent === []): ?>
        <p class="form-hint">Nothing generated yet. Documents opened here or from Reports are listed with who made them.</p>
      <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Document</th><th>Format</th><th>By</th><th>When</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td><a href="<?= htmlspecialchars((string) $row['Href']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $row['Title']) ?></a></td>
              <td><?= $row['Format'] === 'docx' ? 'Word' : 'Print / PDF' ?></td>
              <td><?= htmlspecialchars((string) ($row['UserName'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(marketing_format_datetime($row['CreatedAt'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="form-hint">Opening a document again builds it from today's data.</p>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
