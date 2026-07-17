<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/coa-public-api.php';

coa_public_handle_preflight();

require_once dirname(__DIR__, 2) . '/includes/coa.php';

$items = array_map('coa_to_api_item', coa_list_published());

coa_public_json_response([
    'ok'           => true,
    'generated_at' => gmdate('c'),
    'items'        => $items,
]);
