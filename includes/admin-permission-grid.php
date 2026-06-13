<?php
/** @var array $role */
/** @var bool $editable */
$editable = $editable ?? true;
?>
<div class="permission-grid">
  <div class="permission-grid-head">
    <span>Area</span>
    <span>Create</span>
    <span>Read</span>
    <span>Update</span>
    <span>Delete</span>
  </div>
  <?php foreach (ROLE_PERMISSION_FIELDS as $column => $label): ?>
  <?php $flags = permission_flags($role[$column] ?? null); ?>
  <div class="permission-grid-row">
    <span class="permission-grid-label"><?= htmlspecialchars($label) ?></span>
    <?php foreach (['C', 'R', 'U', 'D'] as $action): ?>
    <span class="permission-grid-cell">
      <?php if ($editable): ?>
      <label class="perm-check">
        <input
          type="checkbox"
          name="permissions[<?= htmlspecialchars($column) ?>][<?= $action ?>]"
          value="1"
          <?= $flags[$action] ? 'checked' : '' ?>
        />
        <span><?= htmlspecialchars(PERMISSION_ACTIONS[$action]) ?></span>
      </label>
      <?php else: ?>
      <span class="perm-readonly <?= $flags[$action] ? 'is-on' : '' ?>">
        <?= $flags[$action] ? 'Yes' : '—' ?>
      </span>
      <?php endif; ?>
    </span>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>
