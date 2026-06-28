<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin.php';

const CATALOG_PERMISSION_COLUMN = 'ProductCatalog';

const CATALOG_BRANDS = ['NutraSync', 'NutraAxis'];

const CATALOG_MANUFACTURERS = ['NutraSeal', 'VitaQuest', 'IFF-HealthWright', 'Other'];

const CATALOG_THERAPEUTIC_CATEGORIES = [
    'Hormonal Support',
    'GI Health',
    'Longevity',
    'Metabolic',
    'Musculoskeletal',
    'Cardiovascular',
    'Sexual Wellness',
    'Prenatal',
    'Other',
];

const CATALOG_SKU_STATUSES = [
    'In Development',
    'Active',
    'Discontinued',
    'On Hold',
];

const CATALOG_QBO_SYNC_STATUSES = ['NotSynced', 'Synced', 'Error', 'Pending'];

const CATALOG_QBO_NAME_MAX_LENGTH = 100;

const CATALOG_QBO_DESCRIPTION_MAX_LENGTH = 4000;

const CATALOG_LABEL_SELECTIONS = [
    'Teal Only',
    'Teal and Coral',
];

const CATALOG_ALLERGENS = [
    'None',
    'Contains Soy',
    'Contains Dairy',
    'Contains Tree Nuts',
    'Contains Wheat/Gluten',
    'Contains Shellfish',
    'Contains Fish',
    'Contains Eggs',
    'Contains Peanuts',
];

const CATALOG_LIST_SORT_COLUMNS = [
    'sku_code'       => 'SKU code',
    'product_name'   => 'Product name',
    'upc'            => 'UPC',
    'brand'          => 'Brand',
    'category'       => 'Category',
    'status'         => 'Status',
    'serving_count'  => '#SRV',
    'capsule_count'  => '#CAP',
    'cogs'           => 'COGS',
    'wholesale_price'=> 'WHSLE',
    'msrp'           => 'MSRP',
    'qbo'            => 'QuickBooks',
];

const CATALOG_LIST_COLUMN_CLASSES = [
    'product_name'    => 'catalog-col-product-name',
    'serving_count'   => 'catalog-col-srv',
    'capsule_count'   => 'catalog-col-cap',
    'wholesale_price' => 'catalog-col-whsle',
];

const CATALOG_LIST_SORT_SQL = [
    'sku_code'        => 's.SKUCode',
    'product_name'    => 's.ProductName',
    'upc'             => 's.UPC',
    'brand'           => 's.Brand',
    'category'        => 's.PrimaryTherapeuticCategory',
    'status'          => 's.SKUStatus',
    'serving_count'   => 's.ServingCount',
    'capsule_count'   => 's.CapsuleCount',
    'cogs'            => 's.COGS',
    'wholesale_price' => 's.WholesalePrice',
    'msrp'            => 's.MSRP',
    'qbo'             => 's.QBO_SyncStatus',
];

function catalog_list_column_class(string $column): string
{
    return CATALOG_LIST_COLUMN_CLASSES[$column] ?? '';
}

function catalog_list_sort_state(array $input = []): array
{
    $sort = strtolower(trim((string) ($input['sort'] ?? $_GET['sort'] ?? 'sku_code')));
    $dir = strtolower(trim((string) ($input['dir'] ?? $_GET['dir'] ?? 'asc')));

    if (!array_key_exists($sort, CATALOG_LIST_SORT_COLUMNS)) {
        $sort = 'sku_code';
    }

    if ($dir !== 'desc') {
        $dir = 'asc';
    }

    return ['sort' => $sort, 'dir' => $dir];
}

function catalog_list_filters(): array
{
    return [
        'status'   => trim($_GET['status'] ?? ''),
        'brand'    => trim($_GET['brand'] ?? ''),
        'category' => trim($_GET['category'] ?? ''),
        'q'        => trim($_GET['q'] ?? ''),
    ] + catalog_list_sort_state();
}

function catalog_list_sort_href(string $column, array $filters): string
{
    $sortState = catalog_list_sort_state($filters);
    $currentSort = $sortState['sort'];
    $currentDir = $sortState['dir'];

    if ($currentSort === $column) {
        $nextDir = $currentDir === 'asc' ? 'desc' : 'asc';
    } else {
        $nextDir = in_array($column, ['cogs', 'wholesale_price', 'msrp', 'serving_count', 'capsule_count'], true) ? 'desc' : 'asc';
    }

    $query = array_filter([
        'status'   => ($filters['status'] ?? '') !== '' ? $filters['status'] : null,
        'brand'    => ($filters['brand'] ?? '') !== '' ? $filters['brand'] : null,
        'category' => ($filters['category'] ?? '') !== '' ? $filters['category'] : null,
        'q'        => ($filters['q'] ?? '') !== '' ? $filters['q'] : null,
        'sort'     => $column,
        'dir'      => $nextDir,
    ], fn($value) => $value !== null && $value !== '');

    return '/product-catalog/?' . http_build_query($query);
}

function catalog_sort_is_active(string $column, array $filters): bool
{
    return ($filters['sort'] ?? 'sku_code') === $column;
}

function catalog_sort_direction(string $column, array $filters): string
{
    if (!catalog_sort_is_active($column, $filters)) {
        return '';
    }

    return ($filters['dir'] ?? 'asc') === 'asc' ? 'asc' : 'desc';
}

function catalog_permission_value(): ?string
{
    return auth_permission_value(CATALOG_PERMISSION_COLUMN);
}

function catalog_can_read(): bool
{
    return auth_can_read(CATALOG_PERMISSION_COLUMN);
}

function catalog_can_create(): bool
{
    return auth_can_create(CATALOG_PERMISSION_COLUMN);
}

function catalog_can_update(): bool
{
    return auth_can_update(CATALOG_PERMISSION_COLUMN);
}

function catalog_can_delete(): bool
{
    return auth_can_delete(CATALOG_PERMISSION_COLUMN);
}

