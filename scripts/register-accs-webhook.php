<?php

/**
 * ACCS Webhook Registration — Adobe I/O Events
 *
 * This ACCS environment uses Adobe I/O Events (not the Commerce REST webhook API),
 * which requires registration through the Adobe Developer Console.
 *
 * This script:
 *   1. Prints the exact endpoint URL and settings to use in the Console
 *   2. Verifies the Function App endpoint is reachable (GET challenge test)
 *   3. Confirms the ACCS_WEBHOOK_SECRET env var is set in the Function App
 *
 * ── MANUAL REGISTRATION STEPS ──────────────────────────────────────────────
 *
 *   1. Go to: https://developer.adobe.com/console/projects
 *   2. Open the NutraAxis project (or create one for this org)
 *   3. Click "Add service" → "I/O Events"
 *   4. Select "Adobe Commerce" → choose your ACCS org/environment
 *   5. Subscribe to the event:
 *        com.adobe.commerce.observer.sales_order_place_after
 *   6. Choose "Webhook" as the delivery method
 *   7. Enter the webhook URL (printed below)
 *   8. Adobe will do a GET challenge — the Function App handles it automatically
 *   9. Copy the "Webhook secret" Adobe generates → set ACCS_WEBHOOK_SECRET in
 *      Azure App Settings (same key must be set in Function App)
 *
 * Run this script after completing the above to verify connectivity.
 *
 * Usage:
 *   php scripts/register-accs-webhook.php
 */

require_once __DIR__ . '/../includes/env.php';

$functionAppUrl  = rtrim((string) env('AZURE_FUNCTION_APP_URL', 'https://nutra-forecast-tool-czaxf0eydta6aeeg.eastus2-01.azurewebsites.net'), '/');
$webhookSecret   = (string) env('ACCS_WEBHOOK_SECRET', '');
$endpointUrl     = $functionAppUrl . '/api/accs-order-webhook';

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║        ACCS Order Webhook — Registration Info                    ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo "Webhook endpoint URL (use this in Adobe Developer Console):\n";
echo "  $endpointUrl\n\n";

echo "Event to subscribe:\n";
echo "  com.adobe.commerce.observer.sales_order_place_after\n\n";

echo "Adobe Developer Console:\n";
echo "  https://developer.adobe.com/console/projects\n\n";

echo str_repeat('-', 70) . "\n";
echo "Connectivity check...\n\n";

// --- Verify the Function App endpoint responds to a challenge ---
if (!function_exists('curl_init')) {
    echo "cURL not available — skipping connectivity check.\n";
} else {
    $challengeUrl = $endpointUrl . '?challenge=test-ping-' . time();
    $ch = curl_init($challengeUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPGET        => true,
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($status === 200) {
        $data = json_decode($body, true);
        if (isset($data['challenge'])) {
            echo "  [OK]  Function App is reachable and returned challenge correctly.\n";
        } else {
            echo "  [OK]  Function App is reachable (HTTP $status).\n";
        }
    } elseif ($status === 0) {
        echo "  [WARN] Could not reach $endpointUrl — check the URL or your network.\n";
    } else {
        echo "  [WARN] Unexpected HTTP $status from endpoint.\n";
        echo "         Response: $body\n";
    }
}

echo "\n";
echo str_repeat('-', 70) . "\n";
echo "Azure Function App settings check...\n\n";

// Check ACCS_WEBHOOK_SECRET in local .env (a proxy for whether it's configured)
if ($webhookSecret !== '') {
    echo "  [OK]  ACCS_WEBHOOK_SECRET is set in your local .env.\n";
    echo "        Make sure the same value is set in Azure App Settings.\n";
} else {
    echo "  [WARN] ACCS_WEBHOOK_SECRET is not set in your local .env.\n";
    echo "         Add it after getting the secret from Adobe Developer Console:\n";
    echo "         ACCS_WEBHOOK_SECRET=your-secret-here\n";
    echo "         Also set it in Azure App Settings for the Function App.\n";
}

echo "\n";
echo str_repeat('-', 70) . "\n";
echo "Email alert destination:\n";
echo "  jbutler@nfcllc.com\n\n";
echo "After registration, place a test order in the ACCS stage storefront:\n";
echo "  https://main--nutrasync-eds-staging--capocommerce.aem.live/\n\n";
echo "Or test the function directly:\n";
echo "  curl -X POST $endpointUrl \\\n";
echo "    -H 'Content-Type: application/json' \\\n";
echo "    -d '{\"data\":{\"value\":{\"order\":{\"increment_id\":\"TEST-001\",\"status\":\"complete\",\"grand_total\":\"49.99\",\"customer_email\":\"test@example.com\",\"customer_firstname\":\"Test\",\"customer_lastname\":\"User\",\"items\":[{\"sku\":\"NA-GW-002\",\"name\":\"MagRenew\",\"qty_ordered\":2,\"price\":\"14.00\",\"row_total\":\"28.00\"}]}}}}'\n\n";
