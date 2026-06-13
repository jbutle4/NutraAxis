<?php
/** @var int $contractId */
/** @var array $attachments */
/** @var bool $showUploadForm */
$showUploadForm = $showUploadForm ?? false;
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
                <td><a class="btn-text" href="/legal-agreements/attachment.php?id=<?= (int) $file['AttachmentID'] ?>"><?= htmlspecialchars($file['FileName']) ?></a></td>
                <td><?= htmlspecialchars(legal_attachment_kind_label($file['AttachmentKind'])) ?></td>
                <td><?= htmlspecialchars(legal_format_file_size((int) $file['FileSizeBytes'])) ?></td>
                <td><?= htmlspecialchars(admin_format_datetime($file['UploadDate'])) ?></td>
                <td><?= htmlspecialchars($file['UploadedByName']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showUploadForm): ?>
        <form class="admin-form" method="post" enctype="multipart/form-data" action="/legal-agreements/upload-attachment.php" style="margin-top: 16px;">
          <input type="hidden" name="contract_id" value="<?= $contractId ?>" />
          <div class="form-grid">
            <div class="form-group">
              <label for="attachment">Upload file</label>
              <input class="form-input" type="file" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.xlsx,.csv,application/pdf" required />
            </div>
            <div class="form-group">
              <label for="attachment_kind">Attachment type</label>
              <select class="form-input" id="attachment_kind" name="attachment_kind">
                <?php foreach (LEGAL_ATTACHMENT_KINDS as $value => $label): ?>
                <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button class="btn-secondary btn-small" type="submit">Upload attachment</button>
        </form>
        <?php endif; ?>
      </section>