function catalog_require_read(): void
{
    auth_require_login();
    if (catalog_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view the Product Catalog.');
}

function catalog_require_create(): void
{
    catalog_require_read();
    if (catalog_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create SKUs.');
}

function catalog_require_update(): void
{
    catalog_require_read();
    if (catalog_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update SKUs.');
}

function catalog_require_delete(): void
{
    catalog_require_read();
    if (catalog_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete SKUs.');
}

function catalog_status_class(string $status): string
{
    return match ($status) {
        'In Development' => 'status-draft',
        'Active'         => 'status-received',
        'Discontinued'   => 'status-cancelled',
        'On Hold'        => 'status-submitted',
        default          => 'status-draft',
    };
}

function catalog_qbo_sync_status_label(string $status): string
{
    return match ($status) {
        'Synced'  => 'Synced to QuickBooks',
        'Error'   => 'QuickBooks sync error',
        'Pending' => 'Sync pending',
        default   => 'Not synced to QuickBooks',
    };
}

function catalog_qbo_sync_status_class(string $status): string
{
    return match ($status) {
        'Synced'  => 'status-received',
        'Error'   => 'status-cancelled',
        'Pending' => 'status-submitted',
        default   => 'status-draft',
    };
}

function catalog_qbo_sync_status_short_label(string $status): string
{
    return match ($status) {
        'Synced'  => 'Synced',
        'Error'   => 'Error',
        'Pending' => 'Pending',
        default   => 'Not synced',
    };
}

function catalog_format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return $value;
    }
}

function catalog_format_money($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return '$' . number_format((float) $value, 2);
}

function catalog_format_weight($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $formatted = rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');

    return $formatted . ' lbs';
}

function catalog_date_input(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d');
    } catch (Throwable) {
        return '';
    }
}

function catalog_allergens_from_storage(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value))));
}

function catalog_allergens_to_storage(array $selected): string
{
    $valid = array_values(array_intersect($selected, CATALOG_ALLERGENS));
    if ($valid === []) {
        return '';
    }

    if (in_array('None', $valid, true)) {
        return 'None';
    }

    return implode(', ', $valid);
}

function catalog_resolve_qbo_account_ref(array &$data, string $prefix): void
{
    $valueKey = $prefix . '_account_ref_value';
    $nameKey = $prefix . '_account_ref_name';
    $value = trim((string) ($data[$valueKey] ?? ''));
    $name = trim((string) ($data[$nameKey] ?? ''));

    if ($value === '') {
        $data[$valueKey] = '';
        $data[$nameKey] = '';

        return;
    }

    $purpose = match ($prefix) {
        'qbo_income'  => 'income',
        'qbo_expense' => 'expense',
        'qbo_asset'   => 'asset',
        default       => null,
    };

    if ($purpose !== null) {
        foreach (catalog_qbo_account_options($purpose) as $option) {
            if ((string) $option['id'] === $value) {
                $data[$valueKey] = (string) $option['id'];
                $data[$nameKey] = (string) $option['name'];

                return;
            }
        }
    }

    $data[$valueKey] = $value;
    $data[$nameKey] = $name;
}

function catalog_supplier_options(?int $selectedId = null): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT SupplierID, SupplierName, SupplierCode, IsActive
        FROM dbo.Supplier
        ORDER BY SupplierName
    SQL);
    $options = [];

    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['SupplierID'];
        $isActive = !empty($row['IsActive']);
        if (!$isActive && $selectedId !== $id) {
            continue;
        }

        $label = (string) $row['SupplierName'];
        if (!empty($row['SupplierCode'])) {
            $label .= ' (' . $row['SupplierCode'] . ')';
        }
        if (!$isActive) {
            $label .= ' — Inactive';
        }

        $options[] = [
            'id'    => $id,
            'label' => $label,
        ];
    }

    return $options;
}

function catalog_sku_to_form(array $sku): array
{
    return [
        'sku_id'                      => (int) $sku['SKUID'],
        'sku_code'                    => (string) $sku['SKUCode'],
        'product_name'                => (string) $sku['ProductName'],
        'supplier_id'                 => $sku['SupplierID'] !== null ? (string) $sku['SupplierID'] : '',
        'brand'                       => (string) $sku['Brand'],
        'manufacturer'                => (string) $sku['Manufacturer'],
        'primary_therapeutic_category'=> (string) $sku['PrimaryTherapeuticCategory'],
        'secondary_category'          => (string) ($sku['SecondaryCategory'] ?? ''),
        'sku_status'                  => (string) $sku['SKUStatus'],
        'serving_count'               => $sku['ServingCount'] !== null ? (string) $sku['ServingCount'] : '',
        'bottle_size'                 => (string) ($sku['BottleSize'] ?? ''),
        'gtin14'                      => (string) ($sku['GTIN14'] ?? ''),
        'upc'                         => (string) ($sku['UPC'] ?? ''),
        'sku_case_barcode'            => (string) ($sku['SKUCaseBarcode'] ?? ''),
        'product_each_weight_lbs'     => $sku['ProductEachWeightLbs'] !== null ? (string) $sku['ProductEachWeightLbs'] : '',
        'product_case_weight_lbs'     => $sku['ProductCaseWeightLbs'] !== null ? (string) $sku['ProductCaseWeightLbs'] : '',
        'supplement_facts_panel'      => (string) ($sku['SupplementFactsPanel'] ?? ''),
        'claims'                      => (string) ($sku['Claims'] ?? ''),
        'allergens'                   => catalog_allergens_from_storage($sku['AllergenStatement'] ?? null),
        'non_gmo_certified'           => !empty($sku['NonGMOCertified']),
        'cogs'                        => $sku['COGS'] !== null ? (string) $sku['COGS'] : '',
        'wholesale_price'             => $sku['WholesalePrice'] !== null ? (string) $sku['WholesalePrice'] : '',
        'msrp'                        => $sku['MSRP'] !== null ? (string) $sku['MSRP'] : '',
        'sfp_link'                    => (string) ($sku['SFPLink'] ?? ''),
        'label_print_ready_link'      => (string) ($sku['LabelPrintReadyLink'] ?? ''),
        'launch_date'                 => catalog_date_input($sku['LaunchDate'] ?? null),
        'notes'                       => (string) ($sku['Notes'] ?? ''),
        'formulation'                 => (string) ($sku['Formulation'] ?? ''),
        'product'                     => (string) ($sku['Product'] ?? ''),
        'label_selection'             => (string) ($sku['LabelSelection'] ?? ''),
        'directions'                  => (string) ($sku['Directions'] ?? ''),
        'capsule_count'               => $sku['CapsuleCount'] !== null ? (string) $sku['CapsuleCount'] : '',
        'certs_on_label'              => (string) ($sku['CertsOnLabel'] ?? ''),
        'qbo_purchase_desc'           => (string) ($sku['QBO_PurchaseDesc'] ?? ''),
        'qbo_taxable'                 => !array_key_exists('QBO_Taxable', $sku) || !empty($sku['QBO_Taxable']),
        'qbo_income_account_ref_value'=> (string) ($sku['QBO_IncomeAccountRefValue'] ?? ''),
        'qbo_income_account_ref_name' => (string) ($sku['QBO_IncomeAccountRefName'] ?? ''),
        'qbo_expense_account_ref_value'=> (string) ($sku['QBO_ExpenseAccountRefValue'] ?? ''),
        'qbo_expense_account_ref_name' => (string) ($sku['QBO_ExpenseAccountRefName'] ?? ''),
        'qbo_asset_account_ref_value' => (string) ($sku['QBO_AssetAccountRefValue'] ?? ''),
        'qbo_asset_account_ref_name'  => (string) ($sku['QBO_AssetAccountRefName'] ?? ''),
    ];
}

