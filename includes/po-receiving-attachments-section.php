<?php
/** @var int $porId */
/** @var array $attachments */
/** @var bool $showUploadForm */
$showUploadForm = $showUploadForm ?? false;
$attachmentFieldId = 'por-attachment-' . (int) $porId;
?>
      <section class="detail-card supplier-po-report">
        <h2>Attachments</h2>
        <?php if ($attachments === []): ?>
        <p class="page-lead">No files attached yet.</p>
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
                <td><a class="btn-text" href="/po-receiving/attachment.php?id=<?= (int) $file['AttachmentID'] ?>"><?= htmlspecialchars($file['FileName']) ?></a></td>
                <td><?= htmlspecialchars(por_attachment_kind_label((string) $file['AttachmentKind'])) ?></td>
                <td><?= htmlspecialchars(por_format_file_size((int) $file['FileSizeBytes'])) ?></td>
                <td><?= htmlspecialchars(admin_format_datetime($file['UploadDate'])) ?></td>
                <td><?= htmlspecialchars((string) $file['UploadedByName']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showUploadForm): ?>
        <form class="admin-form" method="post" enctype="multipart/form-data" action="/po-receiving/upload-attachment.php" style="margin-top: 16px;">
          <input type="hidden" name="por_id" value="<?= $porId ?>" />
          <div class="form-grid">
            <?php
            $uploadFieldId = $attachmentFieldId;
            $uploadFieldName = 'attachment';
            $uploadLabel = 'Upload file';
            $uploadTitle = 'Drop, paste, or choose file';
            $uploadHint = 'Drag a file here, click and paste (Ctrl+V / Cmd+V), or choose a file';
            $uploadAccept = '.pdf,.doc,.docx,.xlsx,.csv,.png,.jpg,.jpeg,.webp,application/pdf,image/*';
            $uploadMaxBytes = POR_MAX_ATTACHMENT_BYTES;
            $uploadAllowedExt = ['pdf', 'doc', 'docx', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'webp'];
            $uploadRequired = true;
            $uploadGridClass = '';
            require __DIR__ . '/file-upload-dropzone-field.php';
            ?>
            <div class="form-group">
              <label for="attachment_kind">Attachment type</label>
              <select class="form-input" id="attachment_kind" name="attachment_kind">
                <?php foreach (POR_ATTACHMENT_KINDS as $kind): ?>
                <option value="<?= htmlspecialchars($kind) ?>"><?= htmlspecialchars(por_attachment_kind_label($kind)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button class="btn-secondary btn-small" type="submit">Upload attachment</button>
        </form>
        <?php endif; ?>
      </section>
