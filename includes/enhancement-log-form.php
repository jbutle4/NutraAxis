<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-grid">
          <div class="form-group form-group-wide">
            <label for="enhancement_title">Enhancement title <span class="required">*</span></label>
            <input class="form-input" type="text" id="enhancement_title" name="enhancement_title" maxlength="200" value="<?= htmlspecialchars($form['enhancement_title'] ?? '') ?>" required />
          </div>

          <div class="form-group form-group-wide">
            <label for="enh_desc">Description</label>
            <textarea class="form-input" id="enh_desc" name="enh_desc" rows="5"><?= htmlspecialchars($form['enh_desc'] ?? '') ?></textarea>
          </div>

          <div class="form-group">
            <label for="requested_by">Requested by</label>
            <input class="form-input" type="text" id="requested_by" name="requested_by" maxlength="200" value="<?= htmlspecialchars($form['requested_by'] ?? '') ?>" />
          </div>

          <div class="form-group">
            <label for="request_date">Request date</label>
            <input class="form-input" type="date" id="request_date" name="request_date" value="<?= htmlspecialchars($form['request_date'] ?? '') ?>" />
          </div>

          <div class="form-group">
            <label for="request_status">Status</label>
            <select class="form-input" id="request_status" name="request_status">
              <?php foreach (ENHANCEMENT_LOG_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($form['request_status'] ?? 'New') === $status ? 'selected' : '' ?>>
                <?= htmlspecialchars(enhancement_log_status_label($status)) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="req_due_date">Due date</label>
            <input class="form-input" type="date" id="req_due_date" name="req_due_date" value="<?= htmlspecialchars($form['req_due_date'] ?? '') ?>" />
          </div>

          <div class="form-group form-group-wide">
            <label for="req_notes">Notes</label>
            <textarea class="form-input" id="req_notes" name="req_notes" rows="4"><?= htmlspecialchars($form['req_notes'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Entry' ?></button>
          <?php if ($isEdit && !empty($logId)): ?>
          <a class="btn-secondary" href="/enhancement-log/view.php?id=<?= (int) $logId ?>">Cancel</a>
          <?php else: ?>
          <a class="btn-secondary" href="/enhancement-log/">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