function catalog_list_skus(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            s.SKUID,
            s.SKUCode,
            s.ProductName,
            s.Brand,
            s.Manufacturer,
            s.PrimaryTherapeuticCategory,
            s.SecondaryCategory,
            s.SKUStatus,
            s.ServingCount,
            s.CapsuleCount,
            s.BottleSize,
            s.GTIN14,
            s.UPC,
            s.COGS,
            s.WholesalePrice,
            s.MSRP,
            s.LaunchDate,
            s.QBO_SyncStatus
        FROM dbo.SKUMaster s
        WHERE 1 = 1
    SQL;
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= ' AND s.SKUStatus = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['brand'])) {
        $sql .= ' AND s.Brand = :brand';
        $params['brand'] = $filters['brand'];
    }

    if (!empty($filters['category'])) {
        $sql .= ' AND (
            s.PrimaryTherapeuticCategory = :category_primary OR
            s.SecondaryCategory = :category_secondary
        )';
        $params['category_primary'] = $filters['category'];
        $params['category_secondary'] = $filters['category'];
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (
            s.SKUCode LIKE :q_sku OR
            s.ProductName LIKE :q_name OR
            s.GTIN14 LIKE :q_gtin OR
            s.UPC LIKE :q_upc
        )';
        $like = '%' . $filters['q'] . '%';
        $params['q_sku'] = $like;
        $params['q_name'] = $like;
        $params['q_gtin'] = $like;
        $params['q_upc'] = $like;
    }

    $sortState = catalog_list_sort_state($filters);
    $sortColumn = CATALOG_LIST_SORT_SQL[$sortState['sort']] ?? CATALOG_LIST_SORT_SQL['sku_code'];
    $sortDir = $sortState['dir'] === 'desc' ? 'DESC' : 'ASC';
    $sql .= " ORDER BY {$sortColumn} {$sortDir}";
    if ($sortState['sort'] !== 'sku_code') {
        $sql .= ', s.SKUCode ASC';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function catalog_get_sku(int $skuId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            s.*,
            sup.SupplierName,
            sup.SupplierCode,
            mu.UserName AS ModifiedByName
        FROM dbo.SKUMaster s
        LEFT JOIN dbo.Supplier sup ON sup.SupplierID = s.SupplierID
        LEFT JOIN dbo.[User] mu ON mu.UserID = s.ModifiedbyUser
        WHERE s.SKUID = :id
    SQL);
    $stmt->execute(['id' => $skuId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function catalog_sku_from_input(array $input): array
{
    $allergens = [];
    if (isset($input['allergens']) && is_array($input['allergens'])) {
        $allergens = array_map('trim', $input['allergens']);
    }

    return [
        'sku_code'                     => trim($input['sku_code'] ?? ''),
        'product_name'                 => trim($input['product_name'] ?? ''),
        'supplier_id'                  => trim($input['supplier_id'] ?? ''),
        'brand'                        => trim($input['brand'] ?? ''),
        'manufacturer'                 => trim($input['manufacturer'] ?? ''),
        'primary_therapeutic_category' => trim($input['primary_therapeutic_category'] ?? ''),
        'secondary_category'           => trim($input['secondary_category'] ?? ''),
        'sku_status'                   => trim($input['sku_status'] ?? 'In Development'),
        'serving_count'                => trim($input['serving_count'] ?? ''),
        'bottle_size'                  => trim($input['bottle_size'] ?? ''),
        'gtin14'                       => trim($input['gtin14'] ?? ''),
        'upc'                          => trim($input['upc'] ?? ''),
        'sku_case_barcode'             => trim($input['sku_case_barcode'] ?? ''),
        'product_each_weight_lbs'      => trim($input['product_each_weight_lbs'] ?? ''),
        'product_case_weight_lbs'      => trim($input['product_case_weight_lbs'] ?? ''),
        'supplement_facts_panel'       => trim($input['supplement_facts_panel'] ?? ''),
        'claims'                       => trim($input['claims'] ?? ''),
        'allergens'                    => $allergens,
        'non_gmo_certified'            => (string) ($input['non_gmo_certified'] ?? '0') === '1',
        'cogs'                         => trim($input['cogs'] ?? ''),
        'wholesale_price'              => trim($input['wholesale_price'] ?? ''),
        'msrp'                         => trim($input['msrp'] ?? ''),
        'sfp_link'                     => trim($input['sfp_link'] ?? ''),
        'label_print_ready_link'       => trim($input['label_print_ready_link'] ?? ''),
        'launch_date'                  => trim($input['launch_date'] ?? ''),
        'notes'                        => trim($input['notes'] ?? ''),
        'formulation'                  => trim($input['formulation'] ?? ''),
        'product'                      => trim($input['product'] ?? ''),
        'label_selection'              => trim($input['label_selection'] ?? ''),
        'directions'                   => trim($input['directions'] ?? ''),
        'capsule_count'                => trim($input['capsule_count'] ?? ''),
        'certs_on_label'               => trim($input['certs_on_label'] ?? ''),
        'qbo_purchase_desc'            => trim($input['qbo_purchase_desc'] ?? ''),
        'qbo_taxable'                  => (string) ($input['qbo_taxable'] ?? '1') === '1',
        'qbo_income_account_ref_value' => trim($input['qbo_income_account_ref_value'] ?? ''),
        'qbo_income_account_ref_name'  => trim($input['qbo_income_account_ref_name'] ?? ''),
        'qbo_expense_account_ref_value'=> trim($input['qbo_expense_account_ref_value'] ?? ''),
        'qbo_expense_account_ref_name' => trim($input['qbo_expense_account_ref_name'] ?? ''),
        'qbo_asset_account_ref_value'  => trim($input['qbo_asset_account_ref_value'] ?? ''),
        'qbo_asset_account_ref_name'   => trim($input['qbo_asset_account_ref_name'] ?? ''),
    ];
}

function catalog_save_sku(array $input, ?int $skuId = null): array
{
    $data = catalog_sku_from_input($input);
    $actorId = auth_user()['UserID'] ?? null;

    if ($data['sku_code'] === '' || $data['product_name'] === '') {
        return ['ok' => false, 'error' => 'SKU code and product name are required.'];
    }

    if (!in_array($data['brand'], CATALOG_BRANDS, true)) {
        return ['ok' => false, 'error' => 'Select a valid brand.'];
    }

    if (!in_array($data['manufacturer'], CATALOG_MANUFACTURERS, true)) {
        return ['ok' => false, 'error' => 'Select a valid manufacturer.'];
    }

    if (!in_array($data['primary_therapeutic_category'], CATALOG_THERAPEUTIC_CATEGORIES, true)) {
        return ['ok' => false, 'error' => 'Select a valid primary therapeutic category.'];
    }

    if ($data['secondary_category'] !== '' && !in_array($data['secondary_category'], CATALOG_THERAPEUTIC_CATEGORIES, true)) {
        return ['ok' => false, 'error' => 'Select a valid secondary category.'];
    }

    if (!in_array($data['sku_status'], CATALOG_SKU_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Select a valid status.'];
    }

    $invalidAllergens = array_diff($data['allergens'], CATALOG_ALLERGENS);
    if ($invalidAllergens !== []) {
        return ['ok' => false, 'error' => 'Select valid allergen options.'];
    }

    $pdo = db();

    $dup = $pdo->prepare('SELECT SKUID FROM dbo.SKUMaster WHERE SKUCode = :code AND SKUID <> :id');
    $dup->execute(['code' => $data['sku_code'], 'id' => $skuId ?? 0]);
    if ($dup->fetch() !== false) {
        return ['ok' => false, 'error' => 'That SKU code is already in use.'];
    }

    $servingCount = $data['serving_count'] !== '' ? (int) $data['serving_count'] : null;
    if ($servingCount !== null && $servingCount <= 0) {
        return ['ok' => false, 'error' => 'Serving count must be greater than zero.'];
    }

    $capsuleCount = $data['capsule_count'] !== '' ? (int) $data['capsule_count'] : null;
    if ($capsuleCount !== null && $capsuleCount <= 0) {
        return ['ok' => false, 'error' => 'Capsule count must be greater than zero.'];
    }

    if ($data['label_selection'] !== '' && !in_array($data['label_selection'], CATALOG_LABEL_SELECTIONS, true)) {
        return ['ok' => false, 'error' => 'Select a valid label selection.'];
    }

    $cogs = $data['cogs'] !== '' ? (float) $data['cogs'] : null;
    $wholesale = $data['wholesale_price'] !== '' ? (float) $data['wholesale_price'] : null;
    $msrp = $data['msrp'] !== '' ? (float) $data['msrp'] : null;

    $eachWeight = $data['product_each_weight_lbs'] !== '' ? (float) $data['product_each_weight_lbs'] : null;
    if ($eachWeight !== null && $eachWeight < 0) {
        return ['ok' => false, 'error' => 'Product each weight must be zero or greater.'];
    }

    $caseWeight = $data['product_case_weight_lbs'] !== '' ? (float) $data['product_case_weight_lbs'] : null;
    if ($caseWeight !== null && $caseWeight < 0) {
        return ['ok' => false, 'error' => 'Product case weight must be zero or greater.'];
    }

    $supplierId = $data['supplier_id'] !== '' ? (int) $data['supplier_id'] : null;
    if ($supplierId !== null) {
        $supplierCheck = $pdo->prepare('SELECT SupplierID FROM dbo.Supplier WHERE SupplierID = :id');
        $supplierCheck->execute(['id' => $supplierId]);
        if ($supplierCheck->fetch() === false) {
            return ['ok' => false, 'error' => 'Select a valid supplier.'];
        }
    }

    catalog_resolve_qbo_account_ref($data, 'qbo_income');
    catalog_resolve_qbo_account_ref($data, 'qbo_expense');
    catalog_resolve_qbo_account_ref($data, 'qbo_asset');

    $qboFieldError = catalog_validate_qbo_fields($data);
    if ($qboFieldError !== null) {
        return ['ok' => false, 'error' => $qboFieldError];
    }

    $params = [
        'code'             => $data['sku_code'],
        'name'             => $data['product_name'],
        'supplier'         => $supplierId,
        'brand'            => $data['brand'],
        'manufacturer'     => $data['manufacturer'],
        'primary_category' => $data['primary_therapeutic_category'],
        'secondary'        => $data['secondary_category'] !== '' ? $data['secondary_category'] : null,
        'status'           => $data['sku_status'],
        'serving'          => $servingCount,
        'bottle'           => $data['bottle_size'] !== '' ? $data['bottle_size'] : null,
        'gtin14'           => $data['gtin14'] !== '' ? $data['gtin14'] : null,
        'upc'              => $data['upc'] !== '' ? $data['upc'] : null,
        'sku_case_barcode' => $data['sku_case_barcode'] !== '' ? $data['sku_case_barcode'] : null,
        'each_weight'      => $eachWeight,
        'case_weight'      => $caseWeight,
        'sfp_panel'        => $data['supplement_facts_panel'] !== '' ? $data['supplement_facts_panel'] : null,
        'claims'           => $data['claims'] !== '' ? $data['claims'] : null,
        'allergens'        => catalog_allergens_to_storage($data['allergens']) ?: null,
        'non_gmo'          => $data['non_gmo_certified'] ? 1 : 0,
        'cogs'             => $cogs,
        'wholesale'        => $wholesale,
        'msrp'             => $msrp,
        'sfp_link'         => $data['sfp_link'] !== '' ? $data['sfp_link'] : null,
        'label_link'       => $data['label_print_ready_link'] !== '' ? $data['label_print_ready_link'] : null,
        'launch'           => $data['launch_date'] !== '' ? $data['launch_date'] : null,
        'notes'            => $data['notes'] !== '' ? $data['notes'] : null,
        'formulation'      => $data['formulation'] !== '' ? $data['formulation'] : null,
        'product'          => $data['product'] !== '' ? $data['product'] : null,
        'label_selection'  => $data['label_selection'] !== '' ? $data['label_selection'] : null,
        'directions'       => $data['directions'] !== '' ? $data['directions'] : null,
        'capsule_count'    => $capsuleCount,
        'certs_on_label'   => $data['certs_on_label'] !== '' ? $data['certs_on_label'] : null,
        'qbo_purchase_desc'=> $data['qbo_purchase_desc'] !== '' ? $data['qbo_purchase_desc'] : null,
        'qbo_taxable'      => $data['qbo_taxable'] ? 1 : 0,
        'qbo_income_value' => $data['qbo_income_account_ref_value'] !== '' ? $data['qbo_income_account_ref_value'] : null,
        'qbo_income_name'  => $data['qbo_income_account_ref_name'] !== '' ? $data['qbo_income_account_ref_name'] : null,
        'qbo_expense_value'=> $data['qbo_expense_account_ref_value'] !== '' ? $data['qbo_expense_account_ref_value'] : null,
        'qbo_expense_name' => $data['qbo_expense_account_ref_name'] !== '' ? $data['qbo_expense_account_ref_name'] : null,
        'qbo_asset_value'  => $data['qbo_asset_account_ref_value'] !== '' ? $data['qbo_asset_account_ref_value'] : null,
        'qbo_asset_name'   => $data['qbo_asset_account_ref_name'] !== '' ? $data['qbo_asset_account_ref_name'] : null,
        'actor'            => $actorId,
    ];

    try {
        if ($skuId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.SKUMaster (
                    SKUCode, ProductName, SupplierID, Brand, Manufacturer,
                    PrimaryTherapeuticCategory, SecondaryCategory, SKUStatus,
                    ServingCount, BottleSize, GTIN14, UPC, SKUCaseBarcode,
                    ProductEachWeightLbs, ProductCaseWeightLbs,
                    SupplementFactsPanel, Claims, AllergenStatement, NonGMOCertified,
                    COGS, WholesalePrice, MSRP, SFPLink, LabelPrintReadyLink,
                    LaunchDate, Notes, Formulation, Product, LabelSelection,
                    Directions, CapsuleCount, CertsOnLabel,
                    QBO_PurchaseDesc, QBO_Taxable,
                    QBO_IncomeAccountRefValue, QBO_IncomeAccountRefName,
                    QBO_ExpenseAccountRefValue, QBO_ExpenseAccountRefName,
                    QBO_AssetAccountRefValue, QBO_AssetAccountRefName,
                    CreatedByUser, ModifiedbyUser
                )
                OUTPUT INSERTED.SKUID AS inserted_id
                VALUES (
                    :code, :name, :supplier, :brand, :manufacturer,
                    :primary_category, :secondary, :status,
                    :serving, :bottle, :gtin14, :upc, :sku_case_barcode,
                    :each_weight, :case_weight,
                    :sfp_panel, :claims, :allergens, :non_gmo,
                    :cogs, :wholesale, :msrp, :sfp_link, :label_link,
                    :launch, :notes, :formulation, :product, :label_selection,
                    :directions, :capsule_count, :certs_on_label,
                    :qbo_purchase_desc, :qbo_taxable,
                    :qbo_income_value, :qbo_income_name,
                    :qbo_expense_value, :qbo_expense_name,
                    :qbo_asset_value, :qbo_asset_name,
                    :actor, :actor
                )
            SQL);
            $stmt->execute($params);
            $skuId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            if (catalog_get_sku($skuId) === null) {
                return ['ok' => false, 'error' => 'SKU not found.'];
            }

            $params['id'] = $skuId;
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.SKUMaster
                SET SKUCode = :code,
                    ProductName = :name,
                    SupplierID = :supplier,
                    Brand = :brand,
                    Manufacturer = :manufacturer,
                    PrimaryTherapeuticCategory = :primary_category,
                    SecondaryCategory = :secondary,
                    SKUStatus = :status,
                    ServingCount = :serving,
                    BottleSize = :bottle,
                    GTIN14 = :gtin14,
                    UPC = :upc,
                    SKUCaseBarcode = :sku_case_barcode,
                    ProductEachWeightLbs = :each_weight,
                    ProductCaseWeightLbs = :case_weight,
                    SupplementFactsPanel = :sfp_panel,
                    Claims = :claims,
                    AllergenStatement = :allergens,
                    NonGMOCertified = :non_gmo,
                    COGS = :cogs,
                    WholesalePrice = :wholesale,
                    MSRP = :msrp,
                    SFPLink = :sfp_link,
                    LabelPrintReadyLink = :label_link,
                    LaunchDate = :launch,
                    Notes = :notes,
                    Formulation = :formulation,
                    Product = :product,
                    LabelSelection = :label_selection,
                    Directions = :directions,
                    CapsuleCount = :capsule_count,
                    CertsOnLabel = :certs_on_label,
                    QBO_PurchaseDesc = :qbo_purchase_desc,
                    QBO_Taxable = :qbo_taxable,
                    QBO_IncomeAccountRefValue = :qbo_income_value,
                    QBO_IncomeAccountRefName = :qbo_income_name,
                    QBO_ExpenseAccountRefValue = :qbo_expense_value,
                    QBO_ExpenseAccountRefName = :qbo_expense_name,
                    QBO_AssetAccountRefValue = :qbo_asset_value,
                    QBO_AssetAccountRefName = :qbo_asset_name,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :actor
                WHERE SKUID = :id
            SQL);
            $stmt->execute($params);
        }

        return ['ok' => true, 'error' => null, 'id' => $skuId];
    } catch (Throwable $e) {
        error_log('catalog_save_sku failed: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to save SKU. Please check your entries and try again.'];
    }
}

function catalog_delete_sku(int $skuId): array
{
    if (catalog_get_sku($skuId) === null) {
        return ['ok' => false, 'error' => 'SKU not found.'];
    }

    $pdo = db();
    $pdo->prepare('DELETE FROM dbo.SKUMaster WHERE SKUID = :id')->execute(['id' => $skuId]);

    return ['ok' => true, 'error' => null];
}

function catalog_format_allergens(?string $value): string
{
    $items = catalog_allergens_from_storage($value);

    return $items === [] ? '—' : implode(', ', $items);
}

function catalog_link_display_label(string $url, string $fallback = 'document'): string
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return $fallback;
    }

    $basename = urldecode(basename($path));
    $basename = preg_replace('/\.[^.]+$/', '', $basename) ?? $basename;
    $basename = preg_replace('/\s+copy$/i', '', $basename) ?? $basename;
    $basename = preg_replace('/^(CSPCanister_|NA_)/', '', $basename) ?? $basename;
    $basename = preg_replace('/_FINAL(ai_OL)?$/', '', $basename) ?? $basename;
    $basename = str_replace('_', ' ', trim($basename));

    return $basename !== '' ? $basename : $fallback;
}

