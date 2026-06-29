<?php

require_once __DIR__ . '/po.php';
require_once __DIR__ . '/quickbooks.php';
require_once __DIR__ . '/po-attachments.php';
require_once __DIR__ . '/attachment-storage.php';

const PO_QBO_ATTACHMENT_KINDS = ['SourcePDF', 'SignedPDF'];
const PO_QBO_ATTACHMENT_MARKER_PREFIX = 'NutraAxis-POAttachment-';

function po_qbo_can_sync(array $order): bool
{
    return po_can_update()
        && qbo_is_connected()
        && po_is_post_approval_edit($order)
        && !po_requires_reapproval($order);
}

function po_qbo_sku_item_map(array $lines): array
{
    $skuCodes = [];
    foreach ($lines as $line) {
        $sku = trim((string) ($line['ItemSKU'] ?? ''));
        if ($sku !== '') {
            $skuCodes[$sku] = true;
        }
    }

    if ($skuCodes === []) {
        return [];
    }

    $pdo = db();
    $placeholders = implode(',', array_fill(0, count($skuCodes), '?'));
    $stmt = $pdo->prepare(
        "SELECT SKUCode, QBO_ItemID, ProductName FROM dbo.SKUMaster WHERE SKUCode IN ({$placeholders})"
    );
    $stmt->execute(array_keys($skuCodes));

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $code = trim((string) ($row['SKUCode'] ?? ''));
        if ($code === '') {
            continue;
        }
        $map[$code] = [
            'qbo_item_id' => trim((string) ($row['QBO_ItemID'] ?? '')),
            'product_name' => trim((string) ($row['ProductName'] ?? '')),
        ];
    }

    return $map;
}

function po_qbo_resolve_item_id(string $sku, array $skuMap): ?string
{
    $sku = trim($sku);
    if ($sku === '') {
        return null;
    }

    $entry = $skuMap[$sku] ?? null;
    $qboId = trim((string) ($entry['qbo_item_id'] ?? ''));
    if ($qboId !== '') {
        return $qboId;
    }

    $fetch = qbo_find_item_by_sku($sku);
    if ($fetch['ok'] && is_array($fetch['item'])) {
        return trim((string) ($fetch['item']['Id'] ?? '')) ?: null;
    }

    return null;
}

function po_qbo_sync_blockers(array $order, array $lines): array
{
    $blockers = [];

    if (!qbo_is_connected()) {
        $blockers[] = 'QuickBooks is not connected.';
    }

    if (!po_is_post_approval_edit($order)) {
        $blockers[] = 'Purchase order must be approved before syncing to QuickBooks.';
    }

    if (po_requires_reapproval($order)) {
        $blockers[] = 'Total due changed after approval. Resubmit for approval before syncing to QuickBooks.';
    }

    if ($lines === []) {
        $blockers[] = 'Purchase order has no line items.';
    }

    $vendorId = trim((string) ($order['QBO_SupplierID'] ?? ''));
    if ($vendorId === '') {
        $blockers[] = 'Supplier is not linked to QuickBooks. Sync the supplier from Supplier Management first.';
    }

    $skuMap = po_qbo_sku_item_map($lines);
    foreach ($lines as $line) {
        $qty = (float) ($line['Quantity'] ?? 0);
        if ($qty <= 0) {
            continue;
        }

        $sku = trim((string) ($line['ItemSKU'] ?? ''));
        if ($sku === '') {
            $blockers[] = 'Line ' . (int) ($line['LineNumber'] ?? 0) . ' is missing a SKU code.';

            continue;
        }

        if (po_qbo_resolve_item_id($sku, $skuMap) === null) {
            $blockers[] = 'SKU ' . $sku . ' is not synced to QuickBooks. Sync it from Product Catalog first.';
        }
    }

    return array_values(array_unique($blockers));
}

