<?php

require_once __DIR__ . '/quickbooks.php';
require_once __DIR__ . '/supplier-qbo.php';

const QBO_PTOS_TEST_VENDOR_NAMES = [
    'smoke vendor llc',
    'test vendor 123',
];

/**
 * Run a callback while bound to a specific QBO environment, then restore the prior binding.
 *
 * @template T
 * @param callable(): T $callback
 * @return T
 */
function qbo_with_environment(string $env, callable $callback)
{
    $previous = $GLOBALS['_qbo_environment'] ?? null;
    qbo_use_environment($env);
    try {
        return $callback();
    } finally {
        if ($previous !== null) {
            qbo_use_environment((string) $previous);
        } else {
            unset($GLOBALS['_qbo_environment']);
        }
    }
}

function qbo_ptos_require_connections(): ?string
{
    if (!qbo_is_connected(QBO_ENV_PRODUCTION)) {
        return 'QuickBooks Production is not connected.';
    }
    if (!qbo_is_connected(QBO_ENV_SANDBOX)) {
        return 'QuickBooks Sandbox is not connected.';
    }

    return null;
}

function qbo_ptos_is_skipped_vendor(array $vendor): bool
{
    $name = supplier_qbo_normalize_name((string) ($vendor['DisplayName'] ?? ''));
    if ($name === '' || in_array($name, QBO_PTOS_TEST_VENDOR_NAMES, true)) {
        return true;
    }

    if (empty($vendor['Active']) && (str_contains($name, 'test') || str_contains($name, 'smoke'))) {
        return true;
    }

    return false;
}

/**
 * @return list<array<string, mixed>>
 */