function catalog_qbo_truncate(?string $value, int $max): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '';
    }

    if (mb_strlen($value) <= $max) {
        return $value;
    }

    return mb_substr($value, 0, $max);
}

function catalog_build_qbo_item_base_name(array $sku): string
{
    $brand = trim((string) ($sku['Brand'] ?? ''));
    $product = trim((string) ($sku['ProductName'] ?? ''));
    if ($brand === '') {
        return $product;
    }
    if ($product === '') {
        return $brand;
    }

    return $brand . ' ' . $product;
}

function catalog_qbo_item_name_needs_sku_suffix(array $sku): bool
{
    $skuId = (int) ($sku['SKUID'] ?? 0);
    $brand = trim((string) ($sku['Brand'] ?? ''));
    $productName = trim((string) ($sku['ProductName'] ?? ''));
    if ($brand === '' || $productName === '') {
        return false;
    }

    $pdo = db();
    $dup = $pdo->prepare(<<<SQL
        SELECT TOP 1 SKUID
        FROM dbo.SKUMaster
        WHERE Brand = :brand
          AND ProductName = :product_name
          AND SKUID <> :id
    SQL);
    $dup->execute([
        'brand'        => $brand,
        'product_name' => $productName,
        'id'           => $skuId,
    ]);
    if ($dup->fetch() !== false) {
        return true;
    }

    if (!is_file(__DIR__ . '/quickbooks.php')) {
        return false;
    }

    require_once __DIR__ . '/quickbooks.php';
    if (!qbo_is_connected()) {
        return false;
    }

    $baseName = catalog_build_qbo_item_base_name($sku);
    $fetch = qbo_find_item_by_name($baseName);
    if (!$fetch['ok'] || !is_array($fetch['item'] ?? null)) {
        return false;
    }

    $item = $fetch['item'];
    $linkedId = trim((string) ($sku['QBO_ItemID'] ?? ''));
    $existingId = trim((string) ($item['Id'] ?? ''));
    if ($linkedId !== '' && $existingId !== '' && $linkedId === $existingId) {
        return false;
    }

    $existingSku = trim((string) ($item['Sku'] ?? ''));
    $localSku = trim((string) ($sku['SKUCode'] ?? ''));

    return $existingSku === '' || strcasecmp($existingSku, $localSku) !== 0;
}

