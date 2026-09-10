<?php
/**
 * Create Production draft applications from existing ACCS customers and email continue links.
 *
 * Usage:
 *   php scripts/invite-existing-accs-customers.php
 *   php scripts/invite-existing-accs-customers.php --ids=400,403,404,405,406
 */

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/database.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

$ids = [400, 403, 404, 405, 406];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--ids=')) {
        $ids = array_values(array_filter(array_map('intval', explode(',', substr($arg, 6)))));
    }
}

putenv('SITE_URL=https://provider-signup.nutraaxislabs.com');
$_ENV['SITE_URL'] = 'https://provider-signup.nutraaxislabs.com';

foreach ($ids as $id) {
    $result = provider_signup_invite_existing_accs_customer($id, 'production');
    echo $id
        . ' ok=' . (!empty($result['ok']) ? 'yes' : 'no')
        . ' app=' . ($result['application_id'] ?? '—')
        . ' resumed=' . (!empty($result['resumed']) ? 'yes' : 'no')
        . ' url=' . ($result['continue_url'] ?? '—')
        . ($result['error'] ? ' error=' . $result['error'] : '')
        . "\n";
}
