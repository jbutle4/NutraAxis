/**
 * Fulfillment routing — maps fulfillment attribute values to email destinations.
 *
 * Each route defines:
 *   code    — the fulfillment attribute value (case-insensitive match)
 *   to      — primary recipient(s)
 *   cc      — CC recipient(s)
 *   label   — human-readable supplier name for email subject/header
 *
 * To add a new fulfillment supplier, add a new entry here (and set the
 * corresponding env vars in Azure App Settings for flexible overrides).
 *
 * Env var overrides (optional):
 *   FULFILLMENT_CART_TO   / FULFILLMENT_CART_CC
 *   FULFILLMENT_CPPC_TO   / FULFILLMENT_CPPC_CC
 *   FULFILLMENT_MTL_TO    / FULFILLMENT_MTL_CC
 *   FULFILLMENT_CC_ALWAYS — always CC this address on every fulfillment email
 */

const CC_ALWAYS = (process.env.FULFILLMENT_CC_ALWAYS || 'jbutler@nfcllc.com')
    .split(',').map(s => s.trim()).filter(Boolean);

const ROUTES = [
    {
        code:  'Cart',
        to:    (process.env.FULFILLMENT_CART_TO || 'jbutle4@icloud.com')
                   .split(',').map(s => s.trim()).filter(Boolean),
        cc:    (process.env.FULFILLMENT_CART_CC || '')
                   .split(',').map(s => s.trim()).filter(Boolean),
        label: 'Cart Fulfillment',
    },
    {
        code:  'CPPC',
        to:    (process.env.FULFILLMENT_CPPC_TO || 'jbutle4@gmail.com')
                   .split(',').map(s => s.trim()).filter(Boolean),
        cc:    (process.env.FULFILLMENT_CPPC_CC || '')
                   .split(',').map(s => s.trim()).filter(Boolean),
        label: 'CPPC Fulfillment',
    },
    {
        code:  'MTL',
        to:    (process.env.FULFILLMENT_MTL_TO || 'jbutle4@gmail.com')
                   .split(',').map(s => s.trim()).filter(Boolean),
        cc:    (process.env.FULFILLMENT_MTL_CC || '')
                   .split(',').map(s => s.trim()).filter(Boolean),
        label: 'MTL Fulfillment',
    },
];

/**
 * Find the route for a given fulfillment code (case-insensitive).
 * Returns null if no route is configured for that code.
 */
function getRoute(fulfillmentCode) {
    if (!fulfillmentCode) return null;
    return ROUTES.find(r => r.code.toLowerCase() === fulfillmentCode.toLowerCase()) ?? null;
}

/**
 * Group order items by fulfillment code.
 *
 * @param {object[]} items - enriched order items (each has a .fulfillment property)
 * @returns {Map<string, object[]>}  key = fulfillment code (or '__unrouted__')
 */
function groupItemsByFulfillment(items) {
    const groups = new Map();

    for (const item of items) {
        const key = item.fulfillment || '__unrouted__';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(item);
    }

    return groups;
}

/**
 * Build the list of dispatches to send for an order.
 * Each dispatch = { route, items, to, cc }
 *
 * Items with no matching route are collected into a fallback
 * dispatch sent to FULFILLMENT_CC_ALWAYS only (so nothing is silently dropped).
 *
 * @param {object[]} enrichedItems
 * @returns {{ route: object|null, items: object[], to: string[], cc: string[] }[]}
 */
function buildDispatches(enrichedItems) {
    const groups    = groupItemsByFulfillment(enrichedItems);
    const dispatches = [];

    for (const [code, items] of groups) {
        if (code === '__unrouted__') {
            // No fulfillment attribute — send a warning dispatch to the CC address
            if (CC_ALWAYS.length > 0) {
                dispatches.push({
                    route: { code: null, label: 'Unrouted Items' },
                    items,
                    to: CC_ALWAYS,
                    cc: [],
                });
            }
            continue;
        }

        const route = getRoute(code);

        if (!route) {
            // Known fulfillment code but no route configured — still notify CC_ALWAYS
            if (CC_ALWAYS.length > 0) {
                dispatches.push({
                    route: { code, label: `Unknown Fulfillment (${code})` },
                    items,
                    to: CC_ALWAYS,
                    cc: [],
                });
            }
            continue;
        }

        dispatches.push({
            route,
            items,
            to: route.to,
            cc: [...route.cc, ...CC_ALWAYS],
        });
    }

    return dispatches;
}

module.exports = { ROUTES, getRoute, groupItemsByFulfillment, buildDispatches, CC_ALWAYS };
