<?php
/** @var string $formAction */
/** @var array{State: string, ZipCode: string, County: string, Rep: string} $form */
/** @var bool $isEdit */
/** @var int $assignmentId */
?>
<form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
  <div class="form-group">
    <label for="State">State</label>
    <div class="form-field">
      <select class="form-input" id="State" name="State" required>
        <option value="">Select state</option>
        <?php foreach (CONTACT_US_STATES as $code => $label): ?>
        <option value="<?= htmlspecialchars($code) ?>" <?= ($form['State'] ?? '') === $code ? 'selected' : '' ?>>
          <?= htmlspecialchars($code . ' — ' . $label) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-group">
    <label for="ZipCode">Zip Code</label>
    <div class="form-field">
      <input class="form-input" type="text" id="ZipCode" name="ZipCode" maxlength="10" required
             value="<?= htmlspecialchars((string) ($form['ZipCode'] ?? '')) ?>"
             placeholder="75035 or All" />
    </div>
  </div>

  <div class="form-group">
    <label for="County">County</label>
    <div class="form-field">
      <input class="form-input" type="text" id="County" name="County" maxlength="100" required
             value="<?= htmlspecialchars((string) ($form['County'] ?? '')) ?>"
             placeholder="Collin or All" />
    </div>
  </div>

  <div class="form-group">
    <label for="Rep">Rep</label>
    <div class="form-field">
      <input class="form-input" type="text" id="Rep" name="Rep" maxlength="200" required
             value="<?= htmlspecialchars((string) ($form['Rep'] ?? '')) ?>"
             placeholder="Sales rep name" />
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn-primary"><?= !empty($isEdit) ? 'Save changes' : 'Create assignment' ?></button>
    <a class="btn-secondary" href="/sales-rep-territory-assignment/">Cancel</a>
  </div>
</form>
