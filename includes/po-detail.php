<?php
/** @var array $order */
/** @var array $lines */
/** @var array $attachments */
/** @var bool $showUploadForm */
/** @var bool $canEditNotes */
/** @var array $productionByLine */
/** @var bool $canEditProduction */
$showUploadForm = $showUploadForm ?? false;
$canEditNotes = $canEditNotes ?? false;
$approvalLog = $approvalLog ?? [];
$productionByLine = $productionByLine ?? [];
$canEditProduction = $canEditProduction ?? false;
?>
      <div class="account-grid">
        <div class="account-card">
          <h2>Buyer</h2>
          <dl class="account-details">
            <div><dt>Name</dt><dd><?= htmlspecialchars($order['BuyerName'] ?? '—') ?></dd></div>
            <div><dt>Address</dt><dd><?= htmlspecialchars($order['BuyerAddress'] ?? '—') ?></dd></div>
            <div><dt>Contact</dt><dd><?= htmlspecialchars($order['BuyerContactName'] ?? '—') ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars($order['BuyerContactEmail'] ?? '—') ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars($order['BuyerContactPhone'] ?? '—') ?></dd></div>
          </dl>
        </div>

        <div class="account-card">
          <h2>Supplier</h2>
          <dl class="account-details">
            <div><dt>Name</dt><dd><?= htmlspecialchars($order['SupplierName']) ?></dd></div>
            <div><dt>Address</dt><dd><?= htmlspecialchars($order['SupplierAddress'] ?? $order['SupplierTableAddress'] ?? '—') ?></dd></div>
            <div><dt>Contact</dt><dd><?= htmlspecialchars($order['ContactName'] ?? '—') ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars($order['ContactEmail'] ?? '—') ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars($order['ContactPhone'] ?? '—') ?></dd></div>
          </dl>
        </div>

        <div class="account-card">
          <h2>Terms</h2>
          <dl class="account-details">
            <div><dt>PO date</dt><dd><?= htmlspecialchars(po_format_date($order['OrderDate'])) ?></dd></div>
            <div><dt>Expected delivery</dt><dd><?= htmlspecialchars(po_format_date($order['ExpectedDeliveryDate'])) ?></dd></div>
            <div><dt>Payment terms</dt><dd><?= htmlspecialchars($order['PaymentTerms'] ?? '—') ?></dd></div>
            <div><dt>Delivery terms</dt><dd><?= htmlspecialchars($order['DeliveryTerms'] ?? '—') ?></dd></div>
            <div><dt>Delivery address</dt><dd><?= htmlspecialchars($order['DeliveryAddress'] ?? '—') ?></dd></div>
            <div><dt>Reference documents</dt><dd><?= htmlspecialchars($order['ReferenceDocuments'] ?? '—') ?></dd></div>
          </dl>
        </div>

        <div class="account-card">
          <h2>Summary</h2>
          <dl class="account-details">
            <div><dt>Subtotal</dt><dd><?= htmlspecialchars(po_format_money($order['Subtotal'])) ?></dd></div>
            <div><dt>Shipping &amp; handling</dt><dd><?= $order['ShippingHandling'] !== null ? htmlspecialchars(po_format_money($order['ShippingHandling'])) : 'TBD' ?></dd></div>
            <div><dt>Total due</dt><dd><strong><?= htmlspecialchars(po_format_money($order['TotalDue'] ?? $order['Subtotal'])) ?></strong></dd></div>
            <div><dt>Created by</dt><dd><?= htmlspecialchars($order['CreatedByName']) ?></dd></div>
            <div><dt>Last modified</dt><dd><?= htmlspecialchars(admin_format_datetime($order['ModifiedDate'])) ?></dd></div>
          </dl>
        </div>
      </div>

      <?php if (!empty($order['SpecialInstructions'])): ?>
      <div class="status-banner">
        <div>
          <strong>Special instructions</strong>
          <p><?= nl2br(htmlspecialchars($order['SpecialInstructions'])) ?></p>
        </div>
      </div>
      <?php endif; ?>

      <div class="admin-table-wrap" style="margin-top: 20px;">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Line</th>
              <th>Product / bottle title</th>
              <th>SKU</th>
              <th>Quote #</th>
              <th>Unit price</th>
              <th>Exp date</th>
              <th>Qty</th>
              <th>Line total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lines as $line): ?>
            <tr>
              <td><?= (int) $line['LineNumber'] ?></td>
              <td><?= htmlspecialchars($line['ItemDescription']) ?></td>
              <td><?= !empty($line['ItemSKU']) ? htmlspecialchars($line['ItemSKU']) : '—' ?></td>
              <td><?= !empty($line['QuoteNumber']) ? htmlspecialchars($line['QuoteNumber']) : '—' ?></td>
              <td><?= htmlspecialchars(po_format_money($line['UnitPrice'])) ?></td>
              <td><?= htmlspecialchars(po_format_date($line['ExpirationDate'] ?? null)) ?></td>
              <td><?= htmlspecialchars(po_format_qty($line['Quantity'] ?? null)) ?></td>
              <td><?= htmlspecialchars(po_format_money($line['LineTotal'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php require __DIR__ . '/po-production-detail.php'; ?>

      <div class="account-card" style="margin-top: 20px;">
        <h2>Internal notes</h2>
        <?php if ($canEditNotes): ?>
        <form class="admin-form" method="post" action="/po-management/notes.php">
          <input type="hidden" name="po_id" value="<?= (int) $order['POID'] ?>" />
          <div class="form-group">
            <label for="notes">Notes</label>
            <textarea class="form-input form-textarea" id="notes" name="notes" rows="3"><?= htmlspecialchars($order['Notes'] ?? '') ?></textarea>
          </div>
          <button class="btn-secondary btn-small" type="submit">Save notes</button>
        </form>
        <?php elseif (!empty($order['Notes'])): ?>
        <p class="account-card-lead"><?= nl2br(htmlspecialchars($order['Notes'])) ?></p>
        <?php else: ?>
        <p class="account-card-lead">No internal notes recorded.</p>
        <?php endif; ?>
      </div>

      <div class="account-card" style="margin-top: 20px;">
        <h2>Attachments</h2>
        <?php if ($attachments === []): ?>
        <p class="account-card-lead">No files attached yet.</p>
        <?php else: ?>
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
              <td><a class="btn-text" href="/po-management/attachment.php?id=<?= (int) $file['AttachmentID'] ?>"><?= htmlspecialchars($file['FileName']) ?></a></td>
              <td><?= htmlspecialchars($file['AttachmentKind']) ?></td>
              <td><?= htmlspecialchars(po_format_file_size((int) $file['FileSizeBytes'])) ?></td>
              <td><?= htmlspecialchars(admin_format_datetime($file['UploadDate'])) ?></td>
              <td><?= htmlspecialchars($file['UploadedByName']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>

        <?php if ($showUploadForm): ?>
        <form class="admin-form" method="post" enctype="multipart/form-data" action="/po-management/upload-attachment.php" style="margin-top: 16px;">
          <input type="hidden" name="po_id" value="<?= (int) $order['POID'] ?>" />
          <div class="form-grid">
            <?php
            $uploadFieldId = 'po-attachment-' . (int) $order['POID'];
            $uploadFieldName = 'attachment';
            $uploadLabel = 'Upload PDF or file';
            $uploadTitle = 'Drop, paste, or choose file';
            $uploadHint = 'Drag a file here, click and paste (Ctrl+V / Cmd+V), or choose a file';
            $uploadAccept = '.pdf,.xlsx,.csv,application/pdf';
            $uploadMaxBytes = PO_MAX_ATTACHMENT_BYTES;
            $uploadAllowedExt = ['pdf', 'xlsx', 'csv'];
            $uploadRequired = true;
            $uploadGridClass = '';
            require __DIR__ . '/file-upload-dropzone-field.php';
            ?>
            <div class="form-group">
              <label for="attachment_kind">Attachment type</label>
              <select class="form-input" id="attachment_kind" name="attachment_kind">
                <option value="SourcePDF">Source PDF</option>
                <option value="SignedPDF">Signed PDF</option>
                <option value="ImportExcel">Import Excel</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>
          <button class="btn-secondary btn-small" type="submit">Upload attachment</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if ($approvalLog !== []): ?>
      <div class="account-card" style="margin-top: 20px;">
        <h2>Approval history</h2>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Approver</th>
              <th>Result</th>
              <th>Comments</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($approvalLog as $entry): ?>
            <tr>
              <td><?= htmlspecialchars(admin_format_datetime($entry['LogDate'])) ?></td>
              <td><?= htmlspecialchars($entry['ApproverName']) ?></td>
              <td><?= htmlspecialchars($entry['ApproverResult']) ?></td>
              <td><?= htmlspecialchars($entry['ApproverComments'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
