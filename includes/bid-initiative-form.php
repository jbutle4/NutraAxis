<?php
/** @var array $form */
/** @var bool $isLocked */
$isLocked = $isLocked ?? false;
$owners = bid_list_owner_options();
?>
<div class="form-grid">
  <div class="form-group form-grid-full">
    <label for="title">Title</label>
    <input class="form-input" type="text" id="title" name="title" maxlength="200" required value="<?= htmlspecialchars($form['title'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
  <div class="form-group form-grid-full">
    <label for="description">Description / scope</label>
    <textarea class="form-input" id="description" name="description" rows="4" <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($form['description'] ?? '') ?></textarea>
  </div>
  <div class="form-group">
    <label for="category">Category</label>
    <select class="form-input" id="category" name="category" <?= $isLocked ? 'disabled' : '' ?>>
      <option value="">—</option>
      <?php foreach (BID_INITIATIVE_CATEGORIES as $category): ?>
      <option value="<?= htmlspecialchars($category) ?>" <?= ($form['category'] ?? '') === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($isLocked): ?><input type="hidden" name="category" value="<?= htmlspecialchars($form['category'] ?? '') ?>" /><?php endif; ?>
  </div>
  <div class="form-group">
    <label for="status">Status</label>
    <select class="form-input" id="status" name="status" <?= $isLocked ? 'disabled' : '' ?>>
      <?php foreach (BID_INITIATIVE_STATUSES as $status): ?>
      <option value="<?= htmlspecialchars($status) ?>" <?= ($form['status'] ?? 'Draft') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($isLocked): ?><input type="hidden" name="status" value="<?= htmlspecialchars($form['status'] ?? 'Draft') ?>" /><?php endif; ?>
  </div>
  <div class="form-group">
    <label for="owner_user_id">Owner</label>
    <select class="form-input" id="owner_user_id" name="owner_user_id" <?= $isLocked ? 'disabled' : '' ?>>
      <option value="">—</option>
      <?php foreach ($owners as $owner): ?>
      <option value="<?= (int) $owner['UserID'] ?>" <?= (int) ($form['owner_user_id'] ?? 0) === (int) $owner['UserID'] ? 'selected' : '' ?>><?= htmlspecialchars($owner['UserName']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($isLocked): ?><input type="hidden" name="owner_user_id" value="<?= htmlspecialchars($form['owner_user_id'] ?? '') ?>" /><?php endif; ?>
  </div>
  <div class="form-group">
    <label for="target_award_date">Target award date</label>
    <input class="form-input" type="date" id="target_award_date" name="target_award_date" value="<?= htmlspecialchars($form['target_award_date'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
  <div class="form-group">
    <label for="budget_amount">Budget amount</label>
    <input class="form-input" type="number" min="0" step="0.01" id="budget_amount" name="budget_amount" value="<?= htmlspecialchars($form['budget_amount'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
</div>
