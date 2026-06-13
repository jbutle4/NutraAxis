<?php
/** @var array $form */
/** @var string $formAction */
/** @var string|null $error */
?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-grid">
          <div class="form-group form-group-wide">
            <label for="subject">Subject</label>
            <input class="form-input" type="text" id="subject" name="subject" value="<?= htmlspecialchars($form['subject'] ?? '') ?>" required maxlength="255" />
          </div>
          <div class="form-group">
            <label for="priority">Priority</label>
            <select class="form-input" id="priority" name="priority">
              <?php foreach (SUPPORT_TICKET_PRIORITIES as $value => $label): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= ($form['priority'] ?? 'normal') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-group-wide">
            <label for="body">Description</label>
            <textarea class="form-input" id="body" name="body" rows="8" required><?= htmlspecialchars($form['body'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Submit Ticket</button>
          <a class="btn-secondary" href="/support/">Cancel</a>
        </div>
      </form>
