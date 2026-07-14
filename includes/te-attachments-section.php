<?php
/** @var int $reportId */
/** @var array $report */
/** @var bool $showUploadForm */
$showUploadForm = $showUploadForm ?? false;
$attachments = te_list_attachments($reportId);
$attachmentFieldId = 'te-attachment-' . (int) $reportId;
?>
      <section class="detail-card supplier-po-report">
        <h2>Receipt attachments</h2>
        <?php if ($attachments === []): ?>
        <p class="page-lead">No receipt PDFs attached yet.</p>
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
                <?php if ($showUploadForm): ?>
                <th>Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attachments as $file): ?>
              <tr>
                <td><a class="btn-text" href="<?= htmlspecialchars(te_attachment_download_path($reportId, (int) $file['AttachmentID'])) ?>"><?= htmlspecialchars($file['FileName']) ?></a></td>
                <td><?= htmlspecialchars((string) $file['AttachmentKind']) ?></td>
                <td><?= htmlspecialchars(te_format_file_size((int) $file['FileSizeBytes'])) ?></td>
                <td><?= htmlspecialchars(admin_format_datetime($file['UploadedAt'])) ?></td>
                <td><?= htmlspecialchars((string) ($file['UploadedByName'] ?? '—')) ?></td>
                <?php if ($showUploadForm): ?>
                <td>
                  <?= table_action_delete_form(
                      '/travel-expense/delete.php',
                      ['report_id' => $reportId, 'attachment_id' => (int) $file['AttachmentID']],
                      'Delete this receipt attachment?'
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
        <form class="admin-form" method="post" enctype="multipart/form-data" action="/travel-expense/upload-attachment.php" style="margin-top: 16px;">
          <input type="hidden" name="report_id" value="<?= $reportId ?>" />
          <div class="form-grid">
            <?php
            $uploadFieldId = $attachmentFieldId;
            $uploadFieldName = 'attachment';
            $uploadLabel = 'Upload receipt (PDF)';
            $uploadTitle = 'Drop, paste, or choose PDF';
            $uploadHint = 'Drag a PDF here, click and paste (Ctrl+V / Cmd+V), or choose a file';
            $uploadAccept = '.pdf,application/pdf';
            $uploadMaxBytes = TE_MAX_ATTACHMENT_BYTES;
            $uploadAllowedExt = ['pdf'];
            $uploadRequired = true;
            $uploadGridClass = '';
            require __DIR__ . '/file-upload-dropzone-field.php';
            ?>
            <div class="form-group">
              <label for="attachment_kind">Attachment type</label>
              <select class="form-input" id="attachment_kind" name="attachment_kind">
                <?php foreach (TE_ATTACHMENT_KINDS as $kind): ?>
                <option value="<?= htmlspecialchars($kind) ?>"><?= htmlspecialchars($kind) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button class="btn-secondary btn-small" type="submit">Upload receipt</button>
        </form>
        <?php endif; ?>
      </section>
