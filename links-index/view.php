<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/links.php';

links_require_read();

$linkId = (int) ($_GET['id'] ?? 0);
$link = $linkId > 0 ? links_get($linkId) : null;

if ($link === null) {
    header('Location: /links-index/', true, 302);
    exit;
}

$activeSlug = 'links-index';
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;
$href = links_external_url((string) $link['LinkURL']);

$pageTitle = $link['LinkName'] . ' | Links Index';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      ob_start();
      ?>
      <a class="btn-primary" href="<?= htmlspecialchars($href) ?>" <?= links_external_target_attrs() ?>>Open Link</a>
      <?php if (links_can_update()): ?>
      <a class="btn-secondary" href="/links-index/edit.php?id=<?= $linkId ?>">Edit</a>
      <?php endif; ?>
      <?php if (links_can_delete()): ?>
      <form id="link-delete-form" method="post" action="/links-index/delete.php" class="visually-hidden-form" onsubmit="return confirm('Delete this link from the index?');">
        <input type="hidden" name="link_id" value="<?= $linkId ?>" />
      </form>
      <button type="submit" form="link-delete-form" class="btn-danger">Delete</button>
      <?php endif;
      $pageToolbar = ob_get_clean();

      render_list_page_header([
          'back_href'  => '/links-index/',
          'back_label' => 'Back to Links Index',
          'category'   => 'Link',
          'title'      => (string) $link['LinkName'],
          'lead'       => '<span class="status-badge ' . links_status_class((string) $link['LinkStatus']) . '">' . htmlspecialchars(links_status_label((string) $link['LinkStatus'])) . '</span> · ' . htmlspecialchars((string) $link['LinkCategory']),
          'lead_html'  => true,
      ]);
      ?>

      <?php if ($notice === 'created' || $notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Link saved successfully.</div>
      <?php elseif ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $error) ?></div>
      <?php endif; ?>

      <?php render_list_page_toolbar($pageToolbar); ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Link details</h2>
          <dl class="detail-list">
            <div><dt>Name</dt><dd><?= htmlspecialchars((string) $link['LinkName']) ?></dd></div>
            <div><dt>Category</dt><dd><?= htmlspecialchars((string) $link['LinkCategory']) ?></dd></div>
            <div><dt>Status</dt><dd><span class="status-badge <?= links_status_class((string) $link['LinkStatus']) ?>"><?= htmlspecialchars(links_status_label((string) $link['LinkStatus'])) ?></span></dd></div>
            <div><dt>User registration required</dt><dd><?= !empty($link['UserRegistrationRequired']) ? 'Yes' : 'No' ?></dd></div>
            <div><dt>URL</dt><dd><a href="<?= htmlspecialchars($href) ?>" <?= links_external_target_attrs() ?>><?= htmlspecialchars((string) $link['LinkURL']) ?></a></dd></div>
            <div><dt>Description</dt><dd><?= htmlspecialchars((string) ($link['LinkDescription'] ?? '—')) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Record</h2>
          <dl class="detail-list">
            <div><dt>Link ID</dt><dd><?= (int) $link['LinkID'] ?></dd></div>
            <div><dt>Last modified</dt><dd><?= htmlspecialchars((string) ($link['ModifiedByName'] ?? '—')) ?></dd></div>
          </dl>
        </section>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
