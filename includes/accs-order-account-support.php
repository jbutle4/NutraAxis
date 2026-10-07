<?php

/**
 * ACCS Order and Account Support — process registry for IT & Ecommerce.
 * Add each support process here; the hub landing page renders the cards.
 *
 * @return list<array{slug: string, title: string, desc: string, href: string, icon: string}>
 */
function accs_order_account_support_processes(): array
{
    return [
        // Example shape for upcoming processes:
        // [
        //     'slug'  => 'order-status-lookup',
        //     'title' => 'Order status lookup',
        //     'desc'  => 'Find an ACCS order and confirm fulfillment / payment state.',
        //     'href'  => '/accs-order-account-support/order-status/',
        //     'icon'  => 'clipboard',
        // ],
    ];
}