function catalog_build_qbo_item_name(array $sku): string
{
    $name = catalog_build_qbo_item_base_name($sku);
    if ($name === '') {
        return '';
    }

    if (catalog_qbo_item_name_needs_sku_suffix($sku)) {
        $suffix = ' (' . trim((string) ($sku['SKUCode'] ?? '')) . ')';
        $maxBase = CATALOG_QBO_NAME_MAX_LENGTH - mb_strlen($suffix);
        if ($maxBase < 1) {
            $maxBase = 1;
        }
        $name = mb_substr($name, 0, $maxBase) . $suffix;
    }

    return catalog_qbo_truncate($name, CATALOG_QBO_NAME_MAX_LENGTH);
}

function catalog_build_qbo_item_description(array $sku): string
{
    $product = trim((string) ($sku['Product'] ?? ''));
    if ($product !== '') {
        return catalog_qbo_truncate($product, CATALOG_QBO_DESCRIPTION_MAX_LENGTH);
    }

    return catalog_qbo_truncate((string) ($sku['ProductName'] ?? ''), CATALOG_QBO_DESCRIPTION_MAX_LENGTH);
}

function catalog_build_qbo_purchase_desc(array $sku): string
{
    $purchaseDesc = trim((string) ($sku['QBO_PurchaseDesc'] ?? ''));
    if ($purchaseDesc !== '') {
        return catalog_qbo_truncate($purchaseDesc, CATALOG_QBO_DESCRIPTION_MAX_LENGTH);
    }

    return catalog_build_qbo_item_description($sku);
}