function po_qbo_build_payload(array $order, array $lines, array $skuMap): array
{
    $billLines = [];
    foreach ($lines as $line) {
        $qty = (float) ($line['Quantity'] ?? 0);
        if ($qty <= 0) {
            continue;
        }

        $sku = trim((string) ($line['ItemSKU'] ?? ''));
        $itemId = po_qbo_resolve_item_id($sku, $skuMap);
        if ($itemId === null) {
            continue;
        }

        $unitPrice = (float) ($line['UnitPrice'] ?? 0);
        $amount = round($qty * $unitPrice, 2);
        $description = trim((string) ($line['ItemDescription'] ?? ''));
        if ($description === '' && $sku !== '') {
            $description = trim((string) (($skuMap[$sku]['product_name'] ?? '') ?: $sku));
        }

        $billLine = [
            'DetailType' => 'ItemBasedExpenseLineDetail',
            'Amount'     => $amount,
            'ItemBasedExpenseLineDetail' => [
                'ItemRef'   => ['value' => $itemId],
                'Qty'       => $qty,
                'UnitPrice' => $unitPrice,
            ],
        ];
        if ($description !== '') {
            $billLine['Description'] = $description;
        }

        $billLines[] = $billLine;
    }

    $payload = [
        'VendorRef' => ['value' => trim((string) $order['QBO_SupplierID'])],
        'TxnDate'   => (string) $order['OrderDate'],
        'Line'      => $billLines,
    ];

    $poNumber = trim((string) ($order['PONumber'] ?? ''));
    if ($poNumber !== '') {
        $payload['DocNumber'] = mb_substr($poNumber, 0, 21);
    }

    $memoParts = array_filter([
        trim((string) ($order['SpecialInstructions'] ?? '')),
        trim((string) ($order['Notes'] ?? '')),
    ]);
    if ($memoParts !== []) {
        $payload['PrivateNote'] = implode("\n\n", $memoParts);
    }

    if (!empty($order['ExpectedDeliveryDate'])) {
        $payload['DueDate'] = (string) $order['ExpectedDeliveryDate'];
    }

    return $payload;
}

function po_qbo_apply_response(int $poId, array $purchaseOrder): void
{
    $qboId = trim((string) ($purchaseOrder['Id'] ?? ''));
    if ($qboId === '') {
        return;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        UPDATE dbo.PurchaseOrder
        SET QBO_POID = :qbo_po_id,
            POQBOCreated = 1,
            ModifiedDate = SYSUTCDATETIME(),
            ModifiedbyUser = :modified_by
        WHERE POID = :po_id
    SQL);
    $stmt->execute([
        'qbo_po_id'   => $qboId,
        'modified_by' => auth_user()['UserID'] ?? null,
        'po_id'       => $poId,
    ]);
}

function qbo_find_purchase_order_by_doc_number(string $docNumber): array
{
    $docNumber = trim($docNumber);
    if ($docNumber === '') {
        return ['ok' => false, 'error' => 'PO number is required.', 'purchase_order' => null];
    }

    $escaped = str_replace("'", "\\'", $docNumber);
    $result = qbo_query("SELECT * FROM PurchaseOrder WHERE DocNumber = '{$escaped}'");
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'] ?? 'Unable to search QuickBooks purchase orders.', 'purchase_order' => null];
    }

    $rows = qbo_extract_rows($result['data'] ?? [], ['PurchaseOrder']);
    $purchaseOrder = $rows[0] ?? null;

    return [
        'ok'              => is_array($purchaseOrder),
        'error'           => is_array($purchaseOrder) ? null : 'Purchase order not found in QuickBooks.',
        'purchase_order'  => is_array($purchaseOrder) ? $purchaseOrder : null,
    ];
}

function po_qbo_attachment_marker(int $attachmentId): string
{
    return PO_QBO_ATTACHMENT_MARKER_PREFIX . $attachmentId;
}

function po_qbo_is_syncable_pdf_attachment(array $attachment): bool
{
    $kind = trim((string) ($attachment['AttachmentKind'] ?? ''));
    if (in_array($kind, PO_QBO_ATTACHMENT_KINDS, true)) {
        return true;
    }

    $contentType = strtolower(trim((string) ($attachment['ContentType'] ?? '')));
    $fileName = strtolower(trim((string) ($attachment['FileName'] ?? '')));

    return str_contains($contentType, 'pdf') || str_ends_with($fileName, '.pdf');
}

