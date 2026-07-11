<?php
/**
 * Embedded drag/drop/paste file field for multipart forms.
 *
 * @var string $uploadFieldId
 * @var string|null $uploadFieldName
 * @var string $uploadLabel
 * @var string $uploadTitle
 * @var string $uploadHint
 * @var string|null $uploadFormHint
 * @var string $uploadAccept
 * @var int $uploadMaxBytes
 * @var array $uploadAllowedExt
 * @var string $uploadSuccessMessage
 * @var string $uploadGridClass
 * @var bool $uploadRequired
 */
$uploadFieldId = $uploadFieldId ?? 'attachment';
$uploadFieldName = $uploadFieldName ?? $uploadFieldId;
$uploadLabel = $uploadLabel ?? 'Upload file';
$uploadTitle = $uploadTitle ?? 'Drop, paste, or choose file';
$uploadHint = $uploadHint ?? 'Drag a file here, click and paste (Ctrl+V / Cmd+V), or choose a file';
$uploadFormHint = $uploadFormHint ?? null;
$uploadAccept = $uploadAccept ?? '.pdf,.doc,.docx,.xlsx,.csv,.png,.jpg,.jpeg,.webp,application/pdf,image/*';
$uploadMaxBytes = $uploadMaxBytes ?? (15 * 1024 * 1024);
$uploadAllowedExt = $uploadAllowedExt ?? ['pdf', 'doc', 'docx', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'webp'];
$uploadSuccessMessage = $uploadSuccessMessage ?? 'File attached';
$uploadOnSelectHint = $uploadOnSelectHint ?? null;
$uploadGridClass = $uploadGridClass ?? 'form-grid-full';
$uploadRequired = $uploadRequired ?? false;
$zoneId = $uploadFieldId . '-dropzone';
?>
  <div class="form-group <?= htmlspecialchars($uploadGridClass) ?>">
    <label for="<?= htmlspecialchars($uploadFieldId) ?>"><?= htmlspecialchars($uploadLabel) ?></label>
    <div class="enh-log-upload-dropzone" id="<?= htmlspecialchars($zoneId) ?>-form">
      <input
        class="por-import-file-input"
        type="file"
        id="<?= htmlspecialchars($uploadFieldId) ?>"
        name="<?= htmlspecialchars($uploadFieldName) ?>"
        accept="<?= htmlspecialchars($uploadAccept) ?>"
        <?= $uploadRequired ? 'required' : '' ?>
      />
      <div
        class="enh-log-paste-zone"
        id="<?= htmlspecialchars($zoneId) ?>"
        tabindex="0"
        role="button"
        aria-label="<?= htmlspecialchars($uploadTitle) ?>"
      >
        <span class="enh-log-paste-zone-title"><?= htmlspecialchars($uploadTitle) ?></span>
        <span class="enh-log-paste-zone-hint"><?= htmlspecialchars($uploadHint) ?></span>
        <span class="por-import-dropzone-file" id="<?= htmlspecialchars($uploadFieldId) ?>-file-name" hidden></span>
      </div>
    </div>
    <?php if ($uploadFormHint !== null && $uploadFormHint !== ''): ?>
    <p class="form-hint"><?= $uploadFormHint ?></p>
    <?php endif; ?>
  </div>
  <script>
  (function () {
    var inputId = <?= json_encode($uploadFieldId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var maxBytes = <?= (int) $uploadMaxBytes ?>;
    var allowedExt = <?= json_encode(array_values($uploadAllowedExt), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var successMessage = <?= json_encode($uploadSuccessMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var onSelectHint = <?= json_encode($uploadOnSelectHint, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var formWrap = document.getElementById(inputId + '-dropzone-form');
    var pasteZone = document.getElementById(inputId + '-dropzone');
    var fileInput = document.getElementById(inputId);
    var fileName = document.getElementById(inputId + '-file-name');
    if (!formWrap || !pasteZone || !fileInput) return;

    var defaultTitle = pasteZone.querySelector('.enh-log-paste-zone-title').textContent;
    var defaultHint = pasteZone.querySelector('.enh-log-paste-zone-hint').textContent;
    var dragDepth = 0;

    function fileExtension(name) {
      var match = (name || '').toLowerCase().match(/\.([a-z0-9]+)$/);
      return match ? match[1] : '';
    }

    function isAllowedFile(file) {
      if (!file) return false;
      var ext = fileExtension(file.name);
      if (ext && allowedExt.indexOf(ext) !== -1) return true;
      if (file.type === 'application/pdf') return true;
      if (file.type === 'text/csv') return true;
      if (file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') return true;
      if (file.type === 'application/vnd.ms-excel') return true;
      if (file.type && file.type.indexOf('image/') === 0) return true;
      return false;
    }

    function setZoneMessage(title, hint, isError) {
      pasteZone.querySelector('.enh-log-paste-zone-title').textContent = title;
      pasteZone.querySelector('.enh-log-paste-zone-hint').textContent = hint;
      pasteZone.classList.toggle('is-error', !!isError);
      pasteZone.classList.remove('is-uploading');
      formWrap.classList.toggle('is-dragover', pasteZone.classList.contains('is-dragover'));
    }

    function resetZoneMessage() {
      setZoneMessage(defaultTitle, defaultHint, false);
      pasteZone.classList.remove('is-dragover', 'is-uploading', 'is-error');
      formWrap.classList.remove('is-dragover');
      dragDepth = 0;
    }

    function assignFile(file) {
      if (!isAllowedFile(file)) {
        setZoneMessage('Invalid file type', 'Use PDF, Office, CSV, or image files.', true);
        window.setTimeout(resetZoneMessage, 3500);
        return;
      }

      if (file.size > maxBytes) {
        setZoneMessage('File too large', 'Maximum file size is ' + Math.round(maxBytes / 1048576) + ' MB.', true);
        window.setTimeout(resetZoneMessage, 4000);
        return;
      }

      var dt = new DataTransfer();
      dt.items.add(file);
      fileInput.files = dt.files;

      if (fileName) {
        fileName.hidden = false;
        fileName.textContent = file.name || 'attachment';
      }
      setZoneMessage(successMessage, onSelectHint
        ? onSelectHint.replace('%s', file.name || 'attachment')
        : (file.name || 'attachment') + ' will upload when you save.', false);
    }

    function handleDrop(event) {
      event.preventDefault();
      event.stopPropagation();
      dragDepth = 0;
      pasteZone.classList.remove('is-dragover');
      formWrap.classList.remove('is-dragover');

      var files = event.dataTransfer && event.dataTransfer.files;
      if (!files || !files.length) {
        setZoneMessage('No file dropped', 'Drop a single file to attach.', true);
        window.setTimeout(resetZoneMessage, 3000);
        return;
      }
      if (files.length > 1) {
        setZoneMessage('One file at a time', 'Drop a single file to attach.', true);
        window.setTimeout(resetZoneMessage, 3000);
        return;
      }
      assignFile(files[0]);
    }

    function markDragEnter(event) {
      event.preventDefault();
      event.stopPropagation();
      dragDepth += 1;
      pasteZone.classList.add('is-dragover');
      formWrap.classList.add('is-dragover');
    }

    function markDragLeave(event) {
      event.preventDefault();
      event.stopPropagation();
      dragDepth = Math.max(0, dragDepth - 1);
      if (dragDepth === 0) {
        pasteZone.classList.remove('is-dragover');
        formWrap.classList.remove('is-dragover');
      }
    }

    function allowDrag(event) {
      event.preventDefault();
      event.stopPropagation();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
      }
    }

    [formWrap, pasteZone].forEach(function (target) {
      target.addEventListener('dragenter', markDragEnter);
      target.addEventListener('dragover', allowDrag);
      target.addEventListener('dragleave', markDragLeave);
      target.addEventListener('drop', handleDrop);
    });

    pasteZone.addEventListener('click', function () {
      fileInput.click();
    });

    pasteZone.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        fileInput.click();
      }
    });

    pasteZone.addEventListener('paste', function (event) {
      var items = event.clipboardData && event.clipboardData.items;
      if (!items) {
        return;
      }

      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        if (item.kind !== 'file') {
          continue;
        }
        var file = item.getAsFile();
        if (file) {
          event.preventDefault();
          assignFile(file);
          return;
        }
      }
    });

    fileInput.addEventListener('change', function () {
      if (fileInput.files && fileInput.files[0]) {
        assignFile(fileInput.files[0]);
      }
    });
  })();
  </script>