function catalog_sku_is_qbo_active(array $sku): bool
{
    return (string) ($sku['SKUStatus'] ?? '') === 'Active';
}

function catalog_validate_qbo_fields(array $data): ?string
{
    $name = catalog_build_qbo_item_name([
        'SKUID'       => 0,
        'Brand'       => $data['brand'] ?? '',
        'ProductName' => $data['product_name'] ?? '',
        'SKUCode'     => $data['sku_code'] ?? '',
    ]);
    if ($name !== '' && mb_strlen($name) > CATALOG_QBO_NAME_MAX_LENGTH) {
        return 'QuickBooks item name must be ' . CATALOG_QBO_NAME_MAX_LENGTH . ' characters or fewer.';
    }

    $description = trim((string) ($data['product'] ?? ''));
    if ($description === '') {
        $description = trim((string) ($data['product_name'] ?? ''));
    }
    if (mb_strlen($description) > CATALOG_QBO_DESCRIPTION_MAX_LENGTH) {
        return 'QuickBooks description must be ' . CATALOG_QBO_DESCRIPTION_MAX_LENGTH . ' characters or fewer.';
    }

    $purchaseDesc = trim((string) ($data['qbo_purchase_desc'] ?? ''));
    if ($purchaseDesc !== '' && mb_strlen($purchaseDesc) > CATALOG_QBO_DESCRIPTION_MAX_LENGTH) {
        return 'QuickBooks purchase description must be ' . CATALOG_QBO_DESCRIPTION_MAX_LENGTH . ' characters or fewer.';
    }

    return null;
}

