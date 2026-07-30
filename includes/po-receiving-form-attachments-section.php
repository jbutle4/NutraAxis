<?php
/** @var array $porAttachments */
/** @var bool $showFormAttachmentUpload */
$porAttachments = $porAttachments ?? [];
$showFormAttachmentUpload = $showFormAttachmentUpload ?? true;
$attachmentFieldId = 'por-form-attachment-' . (int) ($form['por_id'] ?? 0);
?>
        <h2 class="production-line-header">Receiving documents</h2>
        <section class="detail-card supplier-po-report por-form-attachments-card">
          <?php if ($porAttachments !== []): ?>
          <div class="admin-table-wrap" style="margin-bottom: 16px;">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>File</th>
                  <th>Type</th>
                  <th>Size</th>
                  <th>Uploaded</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($porAttachments as $file): ?>
                <tr>
                  <td>
                    <a class="btn-text" href="<?= htmlspecialchars(por_page_path('/po-receiving/attachment.php')) ?>?id=<?= (int) $file['AttachmentID'] ?>">
                      <?= htmlspecialchars($file['FileName']) ?>
                    </a>
                  </td>
                  <td><?= htmlspecialchars(por_attachment_kind_label((string) $file['AttachmentKind'])) ?></td>
                  <td><?= htmlspecialchars(por_format_file_size((int) $file['FileSizeBytes'])) ?></td>
                  <td><?= htmlspecialchars(por_format_datetime($file['UploadDate'] ?? null)) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>

          <?php if ($showFormAttachmentUpload): ?>
          <div class="form-grid">
            <?php
            $uploadFieldId = $attachmentFieldId;
            $uploadFieldName = 'attachment';
            $uploadLabel = 'Upload file';
            $uploadTitle = 'Drop, paste, or choose receiving document';
            $uploadHint = 'PDF, image, or office document up to 15 MB. Saved with this receipt when you submit.';
            $uploadAccept = '.pdf,.doc,.docx,.xlsx,.csv,.png,.jpg,.jpeg,.webp,application/pdf,image/*';
            $uploadMaxBytes = POR_MAX_ATTACHMENT_BYTES;
            $uploadAllowedExt = ['pdf', 'doc', 'docx', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'webp'];
            $uploadRequired = false;
            $uploadGridClass = 'form-grid-full';
            require __DIR__ . '/file-upload-dropzone-field.php';
            ?>
            <div class="form-group">
              <label for="attachment_kind">Attachment type</label>
              <select class="form-input" id="attachment_kind" name="attachment_kind">
                <?php foreach (POR_ATTACHMENT_KINDS as $kind): ?>
                <option value="<?= htmlspecialchars($kind) ?>" <?= ($form['attachment_kind'] ?? 'PackingSlip') === $kind ? 'selected' : '' ?>>
                  <?= htmlspecialchars(por_attachment_kind_label($kind)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php endif; ?>
        </section>