function po_qbo_attachment_qbo_filename(array $attachment, array $order): string
{
    $kind = trim((string) ($attachment['AttachmentKind'] ?? ''));
    $poNumber = trim((string) ($order['PONumber'] ?? ''));
    if ($kind === 'SourcePDF' && $poNumber !== '') {
        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $poNumber) . '.pdf';
    }

    $baseName = trim((string) ($attachment['FileName'] ?? 'attachment.pdf'));
    if ($baseName === '') {
        $baseName = 'attachment-' . (int) ($attachment['AttachmentID'] ?? 0) . '.pdf';
    }

    return preg_replace('/[^A-Za-z0-9._-]+/', '_', $baseName);
}

function po_qbo_find_existing_attachable(array $attachables, int $attachmentId, string $fileName): ?array
{
    $marker = po_qbo_attachment_marker($attachmentId);
    $normalizedName = strtolower(trim($fileName));

    foreach ($attachables as $attachable) {
        if (!is_array($attachable)) {
            continue;
        }

        $note = trim((string) ($attachable['Note'] ?? ''));
        if ($note === $marker) {
            return $attachable;
        }
    }

    foreach ($attachables as $attachable) {
        if (!is_array($attachable)) {
            continue;
        }

        $existingName = strtolower(trim((string) ($attachable['FileName'] ?? '')));
        if ($existingName !== '' && $existingName === $normalizedName) {
            return $attachable;
        }
    }

    return null;
}

function po_qbo_upsert_attachment(string $qboPoId, array $attachment, array $order, array $existingAttachables): array
{
    $attachmentId = (int) ($attachment['AttachmentID'] ?? 0);
    if ($attachmentId <= 0) {
        return ['ok' => false, 'error' => 'Attachment ID is missing.', 'action' => 'error'];
    }

    if (!po_qbo_is_syncable_pdf_attachment($attachment)) {
        return ['ok' => true, 'error' => null, 'action' => 'skipped', 'reason' => 'not a PO PDF attachment'];
    }

    $resolved = attachment_storage_resolve_content($attachment);
    if (!$resolved['ok']) {
        return [
            'ok'     => false,
            'error'  => $resolved['error'] ?? 'Attachment file not found in storage.',
            'action' => 'missing_file',
        ];
    }

    $fileContent = (string) $resolved['content'];
    if ($fileContent === '') {
        return ['ok' => false, 'error' => 'Attachment file is empty.', 'action' => 'missing_file'];
    }

    $fileName = po_qbo_attachment_qbo_filename($attachment, $order);
    $contentType = trim((string) ($resolved['content_type'] ?? ''));
    if ($contentType === '') {
        $contentType = 'application/pdf';
    }

    $localSize = strlen($fileContent);
    $existing = po_qbo_find_existing_attachable($existingAttachables, $attachmentId, $fileName);
    if ($existing !== null) {
        $existingSize = (int) ($existing['Size'] ?? 0);
        if ($existingSize > 0 && $existingSize === $localSize) {
            return [
                'ok'       => true,
                'error'    => null,
                'action'   => 'skipped',
                'reason'   => 'already up to date',
                'fileName' => $fileName,
            ];
        }

        $delete = qbo_delete_attachable(
            (string) ($existing['Id'] ?? ''),
            (string) ($existing['SyncToken'] ?? '')
        );
        if (!$delete['ok']) {
            return [
                'ok'       => false,
                'error'    => $delete['error'] ?? 'Unable to replace the existing QuickBooks attachment.',
                'action'   => 'error',
                'fileName' => $fileName,
            ];
        }
    }

    $upload = qbo_upload_entity_attachment(
        'PurchaseOrder',
        $qboPoId,
        $fileName,
        $contentType,
        $fileContent,
        po_qbo_attachment_marker($attachmentId)
    );
    if (!$upload['ok']) {
        return [
            'ok'       => false,
            'error'    => $upload['error'] ?? 'QuickBooks attachment upload failed.',
            'action'   => 'error',
            'fileName' => $fileName,
        ];
    }

    return [
        'ok'       => true,
        'error'    => null,
        'action'   => $existing !== null ? 'updated' : 'uploaded',
        'fileName' => $fileName,
    ];
}