function catalog_qbo_sync_blockers(array $sku): array
{
    require_once __DIR__ . '/quickbooks.php';

    $blockers = [];

    if (trim((string) ($sku['SKUCode'] ?? '')) === '') {
        $blockers[] = 'SKU code is required.';
    }
    if (trim((string) ($sku['ProductName'] ?? '')) === '') {
        $blockers[] = 'Product name is required.';
    }
    if (trim((string) ($sku['Brand'] ?? '')) === '') {
        $blockers[] = 'Brand is required.';
    }
    if ($sku['MSRP'] === null || $sku['MSRP'] === '') {
        $blockers[] = 'MSRP is required (maps to QBO Unit Price).';
    }
    if ($sku['COGS'] === null || $sku['COGS'] === '') {
        $blockers[] = 'COGS is required (maps to QBO Purchase Cost).';
    }
    if (trim((string) ($sku['QBO_IncomeAccountRefValue'] ?? '')) === '') {
        $blockers[] = 'Income account is required.';
    }
    if (trim((string) ($sku['QBO_ExpenseAccountRefValue'] ?? '')) === '') {
        $blockers[] = 'COGS account is required.';
    }
    if (qbo_sku_uses_inventory_tracking() && trim((string) ($sku['QBO_AssetAccountRefValue'] ?? '')) === '') {
        $blockers[] = 'Inventory asset account is required.';
    }

    $name = catalog_build_qbo_item_name($sku);
    if ($name === '' && $blockers === []) {
        $blockers[] = 'Unable to build QuickBooks item name.';
    }

    return $blockers;
}

function catalog_validate_qbo_item_ready(array $sku): ?string
{
    $blockers = catalog_qbo_sync_blockers($sku);

    return $blockers === [] ? null : $blockers[0];
}

function catalog_build_qbo_account_ref(array $sku, string $prefix): array
{
    $value = trim((string) ($sku[$prefix . 'AccountRefValue'] ?? ''));
    $ref = ['value' => $value];
    $name = trim((string) ($sku[$prefix . 'AccountRefName'] ?? ''));
    if ($name !== '') {
        $ref['name'] = $name;
    }

    return $ref;
}

function catalog_build_qbo_item_payload(array $sku, bool $isCreate, ?string $itemType = null): array
{
    require_once __DIR__ . '/quickbooks.php';

    $itemType = $itemType ?? qbo_sku_item_type();
    $useInventoryTracking = strcasecmp($itemType, 'NonInventory') !== 0;

    $name = catalog_build_qbo_item_name($sku);
    $description = catalog_build_qbo_item_description($sku);
    $purchaseDesc = catalog_build_qbo_purchase_desc($sku);

    $payload = [
        'Name'          => $name,
        'Sku'           => trim((string) ($sku['SKUCode'] ?? '')),
        'Type'          => $useInventoryTracking ? 'Inventory' : 'NonInventory',
        'Description'   => $description !== '' ? $description : null,
        'PurchaseDesc'  => $purchaseDesc !== '' ? $purchaseDesc : null,
        'UnitPrice'     => round((float) $sku['MSRP'], 2),
        'PurchaseCost'  => round((float) $sku['COGS'], 2),
        'Active'        => catalog_sku_is_qbo_active($sku),
        'Taxable'       => !array_key_exists('QBO_Taxable', $sku) || !empty($sku['QBO_Taxable']),
        'IncomeAccountRef'  => catalog_build_qbo_account_ref($sku, 'QBO_Income'),
        'ExpenseAccountRef' => catalog_build_qbo_account_ref($sku, 'QBO_Expense'),
    ];

    if ($useInventoryTracking) {
        $payload['AssetAccountRef'] = catalog_build_qbo_account_ref($sku, 'QBO_Asset');

        if ($isCreate) {
            $invStartDate = trim((string) ($sku['LaunchDate'] ?? ''));
            if ($invStartDate === '') {
                $invStartDate = (new DateTimeImmutable('today'))->format('Y-m-d');
            }

            $payload['TrackQtyOnHand'] = true;
            $payload['QtyOnHand'] = 0;
            $payload['InvStartDate'] = $invStartDate;
        }
    }

    if (!empty($sku['QBO_ItemID'])) {
        $payload['Id'] = (string) $sku['QBO_ItemID'];
    }
    if (!empty($sku['QBO_SyncToken'])) {
        $payload['SyncToken'] = (string) $sku['QBO_SyncToken'];
    }

    foreach (['Description', 'PurchaseDesc'] as $optionalField) {
        if (($payload[$optionalField] ?? null) === null) {
            unset($payload[$optionalField]);
        }
    }

    return $payload;
}

function catalog_mark_qbo_sync(int $skuId, string $status, ?string $error = null): void
{
    if (!in_array($status, CATALOG_QBO_SYNC_STATUSES, true)) {
        $status = 'Error';
    }

    $pdo = db();
    $errorText = $error !== null && $error !== '' ? $error : null;

    if ($status === 'Synced') {
        if ($errorText === null) {
            $pdo->prepare(<<<SQL
                UPDATE dbo.SKUMaster
                SET QBO_SyncStatus = :status,
                    QBO_SyncError = NULL,
                    QBO_SyncedAt = SYSUTCDATETIME(),
                    ModifiedDate = SYSUTCDATETIME()
                WHERE SKUID = :id
            SQL)->execute([
                'status' => $status,
                'id'     => $skuId,
            ]);

            return;
        }

        $pdo->prepare(<<<SQL
            UPDATE dbo.SKUMaster
            SET QBO_SyncStatus = :status,
                QBO_SyncError = :error,
                QBO_SyncedAt = SYSUTCDATETIME(),
                ModifiedDate = SYSUTCDATETIME()
            WHERE SKUID = :id
        SQL)->execute([
            'status' => $status,
            'error'  => $errorText,
            'id'     => $skuId,
        ]);

        return;
    }

    if ($errorText === null) {
        $pdo->prepare(<<<SQL
            UPDATE dbo.SKUMaster
            SET QBO_SyncStatus = :status,
                QBO_SyncError = NULL,
                ModifiedDate = SYSUTCDATETIME()
            WHERE SKUID = :id
        SQL)->execute([
            'status' => $status,
            'id'     => $skuId,
        ]);

        return;
    }

    $pdo->prepare(<<<SQL
        UPDATE dbo.SKUMaster
        SET QBO_SyncStatus = :status,
            QBO_SyncError = :error,
            ModifiedDate = SYSUTCDATETIME()
        WHERE SKUID = :id
    SQL)->execute([
        'status' => $status,
        'error'  => $errorText,
        'id'     => $skuId,
    ]);
}

