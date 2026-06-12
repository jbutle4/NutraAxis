<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var array $userOptions */
$isEdit = $isEdit ?? false;
?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-grid">
          <div class="form-group">
            <label for="contract_number">Contract ID</label>
            <input
              class="form-input"
              type="text"
              id="contract_number"
              name="contract_number"
              value="<?= htmlspecialchars($form['contract_number'] ?? '') ?>"
              <?= $isEdit ? 'required' : '' ?>
              placeholder="<?= $isEdit ? '' : 'Auto-generated if blank' ?>"
            />
          </div>
          <div class="form-group">
            <label for="contract_status">Status</label>
            <select class="form-input" id="contract_status" name="contract_status">
              <?php foreach (LEGAL_CONTRACT_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($form['contract_status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="contract_name">Contract name</label>
            <input class="form-input" type="text" id="contract_name" name="contract_name" value="<?= htmlspecialchars($form['contract_name'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label for="counterparty">Counterparty</label>
            <input class="form-input" type="text" id="counterparty" name="counterparty" value="<?= htmlspecialchars($form['counterparty'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label for="contract_type">Contract type</label>
            <select class="form-input" id="contract_type" name="contract_type" required>
              <?php foreach (LEGAL_CONTRACT_TYPES as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= ($form['contract_type'] ?? '') === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="effective_date">Effective date</label>
            <input class="form-input" type="date" id="effective_date" name="effective_date" value="<?= htmlspecialchars($form['effective_date'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="expiration_date">Expiration date</label>
            <input class="form-input" type="date" id="expiration_date" name="expiration_date" value="<?= htmlspecialchars($form['expiration_date'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="expiration_notes">Expiration notes</label>
            <input class="form-input" type="text" id="expiration_notes" name="expiration_notes" value="<?= htmlspecialchars($form['expiration_notes'] ?? '') ?>" placeholder="e.g. Annual renewal, TBD, Ongoing" />
          </div>
          <div class="form-group">
            <label for="auto_renewal">Auto-renewal</label>
            <select class="form-input" id="auto_renewal" name="auto_renewal">
              <option value="0" <?= empty($form['auto_renewal']) ? 'selected' : '' ?>>No</option>
              <option value="1" <?= !empty($form['auto_renewal']) ? 'selected' : '' ?>>Yes</option>
            </select>
          </div>
          <div class="form-group">
            <label for="renewal_notice_days">Renewal notice (days)</label>
            <input class="form-input" type="number" min="0" id="renewal_notice_days" name="renewal_notice_days" value="<?= htmlspecialchars($form['renewal_notice_days'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="annual_value">Annual value ($)</label>
            <input class="form-input" type="number" min="0" step="0.01" id="annual_value" name="annual_value" value="<?= htmlspecialchars($form['annual_value'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="internal_owner_user">Internal owner</label>
            <select class="form-input" id="internal_owner_user" name="internal_owner_user">
              <option value="">Unassigned</option>
              <?php foreach ($userOptions as $option): ?>
              <option value="<?= (int) $option['id'] ?>" <?= (string) ($form['internal_owner_user'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($option['label']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="external_signatory">External signatory</label>
            <input class="form-input" type="text" id="external_signatory" name="external_signatory" value="<?= htmlspecialchars($form['external_signatory'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="related_supplier">Related supplier</label>
            <input class="form-input" type="text" id="related_supplier" name="related_supplier" value="<?= htmlspecialchars($form['related_supplier'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="governing_law">Governing law</label>
            <select class="form-input" id="governing_law" name="governing_law">
              <option value="">Not specified</option>
              <?php foreach (LEGAL_GOVERNING_LAWS as $law): ?>
              <option value="<?= htmlspecialchars($law) ?>" <?= ($form['governing_law'] ?? '') === $law ? 'selected' : '' ?>><?= htmlspecialchars($law) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="confidentiality_months">Confidentiality period (months)</label>
            <input class="form-input" type="number" min="0" id="confidentiality_months" name="confidentiality_months" value="<?= htmlspecialchars($form['confidentiality_months'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="key_obligations">Key obligations (summary)</label>
            <textarea class="form-input" id="key_obligations" name="key_obligations" rows="4"><?= htmlspecialchars($form['key_obligations'] ?? '') ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="document_link">Document link</label>
            <input class="form-input" type="url" id="document_link" name="document_link" value="<?= htmlspecialchars($form['document_link'] ?? '') ?>" placeholder="https://..." />
          </div>
          <div class="form-group form-grid-full">
            <label for="amendment_links">Amendment / addendum links</label>
            <textarea class="form-input" id="amendment_links" name="amendment_links" rows="3"><?= htmlspecialchars($form['amendment_links'] ?? '') ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="4"><?= htmlspecialchars($form['notes'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="module-actions">
          <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Contract' ?></button>
          <a class="btn-secondary" href="<?= $isEdit ? '/legal-agreements/view.php?id=' . (int) ($form['contract_id'] ?? 0) : '/legal-agreements/' ?>">Cancel</a>
        </div>
      </form>