function po_qbo_sync_attachments(int $poId, string $qboPoId): array
{
    $qboPoId = trim($qboPoId);
    if ($qboPoId === '') {
        return ['ok' => false, 'error' => 'QuickBooks purchase order ID is required.', 'results' => []];
    }

    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.', 'results' => []];
    }

    $listed = po_list_attachments($poId);
    $attachments = [];
    foreach ($listed as $row) {
        $attachment = po_get_attachment((int) ($row['AttachmentID'] ?? 0));
        if ($attachment === null || !po_qbo_is_syncable_pdf_attachment($attachment)) {
            continue;
        }
        $attachments[] = $attachment;
    }

    if ($attachments === []) {
        return [
            'ok'      => true,
            'error'   => null,
            'results' => [],
            'summary' => 'No PO PDF attachment was found to upload to QuickBooks.',
        ];
    }

    usort($attachments, static function (array $left, array $right): int {
        $kindOrder = ['SourcePDF' => 0, 'SignedPDF' => 1];
        $leftKind = $kindOrder[(string) ($left['AttachmentKind'] ?? '')] ?? 99;
        $rightKind = $kindOrder[(string) ($right['AttachmentKind'] ?? '')] ?? 99;
        if ($leftKind !== $rightKind) {
            return $leftKind <=> $rightKind;
        }

        return strcmp((string) ($right['UploadDate'] ?? ''), (string) ($left['UploadDate'] ?? ''));
    });

    $existingResult = qbo_list_entity_attachables('PurchaseOrder', $qboPoId);
    if (!$existingResult['ok']) {
        return [
            'ok'    => false,
            'error' => $existingResult['error'] ?? 'Unable to load QuickBooks attachments.',
            'results' => [],
        ];
    }

    $existingAttachables = $existingResult['attachables'] ?? [];
    $results = [];
    $errors = [];
    $uploaded = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($attachments as $attachment) {
        $result = po_qbo_upsert_attachment($qboPoId, $attachment, $order, $existingAttachables);
        $results[] = $result;

        if (($result['action'] ?? '') === 'uploaded') {
            $uploaded++;
        } elseif (($result['action'] ?? '') === 'updated') {
            $updated++;
        } elseif (($result['action'] ?? '') === 'skipped') {
            $skipped++;
        } elseif (!$result['ok']) {
            $errors[] = trim((string) ($result['fileName'] ?? 'Attachment')) . ': ' . trim((string) ($result['error'] ?? 'Upload failed.'));
        }
    }

    $summaryParts = [];
    if ($uploaded > 0) {
        $summaryParts[] = $uploaded . ' PDF' . ($uploaded === 1 ? '' : 's') . ' uploaded to QuickBooks';
    }
    if ($updated > 0) {
        $summaryParts[] = $updated . ' PDF' . ($updated === 1 ? '' : 's') . ' updated in QuickBooks';
    }
    if ($skipped > 0) {
        $summaryParts[] = $skipped . ' PDF' . ($skipped === 1 ? '' : 's') . ' already up to date in QuickBooks';
    }

    return [
        'ok'      => $errors === [],
        'error'   => $errors === [] ? null : implode(' ', $errors),
        'results' => $results,
        'summary' => $summaryParts === [] ? null : implode('; ', $summaryParts) . '.',
        'uploaded' => $uploaded,
        'updated'  => $updated,
        'skipped'  => $skipped,
    ];
}

function po_qbo_merge_attachment_summary(array $result, array $attachmentResult): array
{
    if (($attachmentResult['summary'] ?? null) !== null) {
        $summary = (string) $attachmentResult['summary'];
        $existingWarning = trim((string) ($result['warning'] ?? ''));
        $result['warning'] = $existingWarning !== '' ? $existingWarning . ' ' . $summary : $summary;
    }

    if (!$attachmentResult['ok'] && !empty($attachmentResult['error'])) {
        $attachmentError = trim((string) $attachmentResult['error']);
        $existingWarning = trim((string) ($result['warning'] ?? ''));
        $result['warning'] = $existingWarning !== ''
            ? $existingWarning . ' Attachment sync issue: ' . $attachmentError
            : 'Attachment sync issue: ' . $attachmentError;
    }

    $result['attachments'] = $attachmentResult;

    return $result;
}

