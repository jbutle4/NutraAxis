<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/coa-public-api.php';

coa_public_handle_preflight();

require_once dirname(__DIR__, 2) . '/includes/education-resources.php';

$items = array_map('education_resources_to_api_item', education_resources_list_public());

coa_public_json_response([
    'ok'           => true,
    'generated_at' => gmdate('c'),
    'items'        => $items,
]);
