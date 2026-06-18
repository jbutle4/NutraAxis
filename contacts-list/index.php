<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/contacts.php';

contacts_require_read();

$activeSlug = 'contacts-list';
$typeFilter = $_GET['type'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'type' => $typeFilter !== '' ? $typeFilter : null,
    'q'    => $search !== '' ? $search : null,
] + table_sort_state(CONTACTS_LIST_SORT_COLUMNS, 'last_name', 'asc', $_GET);
$contacts = contacts_list($listFilters);
$supplierContacts = contacts_list_supplier_contacts();
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Contacts List | NutraAxis Operations';
$pageDescription = 'Maintain business contacts and review supplier directory contact details.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/operations-dashboard.php">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Dashboard
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Operations</div>
          <h1>Contacts List</h1>
          <p class="page-lead">Track suppliers, contractors, education, marketing, and other business contacts used across NutraAxis operations.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(contacts_permission_value())) ?></p>
        </div>
        <?php if (contacts_can_create()): ?>
        <a class="btn-primary" href="/contacts-list/new.php">New Contact</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Contact created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Contact updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Contact deleted successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/contacts-list/">
        <?php table_sort_hidden_inputs($listFilters, 'last_name', 'asc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="type">Contact type</label>
            <select class="form-input" id="type" name="type">
              <option value="">All types</option>
              <?php foreach (CONTACT_TYPES as $value => $label): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= $typeFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, company, email, phone, or supplier" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/contacts-list/">Clear</a>
        </div>
      </form>

      <section class="detail-card">
        <h2>Contacts</h2>
        <?php if ($contacts === []): ?>
        <p>No contacts found.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <?php table_sort_render_head_row(
                  CONTACTS_LIST_SORT_COLUMNS,
                  '/contacts-list',
                  $listFilters,
                  ['type', 'q'],
                  [],
                  'last_name',
                  'asc',
                  '',
                  'View | Edit'
              ); ?>
            </thead>
            <tbody>
              <?php foreach ($contacts as $contact): ?>
              <tr>
                <td><?= htmlspecialchars((string) ($contact['ContactLastName'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($contact['ContactFirstName'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($contact['ContactCompany'] ?? '—')) ?></td>
                <td><?= htmlspecialchars(contacts_type_label($contact['ContactType'] ?? null)) ?></td>
                <td><?= htmlspecialchars((string) ($contact['ContactPhone'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($contact['ContactEmail'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($contact['RelatedSupplierName'] ?? '—')) ?></td>
                <?php table_view_edit_cell(
                    '/contacts-list/view.php?id=' . (int) $contact['ContactID'],
                    '/contacts-list/edit.php?id=' . (int) $contact['ContactID'],
                    contacts_can_update()
                ); ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>

      <section class="detail-card">
        <h2>Supplier Contacts</h2>
        <p class="page-lead">Primary contacts stored on active supplier records in Supplier Management.</p>
        <?php if ($supplierContacts === []): ?>
        <p>No active suppliers found.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Supplier Name</th>
                <th>Contact Name</th>
                <th>Contact Email</th>
                <th>Contact Phone</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($supplierContacts as $supplier): ?>
              <tr>
                <td><?= htmlspecialchars((string) $supplier['SupplierName']) ?></td>
                <td><?= htmlspecialchars((string) ($supplier['ContactName'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($supplier['ContactEmail'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($supplier['ContactPhone'] ?? '—')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
