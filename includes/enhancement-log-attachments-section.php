<?php
/** @var int $logId */
/** @var bool $showUploadForm */
$showUploadForm = $showUploadForm ?? false;
$attachments = enh_log_list_attachments($logId);
?>
      <section class="detail-card" data-enh-log-upload="3">
        <h2>Screenshots</h2>
        <?php if ($attachments === []): ?>
        <p class="page-lead">No screenshots attached yet.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Preview</th>
                <th>File</th>
                <th>Type</th>
                <th>Size</th>
                <th>Uploaded</th>
                <th>By</th>
                <?php if ($showUploadForm): ?>
                <th>Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attachments as $file): ?>
              <?php
                $attachmentId = (int) $file['AttachmentID'];
                $attachmentUrl = enh_log_attachment_view_path($logId, $attachmentId);
                $thumbUri = enh_log_attachment_data_uri($attachmentId, $file);
              ?>
              <tr>
                <td>
                  <a href="<?= htmlspecialchars($attachmentUrl) ?>" target="_blank" rel="noopener">
                    <?php if ($thumbUri !== null): ?>
                    <img
                      src="<?= $thumbUri ?>"
                      alt=""
                      class="enh-log-thumb"
                    />
                    <?php else: ?>
                    <span class="enh-log-thumb-missing">No preview</span>
                    <?php endif; ?>
                  </a>
                </td>
                <td><a class="btn-text" href="<?= htmlspecialchars($attachmentUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($file['FileName']) ?></a></td>
                <td><?= htmlspecialchars(enh_log_attachment_kind_label((string) $file['AttachmentKind'])) ?></td>
                <td><?= htmlspecialchars(enh_log_format_file_size((int) $file['FileSizeBytes'])) ?></td>
                <td><?= htmlspecialchars(enhancement_log_format_datetime((string) ($file['UploadedAt'] ?? ''))) ?></td>
                <td><?= htmlspecialchars((string) ($file['UploadedByName'] ?? '—')) ?></td>
                <?php if ($showUploadForm): ?>
                <td>
                  <?= table_action_delete_form(
                      '/enhancement-log/delete-attachment.php',
                      ['log_id' => $logId, 'attachment_id' => (int) $file['AttachmentID']],
                      'Delete this screenshot?'
                  ) ?>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showUploadForm): ?>
        <form class="admin-form enh-log-upload-dropzone" id="enh-log-upload-form-<?= (int) $logId ?>" method="post" enctype="multipart/form-data" action="/enhancement-log/upload-attachment.php" style="margin-top: 16px;">
          <input type="hidden" name="log_id" value="<?= $logId ?>" />

          <div
            class="enh-log-paste-zone"
            id="enh-log-paste-zone-<?= (int) $logId ?>"
            tabindex="0"
            role="button"
            aria-label="Drop, paste, or upload screenshot"
          >
            <span class="enh-log-paste-zone-title">Drop, paste, or upload screenshot</span>
            <span class="enh-log-paste-zone-hint">Drag an image file into this box, click here and paste (Ctrl+V / Cmd+V), or choose a file below</span>
          </div>

          <div class="form-grid" style="margin-top: 16px;">
            <div class="form-group">
              <label for="attachment-<?= (int) $logId ?>">Choose file</label>
              <input class="form-input" type="file" id="attachment-<?= (int) $logId ?>" name="attachment" accept=".png,.jpg,.jpeg,.gif,.webp,image/*" />
            </div>
            <div class="form-group">
              <label for="attachment_kind-<?= (int) $logId ?>">Attachment type</label>
              <select class="form-input" id="attachment_kind-<?= (int) $logId ?>" name="attachment_kind">
                <?php foreach (ENH_LOG_ATTACHMENT_KINDS as $kind => $label): ?>
                <option value="<?= htmlspecialchars($kind) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button class="btn-secondary btn-small" type="submit">Upload screenshot</button>
        </form>
        <script>
        (function () {
          var logId = <?= (int) $logId ?>;
          var form = document.getElementById('enh-log-upload-form-' + logId);
          var pasteZone = document.getElementById('enh-log-paste-zone-' + logId);
          var fileInput = document.getElementById('attachment-' + logId);
          var kindSelect = document.getElementById('attachment_kind-' + logId);
          if (!form || !pasteZone || !fileInput || !kindSelect) return;

          var defaultTitle = pasteZone.querySelector('.enh-log-paste-zone-title').textContent;
          var defaultHint = pasteZone.querySelector('.enh-log-paste-zone-hint').textContent;
          var dragDepth = 0;

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
            return 'png';
          }

          function isImageFile(file) {
            if (!file) return false;
            if (file.type && file.type.indexOf('image/') === 0) return true;
            var name = (file.name || '').toLowerCase();
            return /\.(png|jpe?g|gif|webp)$/.test(name);
          }

          function showPreview(file) {
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

            window.location.href = data.redirect || ('/enhancement-log/view.php?id=' + logId + '&notice=attachment');
          }

          function uploadImageFile(file) {
            if (!isImageFile(file)) {
              setZoneMessage('Invalid file type', 'Use PNG, JPG, GIF, or WebP.', true);
              window.setTimeout(resetZoneMessage, 3000);
              return;
            }

            showPreview(file);
            setZoneMessage('Uploading…', file.name || 'screenshot', false);

            var formData = new FormData();
            formData.append('log_id', String(logId));
            formData.append('attachment_kind', kindSelect.value);
            formData.append('attachment', file, file.name || ('screenshot-' + Date.now() + '.' + extensionForType(file.type || 'image/png')));
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
              setZoneMessage('No file dropped', 'Drop a single image file to upload.', true);
              window.setTimeout(resetZoneMessage, 3000);
              return;
            }

            if (files.length > 1) {
              setZoneMessage('One file at a time', 'Drop a single screenshot or image file.', true);
              window.setTimeout(resetZoneMessage, 3000);
              return;
            }

            uploadImageFile(files[0]);
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
              setZoneMessage('Nothing to paste', 'Copy a screenshot first, then try again.', true);
              return;
            }

            for (var i = 0; i < items.length; i++) {
              if (items[i].type && items[i].type.indexOf('image/') === 0) {
                var blob = items[i].getAsFile();
                if (!blob) continue;
                var ext = extensionForType(blob.type || 'image/png');
                var file = new File([blob], 'pasted-screenshot-' + Date.now() + '.' + ext, {
                  type: blob.type || 'image/png',
                });
                uploadImageFile(file);
                return;
              }
            }

            setZoneMessage('No image found', 'Copy a screenshot or image, then paste again.', true);
            window.setTimeout(resetZoneMessage, 3000);
          });
        })();
        </script>
        <?php endif; ?>
      </section>
