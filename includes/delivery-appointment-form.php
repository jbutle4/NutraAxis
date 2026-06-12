<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var array $poOptions */
/** @var array $porOptions */
/** @var array $supplierOptions */
/** @var array $returnContext */
$isEdit = $isEdit ?? false;
$returnContext = $returnContext ?? das_return_context_from_query();
?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnContext['return_to'] ?? '') ?>" />
        <input type="hidden" name="por_id" value="<?= (int) ($returnContext['por_id'] ?? 0) ?>" />
        <input type="hidden" name="jazz_asn_id" value="<?= htmlspecialchars($returnContext['jazz_asn_id'] ?? '') ?>" />

        <div class="form-grid">
          <div class="form-group">
            <label for="po_id">Purchase order</label>
            <select class="form-input" id="po_id" name="po_id" required>
              <option value="">Select PO</option>
              <?php foreach ($poOptions as $option): ?>
              <option value="<?= (int) $option['id'] ?>" <?= (string) ($form['po_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($option['label']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="po_receipt_id">PO receipt</label>
            <select class="form-input" id="po_receipt_id" name="po_receipt_id">
              <option value="">None</option>
              <?php foreach ($porOptions as $option): ?>
              <option
                value="<?= (int) $option['id'] ?>"
                data-po-id="<?= (int) ($option['po_id'] ?? 0) ?>"
                data-jazz-asn="<?= htmlspecialchars($option['jazz_asn'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                <?= (string) ($form['po_receipt_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>
              ><?= htmlspecialchars($option['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="supplier_id">Supplier</label>
            <select class="form-input" id="supplier_id" name="supplier_id" required>
              <option value="">Select supplier</option>
              <?php foreach ($supplierOptions as $option): ?>
              <option
                value="<?= (int) $option['id'] ?>"
                data-company-name="<?= htmlspecialchars($option['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-contact-name="<?= htmlspecialchars($option['contact_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-contact-email="<?= htmlspecialchars($option['contact_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-contact-phone="<?= htmlspecialchars($option['contact_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                <?= (string) ($form['supplier_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>
              ><?= htmlspecialchars($option['label']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="form-hint">Company and contact fields update when you select a supplier. ASN fields update when you select a PO receipt.</p>
          </div>
          <div class="form-group">
            <label for="appointment_status">Appointment status</label>
            <select class="form-input" id="appointment_status" name="appointment_status" required>
              <?php foreach (DAS_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($form['appointment_status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="company_name">Company name</label>
            <input class="form-input" type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($form['company_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_name">Contact name</label>
            <input class="form-input" type="text" id="contact_name" name="contact_name" value="<?= htmlspecialchars($form['contact_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_email">Contact email</label>
            <input class="form-input" type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($form['contact_email'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_phone">Contact phone</label>
            <input class="form-input" type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($form['contact_phone'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="appointment_datetime">Appointment date/time</label>
            <input class="form-input" type="datetime-local" id="appointment_datetime" name="appointment_datetime" value="<?= htmlspecialchars($form['appointment_datetime'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="appointment_company_name">Appointment company / facility</label>
            <input class="form-input" type="text" id="appointment_company_name" name="appointment_company_name" value="<?= htmlspecialchars($form['appointment_company_name'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="appointment_address">Appointment address</label>
            <textarea class="form-input" id="appointment_address" name="appointment_address" rows="3"><?= htmlspecialchars($form['appointment_address'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label for="receiving_company_contact">Receiving company contact</label>
            <input class="form-input" type="text" id="receiving_company_contact" name="receiving_company_contact" value="<?= htmlspecialchars($form['receiving_company_contact'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="receiving_company_email">Receiving company email</label>
            <input class="form-input" type="email" id="receiving_company_email" name="receiving_company_email" value="<?= htmlspecialchars($form['receiving_company_email'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="receiving_company_phone">Receiving company phone</label>
            <input class="form-input" type="text" id="receiving_company_phone" name="receiving_company_phone" value="<?= htmlspecialchars($form['receiving_company_phone'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="appointment_asn_created">ASN created</label>
            <select class="form-input" id="appointment_asn_created" name="appointment_asn_created">
              <option value="0" <?= empty($form['appointment_asn_created']) ? 'selected' : '' ?>>No</option>
              <option value="1" <?= !empty($form['appointment_asn_created']) ? 'selected' : '' ?>>Yes</option>
            </select>
          </div>
          <div class="form-group">
            <label for="appointment_asn_number">ASN number</label>
            <input class="form-input" type="text" id="appointment_asn_number" name="appointment_asn_number" value="<?= htmlspecialchars($form['appointment_asn_number'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="appointment_notes">Appointment notes</label>
            <textarea class="form-input" id="appointment_notes" name="appointment_notes" rows="5"><?= htmlspecialchars($form['appointment_notes'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">Save appointment</button>
          <?php if ($isEdit): ?>
          <a class="btn-secondary" href="/delivery-scheduling-log/view.php?id=<?= (int) ($form['appt_id'] ?? 0) ?><?= htmlspecialchars(das_return_query($returnContext)) ?>">View</a>
          <?php endif; ?>
        </div>
      </form>
      <script>
      (function () {
        var poSelect = document.getElementById('po_id');
        var porSelect = document.getElementById('po_receipt_id');
        var supplierSelect = document.getElementById('supplier_id');
        var companyNameInput = document.getElementById('company_name');
        var contactNameInput = document.getElementById('contact_name');
        var contactEmailInput = document.getElementById('contact_email');
        var contactPhoneInput = document.getElementById('contact_phone');
        var asnNumberInput = document.getElementById('appointment_asn_number');
        var asnCreatedSelect = document.getElementById('appointment_asn_created');

        if (!poSelect || !porSelect || !supplierSelect) return;

        function selectedOption(select) {
          return select.options[select.selectedIndex] || null;
        }

        function applySupplierFields(option) {
          if (!option || !option.value) {
            return;
          }

          companyNameInput.value = option.getAttribute('data-company-name') || '';
          contactNameInput.value = option.getAttribute('data-contact-name') || '';
          contactEmailInput.value = option.getAttribute('data-contact-email') || '';
          contactPhoneInput.value = option.getAttribute('data-contact-phone') || '';
        }

        function clearAsnFields() {
          asnNumberInput.value = '';
          asnCreatedSelect.value = '0';
        }

        function applyPorFields(option) {
          if (!option || !option.value) {
            clearAsnFields();
            return;
          }

          var jazzAsn = option.getAttribute('data-jazz-asn') || '';
          asnNumberInput.value = jazzAsn;
          asnCreatedSelect.value = jazzAsn ? '1' : '0';
        }

        function syncSupplierFields(force) {
          var option = selectedOption(supplierSelect);
          if (!option || !option.value) {
            return;
          }

          if (!force && companyNameInput.value.trim() !== '') {
            return;
          }

          applySupplierFields(option);
        }

        function syncPorFields(force) {
          var option = selectedOption(porSelect);
          if (!option || !option.value) {
            if (force) {
              clearAsnFields();
            }
            return;
          }

          if (!force && asnNumberInput.value.trim() !== '') {
            return;
          }

          applyPorFields(option);
        }

        function filterPorOptions() {
          var poId = poSelect.value;
          var selectedPor = porSelect.value;
          var selectionVisible = false;

          Array.prototype.forEach.call(porSelect.options, function (option) {
            if (option.value === '') {
              option.hidden = false;
              return;
            }

            var matchesPo = poId !== '' && option.getAttribute('data-po-id') === poId;
            option.hidden = !matchesPo;

            if (matchesPo && option.value === selectedPor) {
              selectionVisible = true;
            }
          });

          if (!selectionVisible) {
            porSelect.value = '';
            clearAsnFields();
          }
        }

        function onPoChange() {
          filterPorOptions();
        }

        poSelect.addEventListener('change', onPoChange);
        supplierSelect.addEventListener('change', function () {
          applySupplierFields(selectedOption(supplierSelect));
        });
        porSelect.addEventListener('change', function () {
          applyPorFields(selectedOption(porSelect));
        });

        filterPorOptions();
        syncSupplierFields(false);
        syncPorFields(false);
      })();
      </script>
