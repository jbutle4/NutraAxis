<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var int|null $linkId */
$isEdit = $isEdit ?? false;
$linkId = isset($linkId) ? (int) $linkId : 0;
?>
      <div class="admin-form">
      <?php if ($isEdit && $linkId > 0 && links_can_delete()): ?>
      <form id="link-delete-form" method="post" action="/links-index/delete.php" class="visually-hidden-form" onsubmit="return confirm('Delete this link from the index?');">
        <input type="hidden" name="link_id" value="<?= $linkId ?>" />
      </form>
      <?php endif; ?>
      <form id="link-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="link_name">Link name</label>
            <input class="form-input" type="text" id="link_name" name="link_name" value="<?= htmlspecialchars($form['link_name'] ?? '') ?>" required />
          </div>
          <div class="form-group form-grid-full">
            <label for="link_description">Description</label>
            <textarea class="form-input" id="link_description" name="link_description" rows="3"><?= htmlspecialchars($form['link_description'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label for="link_category">Category</label>
            <select class="form-input" id="link_category" name="link_category" required>
              <?php foreach (LINK_CATEGORIES as $category): ?>
              <option value="<?= htmlspecialchars($category) ?>" <?= ($form['link_category'] ?? '') === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="link_status">Status</label>
            <select class="form-input" id="link_status" name="link_status">
              <?php foreach (LINK_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($form['link_status'] ?? 'active') === $status ? 'selected' : '' ?>><?= htmlspecialchars(links_status_label($status)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="user_registration_required">User registration required</label>
            <select class="form-input" id="user_registration_required" name="user_registration_required">
              <option value="0" <?= empty($form['user_registration_required']) ? 'selected' : '' ?>>No</option>
              <option value="1" <?= !empty($form['user_registration_required']) ? 'selected' : '' ?>>Yes</option>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="link_url">Link URL</label>
            <input class="form-input" type="url" id="link_url" name="link_url" value="<?= htmlspecialchars($form['link_url'] ?? '') ?>" required placeholder="https://example.com" />
          </div>
        </div>
      </form>
      <div class="form-actions">
        <button type="submit" form="link-form" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Link' ?></button>
        <a class="btn-secondary" href="<?= $isEdit && $linkId > 0 ? '/links-index/view.php?id=' . $linkId : '/links-index/' ?>">Cancel</a>
        <?php if ($isEdit && $linkId > 0 && links_can_delete()): ?>
        <button type="submit" form="link-delete-form" class="btn-danger">Delete</button>
        <?php endif; ?>
      </div>
      </div>
