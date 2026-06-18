<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/contacts.php';

contacts_require_read();

$contactId = (int) ($_GET['id'] ?? 0);
$contact = $contactId > 0 ? contacts_get($contactId) : null;

if ($contact === null) {
    header('Location: /contacts-list/', true, 302);
    exit;
}

$activeSlug = 'contacts-list';
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;

$pageTitle = contacts_display_name($contact) . ' | Contacts List';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/contacts-list/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Contacts List
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Contact</div>
          <h1><?= htmlspecialchars(contacts_display_name($contact)) ?></h1>
          <p class="page-lead">
            <?= htmlspecialchars(contacts_type_label($contact['ContactType'] ?? null)) ?>
            <?php if (!empty($contact['ContactCompany'])): ?>
            · <?= htmlspecialchars((string) $contact['ContactCompany']) ?>
            <?php endif; ?>
          </p>
        </div>
        <div class="admin-actions">
          <?php if (contacts_can_update()): ?>
          <a class="btn-secondary" href="/contacts-list/edit.php?id=<?= $contactId ?>">Edit</a>
          <?php endif; ?>
          <?php if (contacts_can_delete()): ?>
          <form id="contact-delete-form" method="post" action="/contacts-list/delete.php" class="visually-hidden-form" onsubmit="return confirm('Delete this contact?');">
            <input type="hidden" name="contact_id" value="<?= $contactId ?>" />
          </form>
          <button type="submit" form="contact-delete-form" class="btn-danger">Delete</button>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($notice === 'created' || $notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Contact saved successfully.</div>
      <?php elseif ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $error) ?></div>
      <?php endif; ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Contact details</h2>
          <dl class="detail-list">
            <div><dt>First name</dt><dd><?= htmlspecialchars((string) ($contact['ContactFirstName'] ?? '—')) ?></dd></div>
            <div><dt>Last name</dt><dd><?= htmlspecialchars((string) ($contact['ContactLastName'] ?? '—')) ?></dd></div>
            <div><dt>Company</dt><dd><?= htmlspecialchars((string) ($contact['ContactCompany'] ?? '—')) ?></dd></div>
            <div><dt>Contact type</dt><dd><?= htmlspecialchars(contacts_type_label($contact['ContactType'] ?? null)) ?></dd></div>
            <div><dt>Related supplier</dt><dd><?= htmlspecialchars((string) ($contact['RelatedSupplierName'] ?? '—')) ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars((string) ($contact['ContactPhone'] ?? '—')) ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars((string) ($contact['ContactEmail'] ?? '—')) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Address</h2>
          <dl class="detail-list">
            <div><dt>Street</dt><dd><?= htmlspecialchars((string) ($contact['ContactAddress'] ?? '—')) ?></dd></div>
            <div><dt>City</dt><dd><?= htmlspecialchars((string) ($contact['ContactCity'] ?? '—')) ?></dd></div>
            <div><dt>State</dt><dd><?= htmlspecialchars(contacts_state_label($contact['ContactState'] ?? null)) ?></dd></div>
            <div><dt>ZIP</dt><dd><?= htmlspecialchars((string) ($contact['ContactZip'] ?? '—')) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Notes</h2>
          <p><?= nl2br(htmlspecialchars((string) ($contact['ContactNotes'] ?? '—'))) ?></p>
        </section>

        <section class="detail-card">
          <h2>Record</h2>
          <dl class="detail-list">
            <div><dt>Contact ID</dt><dd><?= (int) $contact['ContactID'] ?></dd></div>
            <div><dt>Last modified by</dt><dd><?= htmlspecialchars((string) ($contact['ModifiedByName'] ?? '—')) ?></dd></div>
          </dl>
        </section>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
