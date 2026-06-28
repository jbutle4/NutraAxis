<?php
/** @var int $invoiceId */
/** @var array $attachments */
/** @var bool $showUploadForm */
/** @var string|null $uploadNotice */
/** @var string $attachmentBasePath */
/** @var string $uploadActionPath */
/** @var string|null $uploadReturnPath */
$showUploadForm = $showUploadForm ?? false;
$uploadNotice = $uploadNotice ?? null;
$attachmentBasePath = $attachmentBasePath ?? '/accounting/supplier-invoices/attachment.php';
$uploadActionPath = $uploadActionPath ?? '/accounting/supplier-invoices/upload-attachment.php';
$uploadReturnPath = $uploadReturnPath ?? '/accounting/supplier-invoices/view.php?id=' . (int) $invoiceId;
$attachmentAccept = '.pdf,.doc,.docx,.xlsx,.csv,.png,.jpg,.jpeg,.webp,application/pdf,image/*';
?>
<section class="detail-card supplier-po-report">
  <h2>Invoice attachments</h2>

  <?php if ($uploadNotice === 'attachment'): ?>
  <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
  <?php endif; ?>

  <?php if ($attachments === []): ?>
  <p class="page-lead">No files attached to this invoice yet.</p>
  <?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>File</th>
          <th>Type</th>
          <th>Size</th>
          <th>Uploaded</th>
          <th>By</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($attachments as $file): ?>
        <tr>
          <td><a class="btn-text" href="<?= htmlspecialchars($attachmentBasePath) ?>?id=<?= (int) $file['AttachmentID'] ?>"><?= htmlspecialchars($file['FileName']) ?></a></td>
          <td><?= htmlspecialchars(supplier_invoice_attachment_kind_label((string) $file['AttachmentKind'])) ?></td>
          <td><?= htmlspecialchars(supplier_invoice_format_file_size((int) $file['FileSizeBytes'])) ?></td>
          <td><?= htmlspecialchars(admin_format_datetime($file['UploadDate'])) ?></td>
          <td><?= htmlspecialchars((string) $file['UploadedByName']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($showUploadForm): ?>
  <form class="admin-form enh-log-upload-dropzone" id="supplier-invoice-upload-form-<?= (int) $invoiceId ?>" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($uploadActionPath) ?>" style="margin-top: 16px;">
    <input type="hidden" name="invoice_id" value="<?= (int) $invoiceId ?>" />
    <input type="hidden" name="return_to" value="<?= htmlspecialchars($uploadReturnPath) ?>" />

    <div
      class="enh-log-paste-zone"
      id="supplier-invoice-paste-zone-<?= (int) $invoiceId ?>"
      tabindex="0"
      role="button"
      aria-label="Drop, paste, or upload invoice attachment"
    >
      <span class="enh-log-paste-zone-title">Drop, paste, or upload file</span>
      <span class="enh-log-paste-zone-hint">Drag a PDF or image into this box, click here and paste a screenshot (Ctrl+V / Cmd+V), or choose a file below</span>
    </div>

    <div class="form-grid" style="margin-top: 16px;">
      <div class="form-group">
        <label for="attachment-<?= (int) $invoiceId ?>">Choose file</label>
        <input class="form-input" type="file" id="attachment-<?= (int) $invoiceId ?>" name="attachment" accept="<?= htmlspecialchars($attachmentAccept) ?>" />
      </div>
      <div class="form-group">
        <label for="attachment_kind-<?= (int) $invoiceId ?>">Attachment type</label>
        <select class="form-input" id="attachment_kind-<?= (int) $invoiceId ?>" name="attachment_kind">
          <?php foreach (array_keys(SUPPLIER_INVOICE_ATTACHMENT_KINDS) as $kind): ?>
          <option value="<?= htmlspecialchars($kind) ?>"><?= htmlspecialchars(supplier_invoice_attachment_kind_label($kind)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <button class="btn-secondary btn-small" type="submit">Upload attachment</button>
  </form>
  <script>
  (function () {
    var invoiceId = <?= (int) $invoiceId ?>;
    var returnTo = <?= json_encode($uploadReturnPath, JSON_UNESCAPED_SLASHES) ?>;
    var form = document.getElementById('supplier-invoice-upload-form-' + invoiceId);
    var pasteZone = document.getElementById('supplier-invoice-paste-zone-' + invoiceId);
    var fileInput = document.getElementById('attachment-' + invoiceId);
    var kindSelect = document.getElementById('attachment_kind-' + invoiceId);
    if (!form || !pasteZone || !fileInput || !kindSelect) return;

    var defaultTitle = pasteZone.querySelector('.enh-log-paste-zone-title').textContent;
    var defaultHint = pasteZone.querySelector('.enh-log-paste-zone-hint').textContent;
    var dragDepth = 0;
    var allowedExt = ['pdf', 'doc', 'docx', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'webp'];

    function setZoneMessage(title, hint, isError) {
      pasteZone.querySelector('.enh-log-paste-zone-title').textContent = title;
      pasteZone.querySelector('.enh-log-paste-zone-hint').textContent = hint;
      pasteZone.classList.toggle('is-error', !!isError);
      pasteZone.classList.toggle('is-uploading', !isError && title === 'Uploading…');
      form.classList.toggle('is-dragover', pasteZone.classList.contains('is-dragover'));
    }

    function resetZoneMessage() {
      setZoneMessage(defaultTitle, defaultHint, false);
      pasteZone.classList.remove('is-uploading', 'is-dragover');
      form.classList.remove('is-dragover');
      dragDepth = 0;
      var preview = pasteZone.querySelector('.enh-log-paste-preview');
      if (preview) preview.remove();
    }

    function extensionForType(type) {
      if (type === 'image/jpeg') return 'jpg';
      if (type === 'image/png') return 'png';
      if (type === 'image/gif') return 'gif';
      if (type === 'image/webp') return 'webp';
      if (type === 'application/pdf') return 'pdf';
      return 'bin';
    }

    function fileExtension(name) {
      var match = (name || '').toLowerCase().match(/\.([a-z0-9]+)$/);
      return match ? match[1] : '';
    }

    function isAllowedFile(file) {
      if (!file) return false;
      var ext = fileExtension(file.name);
      if (ext && allowedExt.indexOf(ext) !== -1) return true;
      if (file.type === 'application/pdf') return true;
      if (file.type && file.type.indexOf('image/') === 0) return true;
      return false;
    }

    function isImageFile(file) {
      if (!file) return false;
      if (file.type && file.type.indexOf('image/') === 0) return true;
      return /\.(png|jpe?g|gif|webp)$/i.test(file.name || '');
    }

    function showPreview(file) {
      if (!isImageFile(file)) return;
      var preview = pasteZone.querySelector('.enh-log-paste-preview');
      if (!preview) {
        preview = document.createElement('img');
        preview.className = 'enh-log-paste-preview';
        preview.alt = '';
        pasteZone.appendChild(preview);
      }
      preview.src = URL.createObjectURL(file);
    }

    function parseUploadResponse(response, text) {
      var data = null;
      try {
        data = JSON.parse(text);
      } catch (error) {
        if (text.indexOf('<html') !== -1 || response.redirected) {
          throw new Error('Session expired or upload blocked. Refresh the page and try again.');
        }
        throw new Error('Upload failed due to an unexpected server response.');
      }

      if (!response.ok || !data.ok) {
        throw new Error((data && data.error) || 'Upload failed.');
      }

      window.location.href = data.redirect || (returnTo + (returnTo.indexOf('?') >= 0 ? '&' : '?') + 'notice=attachment');
    }

    function uploadFile(file) {
      if (!isAllowedFile(file)) {
        setZoneMessage('Invalid file type', 'Use PDF, Word, Excel, CSV, or image files.', true);
        window.setTimeout(resetZoneMessage, 3000);
        return;
      }

      showPreview(file);
      setZoneMessage('Uploading…', file.name || 'attachment', false);

      var formData = new FormData();
      formData.append('invoice_id', String(invoiceId));
      formData.append('return_to', returnTo);
      formData.append('attachment_kind', kindSelect.value);
      formData.append('attachment', file, file.name || ('attachment-' + Date.now() + '.' + extensionForType(file.type || 'application/octet-stream')));
      formData.append('ajax', '1');

      fetch(form.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
        .then(function (response) {
          return response.text().then(function (text) {
            parseUploadResponse(response, text);
          });
        })
        .catch(function (error) {
          setZoneMessage('Upload failed', error.message || 'Try choosing the file below instead.', true);
          window.setTimeout(resetZoneMessage, 5000);
        });
    }

    function handleDrop(event) {
      event.preventDefault();
      event.stopPropagation();
      dragDepth = 0;
      pasteZone.classList.remove('is-dragover');
      form.classList.remove('is-dragover');

      var files = event.dataTransfer && event.dataTransfer.files;
      if (!files || !files.length) {
        setZoneMessage('No file dropped', 'Drop a single file to upload.', true);
        window.setTimeout(resetZoneMessage, 3000);
        return;
      }

      if (files.length > 1) {
        setZoneMessage('One file at a time', 'Drop a single file to upload.', true);
        window.setTimeout(resetZoneMessage, 3000);
        return;
      }

      uploadFile(files[0]);
    }

    function markDragEnter(event) {
      event.preventDefault();
      event.stopPropagation();
      dragDepth += 1;
      pasteZone.classList.add('is-dragover');
      form.classList.add('is-dragover');
    }

    function markDragLeave(event) {
      event.preventDefault();
      event.stopPropagation();
      dragDepth = Math.max(0, dragDepth - 1);
      if (dragDepth === 0) {
        pasteZone.classList.remove('is-dragover');
        form.classList.remove('is-dragover');
      }
    }

    function allowDrag(event) {
      event.preventDefault();
      event.stopPropagation();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
      }
    }

    [form, pasteZone].forEach(function (target) {
      target.addEventListener('dragenter', markDragEnter);
      target.addEventListener('dragover', allowDrag);
      target.addEventListener('dragleave', markDragLeave);
      target.addEventListener('drop', handleDrop);
    });

    window.addEventListener('dragover', function (event) {
      event.preventDefault();
    });
    window.addEventListener('drop', function (event) {
      if (form.contains(event.target)) return;
      event.preventDefault();
    });

    pasteZone.addEventListener('click', function () {
      pasteZone.focus();
    });

    pasteZone.addEventListener('paste', function (event) {
      event.preventDefault();
      var items = event.clipboardData && event.clipboardData.items;
      if (!items) {
        setZoneMessage('Nothing to paste', 'Copy a screenshot or image first, then try again.', true);
        return;
      }

      for (var i = 0; i < items.length; i++) {
        if (items[i].type && items[i].type.indexOf('image/') === 0) {
          var blob = items[i].getAsFile();
          if (!blob) continue;
          var ext = extensionForType(blob.type || 'image/png');
          var file = new File([blob], 'pasted-attachment-' + Date.now() + '.' + ext, {
            type: blob.type || 'image/png',
          });
          uploadFile(file);
          return;
        }
      }

      setZoneMessage('No image found', 'Paste works for screenshots and images. Use drag-and-drop or choose file for PDFs.', true);
      window.setTimeout(resetZoneMessage, 4000);
    });

    fileInput.addEventListener('change', function () {
      if (fileInput.files && fileInput.files.length === 1) {
        uploadFile(fileInput.files[0]);
      }
    });
  })();
  </script>
  <?php endif; ?>
</section>