function qbo_fetch_purchase_order(string $qboPoId): array
{
    $qboPoId = trim($qboPoId);
    if ($qboPoId === '') {
        return ['ok' => false, 'error' => 'QuickBooks purchase order ID is required.', 'purchase_order' => null];
    }

    $result = qbo_api_request('GET', '/purchaseorder/' . rawurlencode($qboPoId), ['minorversion' => 65]);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'] ?? 'Unable to load purchase order from QuickBooks.', 'purchase_order' => null];
    }

    $purchaseOrder = $result['data']['PurchaseOrder'] ?? null;

    return [
        'ok'             => is_array($purchaseOrder),
        'error'          => is_array($purchaseOrder) ? null : 'QuickBooks did not return a purchase order.',
        'purchase_order' => is_array($purchaseOrder) ? $purchaseOrder : null,
    ];
}

function qbo_sync_purchase_order(int $poId): array
{
    try {
        if (!qbo_is_connected()) {
            return ['ok' => false, 'error' => 'QuickBooks is not connected.'];
        }

        $order = po_get_order($poId);
        if ($order === null) {
            return ['ok' => false, 'error' => 'Purchase order not found.'];
        }

        $lines = po_get_lines($poId);
        $blockers = po_qbo_sync_blockers($order, $lines);
        if ($blockers !== []) {
            return ['ok' => false, 'error' => implode(' ', $blockers)];
        }

        $existingQboId = trim((string) ($order['QBO_POID'] ?? ''));
        if ($existingQboId !== '') {
            $fetch = qbo_fetch_purchase_order($existingQboId);
            if ($fetch['ok']) {
                $result = [
                    'ok'      => true,
                    'error'   => null,
                    'warning' => 'This purchase order is already linked to QuickBooks (PO #' . $existingQboId . ').',
                    'action'  => 'already_synced',
                    'qbo_id'  => $existingQboId,
                ];

                return po_qbo_merge_attachment_summary(
                    $result,
                    po_qbo_sync_attachments($poId, $existingQboId)
                );
            }
        }

        $skuMap = po_qbo_sku_item_map($lines);
        $payload = po_qbo_build_payload($order, $lines, $skuMap);
        if (($payload['Line'] ?? []) === []) {
            return ['ok' => false, 'error' => 'No billable line items are ready for QuickBooks sync.'];
        }

        $result = qbo_api_request('POST', '/purchaseorder', ['minorversion' => 65], $payload);
        if (!$result['ok']) {
            $reconciled = qbo_find_purchase_order_by_doc_number((string) ($order['PONumber'] ?? ''));
            if ($reconciled['ok'] && is_array($reconciled['purchase_order'])) {
                po_qbo_apply_response($poId, $reconciled['purchase_order']);
                $qboPoId = trim((string) ($reconciled['purchase_order']['Id'] ?? ''));
                $result = [
                    'ok'         => true,
                    'error'      => null,
                    'warning'    => 'QuickBooks already had this PO number. The existing QuickBooks purchase order has been linked here.',
                    'reconciled' => true,
                    'action'     => 'reconciled',
                    'qbo_id'     => $qboPoId,
                ];

                if ($qboPoId !== '') {
                    $result = po_qbo_merge_attachment_summary($result, po_qbo_sync_attachments($poId, $qboPoId));
                }

                return $result;
            }

            return ['ok' => false, 'error' => $result['error'] ?? 'QuickBooks purchase order sync failed.'];
        }

        $purchaseOrder = $result['data']['PurchaseOrder'] ?? null;
        if (!is_array($purchaseOrder) || trim((string) ($purchaseOrder['Id'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'QuickBooks did not return a purchase order ID.'];
        }

        po_qbo_apply_response($poId, $purchaseOrder);

        $qboPoId = (string) $purchaseOrder['Id'];
        $result = [
            'ok'     => true,
            'error'  => null,
            'action' => 'created',
            'qbo_id' => $qboPoId,
        ];

        return po_qbo_merge_attachment_summary($result, po_qbo_sync_attachments($poId, $qboPoId));
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => qbo_sync_format_exception($e, 'sync this purchase order to QuickBooks')];
    }
}
