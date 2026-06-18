<?php

/**
 * Azure Function App resource names and portal display labels.
 *
 * Azure resource names cannot be renamed in place; UAT runs on Nutra-forecast-tool
 * with Environment=UAT tags in Azure. Production uses Nutra-forecast-tool-prod.
 */

const FUNCTION_APP_UAT_RESOURCE_NAME = 'Nutra-forecast-tool';
const FUNCTION_APP_UAT_DISPLAY_NAME = 'Nutra-forecast-tool (UAT)';
const FUNCTION_APP_UAT_DEFAULT_URL = 'https://nutra-forecast-tool-czaxf0eydta6aeeg.eastus2-01.azurewebsites.net';

const FUNCTION_APP_PROD_RESOURCE_NAME = 'Nutra-forecast-tool-prod';
const FUNCTION_APP_PROD_DISPLAY_NAME = 'Nutra-forecast-tool-prod (Production)';
const FUNCTION_APP_PROD_DEFAULT_URL = 'https://nutra-forecast-tool-prod.azurewebsites.net';

function function_app_uat_display_name(): string
{
    return FUNCTION_APP_UAT_DISPLAY_NAME;
}

function function_app_prod_display_name(): string
{
    return FUNCTION_APP_PROD_DISPLAY_NAME;
}

/** Both apps, for site docs and operator-facing copy. */
function function_app_display_names_summary(): string
{
    return function_app_uat_display_name() . ' and ' . function_app_prod_display_name();
}

function function_app_display_name_for_url(string $url): string
{
    $normalized = rtrim(strtolower($url), '/');
    if (str_contains($normalized, 'nutra-forecast-tool-prod')) {
        return function_app_prod_display_name();
    }
    if (str_contains($normalized, 'nutra-forecast-tool')) {
        return function_app_uat_display_name();
    }

    return 'Azure Function App';
}
