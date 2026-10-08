<?php

/**
 * ACCS Order and Account Support — process registry for IT & Ecommerce.
 *
 * @return list<array{slug: string, title: string, desc: string, href: string, icon: string}>
 */
function accs_order_account_support_processes(): array
{
    return [
        [
            'slug'  => 'cases',
            'title' => 'Support cases',
            'desc'  => 'Open cases, comment history, Stage resource ledger, and clone/purge status.',
            'href'  => '/accs-order-account-support/cases/',
            'icon'  => 'clipboard',
        ],
        [
            'slug'  => 'clone',
            'title' => 'Clone account to Stage',
            'desc'  => 'Mirror a Production clinic onto Stage with Prod shared-catalog membership and prices.',
            'href'  => '/accs-order-account-support/clone/',
            'icon'  => 'support',
        ],
        [
            'slug'  => 'cart',
            'title' => 'Recreate order as Stage cart',
            'desc'  => 'Build an open Stage cart from a Production order so you can finish checkout on Stage.',
            'href'  => '/accs-order-account-support/cart/',
            'icon'  => 'catalog',
        ],
        [
            'slug'  => 'purge',
            'title' => 'Purge Stage support account',
            'desc'  => 'Tear down SUPPORT- Stage company, catalog, prices, and admin using the case ledger.',
            'href'  => '/accs-order-account-support/purge/',
            'icon'  => 'document',
        ],
    ];
}
