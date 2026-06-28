<?php
/** @var int $paymentId */
/** @var array $attachments */
/** @var bool $showUploadForm */
/** @var string|null $uploadNotice */
/** @var string $attachmentBasePath */
/** @var string $uploadActionPath */
$showUploadForm = $showUploadForm ?? false;
$uploadNotice = $uploadNotice ?? null;
$attachmentBasePath = $attachmentBasePath ?? '/po-payments/attachment.php';
$uploadActionPath = $uploadActionPath ?? '/po-payments/upload-attachment.php';
?>
      <section class="detail-card supplier-po-report">
        <h2>Payment attachments</h2>

        <?php if ($uploadNotice === 'attachment'): ?>
        <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
        <?php endif; ?>

        <?php if ($attachments === []): ?>
        <p class="page-lead">No files attached to this payment yet.</p>
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
                <td><a class="btn-text" href="<?= htmlspecialchars($attachmentBasePath) ?>?id=<?= (int) $file['POPaymentAttachmentID'] ?>"><?= htmlspecialchars($file['FileName']) ?></a></td>
                <td><?= htmlspecialchars(po_payment_attachment_kind_label((string) $file['AttachmentKind'])) ?></td>
                <td><?= htmlspecialchars(po_payment_format_file_size((int) $file['FileSizeBytes'])) ?></td>
                <td><?= htmlspecialchars(admin_format_datetime($file['UploadDate'])) ?></td>
                <td><?= htmlspecialchars((string) $file['UploadedByName']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showUploadForm): ?>
        <form class="admin-form" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($uploadActionPath) ?>" style="margin-top: 16px;">
          <input type="hidden" name="payment_id" value="<?= $paymentId ?>" />
          <div class="form-grid">
            <div class="form-group">
              <label for="attachment">Upload file</label>
              <input class="form-input" type="file" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.xlsx,.csv,.png,.jpg,.jpeg,.webp,application/pdf,image/*" required />
            </div>
            <div class="form-group">
              <label for="attachment_kind">Attachment type</label>
              <select class="form-input" id="attachment_kind" name="attachment_kind">
                <?php foreach (POPAYMENT_ATTACHMENT_KINDS as $kind): ?>
                <option value="<?= htmlspecialchars($kind) ?>"><?= htmlspecialchars(po_payment_attachment_kind_label($kind)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button class="btn-secondary btn-small" type="submit">Upload attachment</button>
        </form>
        <?php endif; ?>
      </section>