function catalog_apply_qbo_item_response(int $skuId, array $item): void
{
    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.SKUMaster
        SET QBO_ItemID = :qbo_id,
            QBO_SyncToken = :sync_token,
            QBO_DisplayName = :display_name,
            QBO_SyncStatus = N'Synced',
            QBO_SyncError = NULL,
            QBO_SyncedAt = SYSUTCDATETIME(),
            ModifiedDate = SYSUTCDATETIME()
        WHERE SKUID = :id
    SQL)->execute([
        'qbo_id'       => (string) ($item['Id'] ?? ''),
        'sync_token'   => (string) ($item['SyncToken'] ?? ''),
        'display_name' => (string) ($item['Name'] ?? ''),
        'id'           => $skuId,
    ]);
}

function catalog_store_qbo_item_identity(int $skuId, array $item): void
{
    $params = [
        'qbo_id'     => (string) ($item['Id'] ?? ''),
        'sync_token' => (string) ($item['SyncToken'] ?? ''),
        'id'         => $skuId,
    ];
    $displayName = trim((string) ($item['Name'] ?? ''));

    if ($displayName !== '') {
        db()->prepare(<<<SQL
            UPDATE dbo.SKUMaster
            SET QBO_ItemID = :qbo_id,
                QBO_SyncToken = :sync_token,
                QBO_DisplayName = :display_name,
                ModifiedDate = SYSUTCDATETIME()
            WHERE SKUID = :id
        SQL)->execute($params + ['display_name' => $displayName]);

        return;
    }

    db()->prepare(<<<SQL
        UPDATE dbo.SKUMaster
        SET QBO_ItemID = :qbo_id,
            QBO_SyncToken = :sync_token,
            ModifiedDate = SYSUTCDATETIME()
        WHERE SKUID = :id
    SQL)->execute($params);
}

function catalog_qbo_account_options(string $purpose): array
{
    if (!is_file(__DIR__ . '/quickbooks.php')) {
        return [];
    }

    require_once __DIR__ . '/quickbooks.php';
    $result = qbo_coa_list_accounts(true);
    if (!$result['ok']) {
        return [];
    }

    $options = [];
    foreach ($result['rows'] as $row) {
        $accountType = (string) ($row['AccountType'] ?? '');
        $subType = (string) ($row['AccountSubType'] ?? '');
        $matches = match ($purpose) {
            'income'  => $accountType === 'Income',
            'expense' => $accountType === 'Cost of Goods Sold',
            'asset'   => $accountType === 'Other Current Asset' && $subType === 'Inventory',
            default   => false,
        };
        if (!$matches) {
            continue;
        }

        $id = trim((string) ($row['Id'] ?? ''));
        $name = trim((string) ($row['Name'] ?? ''));
        if ($id === '' || $name === '') {
            continue;
        }

        $label = $name;
        if (!empty($row['FullyQualifiedName']) && $row['FullyQualifiedName'] !== $name) {
            $label = (string) $row['FullyQualifiedName'];
        }

        $options[] = [
            'id'    => $id,
            'name'  => $name,
            'label' => $label,
        ];
    }

    usort($options, fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

    return $options;
}

function qbo_sync_sku(int $skuId): array
{
    if (!is_file(__DIR__ . '/quickbooks.php')) {
        return ['ok' => false, 'error' => 'QuickBooks integration is not available.'];
    }

    require_once __DIR__ . '/quickbooks.php';

    return qbo_sync_sku_to_quickbooks($skuId);
}

const CATALOG_QBO_BULK_SYNC_SESSION_KEY = 'catalog_qbo_bulk_sync_result';

function catalog_sync_all_skus_to_qbo(): array
{
    if (!qbo_is_connected()) {
        return ['ok' => false, 'error' => 'QuickBooks is not connected.', 'total' => 0, 'synced' => 0, 'reconciled' => 0, 'failed' => 0, 'failures' => []];
    }

    $pdo = db();
    $stmt = $pdo->query('SELECT SKUID, SKUCode FROM dbo.SKUMaster ORDER BY SKUCode');
    $rows = $stmt->fetchAll();

    $summary = [
        'ok'         => true,
        'error'      => null,
        'total'      => count($rows),
        'synced'     => 0,
        'reconciled' => 0,
        'failed'     => 0,
        'failures'   => [],
        'warnings'   => 0,
    ];

    foreach ($rows as $row) {
        $skuId = (int) ($row['SKUID'] ?? 0);
        $skuCode = (string) ($row['SKUCode'] ?? '');
        if ($skuId <= 0) {
            continue;
        }

        $result = qbo_sync_sku($skuId);
        if ($result['ok']) {
            if (!empty($result['reconciled'])) {
                $summary['reconciled']++;
            } else {
                $summary['synced']++;
            }
            if (!empty($result['warning'])) {
                $summary['warnings']++;
            }
            continue;
        }

        $summary['failed']++;
        $summary['failures'][] = [
            'sku_id'   => $skuId,
            'sku_code' => $skuCode,
            'error'    => (string) ($result['error'] ?? 'QuickBooks sync failed.'),
        ];
    }

    if ($summary['total'] === 0) {
        $summary['ok'] = false;
        $summary['error'] = 'No SKUs found to sync.';
    } elseif ($summary['synced'] === 0 && $summary['reconciled'] === 0 && $summary['failed'] > 0) {
        $summary['ok'] = false;
        $summary['error'] = 'All SKU syncs failed.';
    }

    return $summary;
}

function catalog_store_bulk_sync_result(array $result): void
{
    $_SESSION[CATALOG_QBO_BULK_SYNC_SESSION_KEY] = $result;
}

function catalog_take_bulk_sync_result(): ?array
{
    $result = $_SESSION[CATALOG_QBO_BULK_SYNC_SESSION_KEY] ?? null;
    unset($_SESSION[CATALOG_QBO_BULK_SYNC_SESSION_KEY]);

    return is_array($result) ? $result : null;
}
