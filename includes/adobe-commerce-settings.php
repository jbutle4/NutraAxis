<?php

/**
 * Canonical Adobe Commerce (ACCS) environment variable names.
 *
 * Use these exact names in Azure App Service application settings and in a
 * local .env file when running CLI scripts (values should match Azure).
 *
 * Required for API access:
 *   ADOBE_COMMERCE_CLIENT_ID
 *   ADOBE_COMMERCE_CLIENT_SECRET
 *
 * Environment selection:
 *   ADOBE_COMMERCE_ENVIRONMENT — stage | dev | production (default: stage)
 *
 * Tenant IDs (optional; code defaults apply when unset):
 *   ADOBE_COMMERCE_TENANT_ID — override tenant for any environment
 *   ADOBE_COMMERCE_STAGE
 *   ADOBE_COMMERCE_DEV
 *   ADOBE_COMMERCE_PRODUCTION
 *
 * Advanced (optional):
 *   ADOBE_COMMERCE_API_HOST — override API host (normally derived per environment)
 *   ADOBE_COMMERCE_IMS_TOKEN_URL — override IMS token endpoint
 *   ADOBE_COMMERCE_ORG_ID — Adobe org ID (I/O Events / integrations)
 *   ADOBE_COMMERCE_PAGE_SIZE — order list page size (default 25)
 *   ADOBE_COMMERCE_ORDERS_PAGE_SIZE — bulk order fetch page size (default 100)
 *   ADOBE_COMMERCE_INVENTORY_PAGE_SIZE — inventory fetch page size (default 100)
 *   ADOBE_COMMERCE_WEBHOOK_SECRET — Adobe I/O Events webhook signing secret
 */

const ADOBE_COMMERCE_RUNTIME_ENV_KEYS = [
    'ADOBE_COMMERCE_CLIENT_ID',
    'ADOBE_COMMERCE_CLIENT_SECRET',
    'ADOBE_COMMERCE_ENVIRONMENT',
    'ADOBE_COMMERCE_TENANT_ID',
    'ADOBE_COMMERCE_STAGE',
    'ADOBE_COMMERCE_DEV',
    'ADOBE_COMMERCE_PRODUCTION',
    'ADOBE_COMMERCE_ORG_ID',
    'ADOBE_COMMERCE_API_HOST',
    'ADOBE_COMMERCE_IMS_TOKEN_URL',
    'ADOBE_COMMERCE_PAGE_SIZE',
    'ADOBE_COMMERCE_ORDERS_PAGE_SIZE',
    'ADOBE_COMMERCE_INVENTORY_PAGE_SIZE',
    'ADOBE_COMMERCE_WEBHOOK_SECRET',
];