function qbo_ptos_query_all(string $entity, string $orderBy = ''): array
{
    $rows = [];
    $start = 1;
    $pageSize = 100;

    for ($page = 0; $page < 100; $page++) {
        $sql = 'SELECT * FROM ' . $entity;
        if ($orderBy !== '') {
            $sql .= ' ORDERBY ' . $orderBy;
        }
        $sql .= ' STARTPOSITION ' . $start . ' MAXRESULTS ' . $pageSize;

        $result = qbo_query($sql, $pageSize);
        if (!$result['ok']) {
            if ($rows !== []) {
                return $rows;
            }

            return [];
        }

        $batch = qbo_extract_rows($result['data'], [$entity]);
        if ($batch === []) {
            break;
        }

        foreach ($batch as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        if (count($batch) < $pageSize) {
            break;
        }

        $start += $pageSize;
    }

    return $rows;
}

function qbo_ptos_vendor_payload_from_source(array $vendor): array
{
    $payload = [
        'DisplayName' => (string) ($vendor['DisplayName'] ?? ''),
        'Active'      => !empty($vendor['Active']),
    ];

    if (array_key_exists('Vendor1099', $vendor)) {
        $payload['Vendor1099'] = !empty($vendor['Vendor1099']);
    }

    $stringFields = [
        'CompanyName', 'PrintOnCheckName', 'Title', 'GivenName', 'MiddleName',
        'FamilyName', 'Suffix', 'TaxIdentifier', 'AcctNum',
    ];
    foreach ($stringFields as $field) {
        $value = trim((string) ($vendor[$field] ?? ''));
        if ($value !== '') {
            $payload[$field] = $value;
        }
    }

    foreach (['PrimaryEmailAddr', 'PrimaryPhone', 'Mobile', 'AlternatePhone', 'Fax', 'WebAddr', 'BillAddr', 'ShipAddr'] as $field) {
        if (!empty($vendor[$field]) && is_array($vendor[$field])) {
            $payload[$field] = $vendor[$field];
        }
    }

    if (!empty($vendor['TermRef']) && is_array($vendor['TermRef'])) {
        $payload['TermRef'] = $vendor['TermRef'];
    }

    return $payload;
}

function qbo_ptos_find_vendor_by_display_name(string $displayName): ?array
{
    $result = qbo_find_vendor_by_display_name($displayName);

    return $result['ok'] ? ($result['vendor'] ?? null) : null;
}

function qbo_ptos_find_purchase_order_by_doc_number(string $docNumber): ?array
{
    $docNumber = trim($docNumber);
    if ($docNumber === '') {
        return null;
    }

    $escaped = str_replace("'", "\\'", $docNumber);
    $result = qbo_query("SELECT * FROM PurchaseOrder WHERE DocNumber = '" . $escaped . "' MAXRESULTS 5");
    if (!$result['ok']) {
        return null;
    }

    $rows = qbo_extract_rows($result['data'], ['PurchaseOrder']);

    return is_array($rows[0] ?? null) ? $rows[0] : null;
}

/**
 * @return array<string, string>
 */
function qbo_ptos_build_vendor_id_map(): array
{
    $prodVendors = qbo_with_environment(QBO_ENV_PRODUCTION, fn(): array => qbo_ptos_query_all('Vendor', 'DisplayName'));
    $sandboxVendors = qbo_with_environment(QBO_ENV_SANDBOX, fn(): array => qbo_ptos_query_all('Vendor', 'DisplayName'));

    $sandboxByName = [];
    foreach ($sandboxVendors as $vendor) {
        $norm = supplier_qbo_normalize_name((string) ($vendor['DisplayName'] ?? ''));
        if ($norm !== '' && !isset($sandboxByName[$norm])) {
            $sandboxByName[$norm] = $vendor;
        }
    }

    $map = [];
    foreach ($prodVendors as $vendor) {
        $prodId = trim((string) ($vendor['Id'] ?? ''));
        $norm = supplier_qbo_normalize_name((string) ($vendor['DisplayName'] ?? ''));
        if ($prodId === '' || $norm === '') {
            continue;
        }

        $sandboxVendor = $sandboxByName[$norm] ?? null;
        if (is_array($sandboxVendor) && !empty($sandboxVendor['Id'])) {
            $map[$prodId] = (string) $sandboxVendor['Id'];
        }
    }

    return $map;
}

/**
 * @return array<string, string>
 */
function qbo_ptos_build_account_id_map(): array
{
    $prodAccounts = qbo_with_environment(QBO_ENV_PRODUCTION, fn(): array => qbo_ptos_query_all('Account', 'Name'));
    $sandboxAccounts = qbo_with_environment(QBO_ENV_SANDBOX, fn(): array => qbo_ptos_query_all('Account', 'Name'));

    $sandboxByAcctNum = [];
    $sandboxByFqn = [];
    $sandboxByNameType = [];
    foreach ($sandboxAccounts as $account) {
        if (empty($account['Active'])) {
            continue;
        }

        $id = trim((string) ($account['Id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $acctNum = trim((string) ($account['AcctNum'] ?? ''));
        if ($acctNum !== '') {
            $sandboxByAcctNum[$acctNum] = $id;
        }

        $fqn = mb_strtolower(trim((string) ($account['FullyQualifiedName'] ?? '')));
        if ($fqn !== '') {
            $sandboxByFqn[$fqn] = $id;
        }

        $nameType = mb_strtolower(trim((string) ($account['Name'] ?? ''))) . '|' . mb_strtolower(trim((string) ($account['AccountType'] ?? '')));
        if (!isset($sandboxByNameType[$nameType])) {
            $sandboxByNameType[$nameType] = $id;
        }
    }

    $map = [];
    foreach ($prodAccounts as $account) {
        $prodId = trim((string) ($account['Id'] ?? ''));
        if ($prodId === '') {
            continue;
        }

        $acctNum = trim((string) ($account['AcctNum'] ?? ''));
        if ($acctNum !== '' && isset($sandboxByAcctNum[$acctNum])) {
            $map[$prodId] = $sandboxByAcctNum[$acctNum];
            continue;
        }

        $fqn = mb_strtolower(trim((string) ($account['FullyQualifiedName'] ?? '')));
        if ($fqn !== '' && isset($sandboxByFqn[$fqn])) {
            $map[$prodId] = $sandboxByFqn[$fqn];
            continue;
        }

        $nameType = mb_strtolower(trim((string) ($account['Name'] ?? ''))) . '|' . mb_strtolower(trim((string) ($account['AccountType'] ?? '')));
        if (isset($sandboxByNameType[$nameType])) {
            $map[$prodId] = $sandboxByNameType[$nameType];
        }
    }

    return $map;
}

/**
 * @return array<string, string>
 */
function qbo_ptos_build_item_id_map(): array
{
    $prodItems = qbo_with_environment(QBO_ENV_PRODUCTION, fn(): array => qbo_ptos_query_all('Item', 'Name'));
    $sandboxItems = qbo_with_environment(QBO_ENV_SANDBOX, fn(): array => qbo_ptos_query_all('Item', 'Name'));

    $sandboxByName = [];
    foreach ($sandboxItems as $item) {
        if (empty($item['Active'])) {
            continue;
        }
        $name = mb_strtolower(trim((string) ($item['Name'] ?? '')));
        $id = trim((string) ($item['Id'] ?? ''));
        if ($name !== '' && $id !== '' && !isset($sandboxByName[$name])) {
            $sandboxByName[$name] = $id;
        }
    }

    $map = [];
    foreach ($prodItems as $item) {
        $prodId = trim((string) ($item['Id'] ?? ''));
        $name = mb_strtolower(trim((string) ($item['Name'] ?? '')));
        if ($prodId === '' || $name === '') {
            continue;
        }
        if (isset($sandboxByName[$name])) {
            $map[$prodId] = $sandboxByName[$name];
        }
    }

    return $map;
}

function qbo_ptos_default_expense_account_id(): ?string
{
    return qbo_with_environment(QBO_ENV_SANDBOX, function (): ?string {
        $accounts = qbo_ptos_query_all('Account', 'Name');
        foreach ($accounts as $account) {
            if (empty($account['Active'])) {
                continue;
            }
            $type = strtolower((string) ($account['AccountType'] ?? ''));
            $sub = strtolower((string) ($account['AccountSubType'] ?? ''));
            if ($type === 'expense' || str_contains($sub, 'expense')) {
                $id = trim((string) ($account['Id'] ?? ''));
                if ($id !== '') {
                    return $id;
                }
            }
        }

        return null;
    });
}

function qbo_ptos_map_account_ref(string $prodAccountId, array $accountMap): ?string
{
    $prodAccountId = trim($prodAccountId);
    if ($prodAccountId !== '' && isset($accountMap[$prodAccountId])) {
        return $accountMap[$prodAccountId];
    }

    return qbo_ptos_default_expense_account_id();
}

/**
 * @param array<string, string> $vendorMap
 * @param array<string, string> $accountMap
 * @param array<string, string> $itemMap
 * @return list<array<string, mixed>>
 */
function qbo_ptos_map_txn_lines(array $lines, array $vendorMap, array $accountMap, array $itemMap): array
{
    $mapped = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }

        $amount = (float) ($line['Amount'] ?? 0);
        if ($amount <= 0) {
            continue;
        }

        $detailType = (string) ($line['DetailType'] ?? '');
        $mappedLine = [
            'Amount'     => round($amount, 2),
            'DetailType' => $detailType,
        ];
        if (!empty($line['Description'])) {
            $mappedLine['Description'] = (string) $line['Description'];
        }

        if ($detailType === 'AccountBasedExpenseLineDetail') {
            $prodAcctId = (string) ($line['AccountBasedExpenseLineDetail']['AccountRef']['value'] ?? '');
            $sandboxAcctId = qbo_ptos_map_account_ref($prodAcctId, $accountMap);
            if ($sandboxAcctId === null) {
                continue;
            }
            $mappedLine['AccountBasedExpenseLineDetail'] = [
                'AccountRef' => ['value' => $sandboxAcctId],
            ];
            $mapped[] = $mappedLine;
            continue;
        }

        if ($detailType === 'ItemBasedExpenseLineDetail') {
            $prodItemId = (string) ($line['ItemBasedExpenseLineDetail']['ItemRef']['value'] ?? '');
            $sandboxItemId = $prodItemId !== '' ? ($itemMap[$prodItemId] ?? null) : null;
            if ($sandboxItemId !== null) {
                $detail = [
                    'ItemRef' => ['value' => $sandboxItemId],
                ];
                if (isset($line['ItemBasedExpenseLineDetail']['Qty'])) {
                    $detail['Qty'] = (float) $line['ItemBasedExpenseLineDetail']['Qty'];
                }
                if (isset($line['ItemBasedExpenseLineDetail']['UnitPrice'])) {
                    $detail['UnitPrice'] = (float) $line['ItemBasedExpenseLineDetail']['UnitPrice'];
                }
                $mappedLine['ItemBasedExpenseLineDetail'] = $detail;
                $mapped[] = $mappedLine;
                continue;
            }

            $sandboxAcctId = qbo_ptos_map_account_ref('', $accountMap);
            if ($sandboxAcctId === null) {
                continue;
            }
            $mapped[] = [
                'Amount'     => round($amount, 2),
                'DetailType' => 'AccountBasedExpenseLineDetail',
                'Description'=> (string) ($line['Description'] ?? 'Imported line'),
                'AccountBasedExpenseLineDetail' => [
                    'AccountRef' => ['value' => $sandboxAcctId],
                ],
            ];
        }
    }

    return $mapped;
}

/**
 * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
 */
function qbo_sync_production_to_sandbox_vendors(): array
{
    $connectionError = qbo_ptos_require_connections();
    if ($connectionError !== null) {
        return [
            'summary' => ['errors' => 1],
            'rows'    => [['action' => 'error', 'name' => '—', 'detail' => $connectionError]],
        ];
    }

    $prodVendors = qbo_with_environment(QBO_ENV_PRODUCTION, fn(): array => qbo_ptos_query_all('Vendor', 'DisplayName'));
    $rows = [];
    $summary = [
        'linked'         => 0,
        'created'        => 0,
        'skipped'        => 0,
        'errors'         => 0,
    ];

    foreach ($prodVendors as $vendor) {
        $displayName = trim((string) ($vendor['DisplayName'] ?? ''));
        if ($displayName === '' || qbo_ptos_is_skipped_vendor($vendor)) {
            $summary['skipped']++;
            $rows[] = ['action' => 'skipped', 'name' => $displayName !== '' ? $displayName : '—', 'detail' => 'Test/inactive vendor skipped.'];
            continue;
        }

        $existing = qbo_with_environment(QBO_ENV_SANDBOX, fn(): ?array => qbo_ptos_find_vendor_by_display_name($displayName));
        if (is_array($existing) && !empty($existing['Id'])) {
            $summary['linked']++;
            $rows[] = ['action' => 'linked', 'name' => $displayName, 'detail' => 'Sandbox vendor already exists (matched by display name).'];
            continue;
        }

        $payload = qbo_ptos_vendor_payload_from_source($vendor);
        $create = qbo_with_environment(QBO_ENV_SANDBOX, fn(): array => qbo_api_request('POST', '/vendor', ['minorversion' => 65], $payload));
        if (!$create['ok']) {
            $summary['errors']++;
            $rows[] = ['action' => 'error', 'name' => $displayName, 'detail' => (string) ($create['error'] ?? 'Unable to create sandbox vendor.')];
            continue;
        }

        $created = qbo_extract_vendor($create['data']);
        if ($created === null || empty($created['Id'])) {
            $summary['errors']++;
            $rows[] = ['action' => 'error', 'name' => $displayName, 'detail' => 'QuickBooks Sandbox did not return a vendor ID.'];
            continue;
        }

        $summary['created']++;
        $rows[] = ['action' => 'created', 'name' => $displayName, 'detail' => 'Created vendor in Sandbox from Production.'];
    }

    return ['summary' => $summary, 'rows' => $rows];
}

/**
 * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
 */
function qbo_sync_production_to_sandbox_purchase_orders(): array
{
    $connectionError = qbo_ptos_require_connections();
    if ($connectionError !== null) {
        return [
            'summary' => ['errors' => 1],
            'rows'    => [['action' => 'error', 'name' => '—', 'detail' => $connectionError]],
        ];
    }

    $vendorMap = qbo_ptos_build_vendor_id_map();
    $accountMap = qbo_ptos_build_account_id_map();
    $itemMap = qbo_ptos_build_item_id_map();
    $prodPos = qbo_with_environment(QBO_ENV_PRODUCTION, fn(): array => qbo_ptos_query_all('PurchaseOrder', 'TxnDate DESC'));

    $rows = [];
    $summary = [
        'linked'  => 0,
        'created' => 0,
        'skipped' => 0,
        'errors'  => 0,
    ];

    foreach ($prodPos as $po) {
        $docNumber = trim((string) ($po['DocNumber'] ?? ''));
        $label = $docNumber !== '' ? $docNumber : ('QBO-PO-' . trim((string) ($po['Id'] ?? '')));

        if ($docNumber !== '') {
            $existing = qbo_with_environment(QBO_ENV_SANDBOX, fn(): ?array => qbo_ptos_find_purchase_order_by_doc_number($docNumber));
            if (is_array($existing) && !empty($existing['Id'])) {
                $summary['linked']++;
                $rows[] = ['action' => 'linked', 'name' => $label, 'detail' => 'Sandbox purchase order already exists.'];
                continue;
            }
        }

        $prodVendorId = trim((string) ($po['VendorRef']['value'] ?? ''));
        $sandboxVendorId = $prodVendorId !== '' ? ($vendorMap[$prodVendorId] ?? null) : null;
        if ($sandboxVendorId === null) {
            $vendorName = trim((string) ($po['VendorRef']['name'] ?? ''));
            if ($vendorName !== '') {
                $found = qbo_with_environment(QBO_ENV_SANDBOX, fn(): ?array => qbo_ptos_find_vendor_by_display_name($vendorName));
                $sandboxVendorId = is_array($found) ? trim((string) ($found['Id'] ?? '')) : null;
            }
        }
        if ($sandboxVendorId === null || $sandboxVendorId === '') {
            $summary['errors']++;
            $rows[] = ['action' => 'error', 'name' => $label, 'detail' => 'Vendor is not present in Sandbox. Run vendor sync first.'];
            continue;
        }

        $mappedLines = qbo_ptos_map_txn_lines(is_array($po['Line'] ?? null) ? $po['Line'] : [], $vendorMap, $accountMap, $itemMap);
        if ($mappedLines === []) {
            $summary['skipped']++;
            $rows[] = ['action' => 'skipped', 'name' => $label, 'detail' => 'No mappable PO lines for Sandbox.'];
            continue;
        }

        $payload = [
            'VendorRef' => ['value' => $sandboxVendorId],
            'TxnDate'   => (string) ($po['TxnDate'] ?? date('Y-m-d')),
            'Line'      => $mappedLines,
        ];
        if ($docNumber !== '') {
            $payload['DocNumber'] = $docNumber;
        }

        $create = qbo_with_environment(QBO_ENV_SANDBOX, fn(): array => qbo_api_request('POST', '/purchaseorder', ['minorversion' => 65], $payload));
        if (!$create['ok']) {
            $summary['errors']++;
            $rows[] = ['action' => 'error', 'name' => $label, 'detail' => (string) ($create['error'] ?? 'Unable to create sandbox purchase order.')];
            continue;
        }

        $summary['created']++;
        $rows[] = ['action' => 'created', 'name' => $label, 'detail' => 'Created purchase order in Sandbox from Production.'];
    }

    return ['summary' => $summary, 'rows' => $rows];
}

/**
 * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
 */
function qbo_sync_production_to_sandbox_bills(): array
{
    $connectionError = qbo_ptos_require_connections();
    if ($connectionError !== null) {
        return [
            'summary' => ['errors' => 1],
            'rows'    => [['action' => 'error', 'name' => '—', 'detail' => $connectionError]],
        ];
    }

    $vendorMap = qbo_ptos_build_vendor_id_map();
    $accountMap = qbo_ptos_build_account_id_map();
    $itemMap = qbo_ptos_build_item_id_map();
    $prodBills = qbo_with_environment(QBO_ENV_PRODUCTION, fn(): array => qbo_ptos_query_all('Bill', 'TxnDate DESC'));

    $rows = [];
    $summary = [
        'linked'  => 0,
        'created' => 0,
        'skipped' => 0,
        'errors'  => 0,
    ];

    foreach ($prodBills as $bill) {
        $docNumber = trim((string) ($bill['DocNumber'] ?? ''));
        $label = $docNumber !== '' ? $docNumber : ('QBO-Bill-' . trim((string) ($bill['Id'] ?? '')));

        if ($docNumber !== '') {
            $prodVendorId = trim((string) ($bill['VendorRef']['value'] ?? ''));
            $sandboxVendorId = $prodVendorId !== '' ? ($vendorMap[$prodVendorId] ?? null) : null;
            $existing = qbo_with_environment(QBO_ENV_SANDBOX, fn(): ?array => qbo_find_bill_by_doc_number($docNumber, $sandboxVendorId));
            if (is_array($existing) && !empty($existing['Id'])) {
                $summary['linked']++;
                $rows[] = ['action' => 'linked', 'name' => $label, 'detail' => 'Sandbox bill already exists.'];
                continue;
            }
        }

        $prodVendorId = trim((string) ($bill['VendorRef']['value'] ?? ''));
        $sandboxVendorId = $prodVendorId !== '' ? ($vendorMap[$prodVendorId] ?? null) : null;
        if ($sandboxVendorId === null) {
            $vendorName = trim((string) ($bill['VendorRef']['name'] ?? ''));
            if ($vendorName !== '') {
                $found = qbo_with_environment(QBO_ENV_SANDBOX, fn(): ?array => qbo_ptos_find_vendor_by_display_name($vendorName));
                $sandboxVendorId = is_array($found) ? trim((string) ($found['Id'] ?? '')) : null;
            }
        }
        if ($sandboxVendorId === null || $sandboxVendorId === '') {
            $summary['errors']++;
            $rows[] = ['action' => 'error', 'name' => $label, 'detail' => 'Vendor is not present in Sandbox. Run vendor sync first.'];
            continue;
        }

        $mappedLines = qbo_ptos_map_txn_lines(is_array($bill['Line'] ?? null) ? $bill['Line'] : [], $vendorMap, $accountMap, $itemMap);
        if ($mappedLines === []) {
            $summary['skipped']++;
            $rows[] = ['action' => 'skipped', 'name' => $label, 'detail' => 'No mappable bill lines for Sandbox.'];
            continue;
        }

        $payload = [
            'VendorRef' => ['value' => $sandboxVendorId],
            'TxnDate'   => (string) ($bill['TxnDate'] ?? date('Y-m-d')),
            'Line'      => $mappedLines,
        ];
        if ($docNumber !== '') {
            $payload['DocNumber'] = $docNumber;
        }
        if (!empty($bill['DueDate'])) {
            $payload['DueDate'] = (string) $bill['DueDate'];
        }

        $create = qbo_with_environment(QBO_ENV_SANDBOX, fn(): array => qbo_api_request('POST', '/bill', ['minorversion' => 65], $payload));
        if (!$create['ok']) {
            $summary['errors']++;
            $rows[] = ['action' => 'error', 'name' => $label, 'detail' => (string) ($create['error'] ?? 'Unable to create sandbox bill.')];
            continue;
        }

        $summary['created']++;
        $rows[] = ['action' => 'created', 'name' => $label, 'detail' => 'Created bill in Sandbox from Production.'];
    }

    return ['summary' => $summary, 'rows' => $rows];
}
