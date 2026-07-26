/**
 * Adobe Commerce (ACCS) REST API client — Node.js port of includes/adobe-commerce.php.
 * Handles OAuth token caching and product lookups.
 */

const https = require('https');

const ENVIRONMENTS = {
    stage:      { tenant: 'UAEyTrirS4qBMAWYZa4uic', apiHost: 'na1-sandbox.api.commerce.adobe.com' },
    dev:        { tenant: 'JZTG7BaEUkyB9oWTNxEzEq', apiHost: 'na1-sandbox.api.commerce.adobe.com' },
    production: { tenant: 'VLuKe3eeTwf1D5oxmLBfcr', apiHost: 'na1.api.commerce.adobe.com' },
};

const IMS_SCOPE = 'openid,AdobeID,commerce.accs,additional_info.roles,org.read,additional_info.projectedProductContext,profile,email';

// In-process token cache
let _cachedToken    = null;
let _cachedExpiresAt = 0;

function getEnvironment() {
    const env = (process.env.ADOBE_COMMERCE_ENVIRONMENT || 'stage').toLowerCase().trim();
    return ENVIRONMENTS[env] ? env : 'stage';
}

function getTenantId() {
    const override = (process.env.ADOBE_COMMERCE_TENANT_ID || '').trim();
    if (override) return override;

    const env = getEnvironment();
    const byEnv = (process.env[`ADOBE_COMMERCE_${env.toUpperCase()}`] || '').trim();
    if (byEnv) return byEnv;

    return ENVIRONMENTS[env].tenant;
}

function getApiHost() {
    const override = (process.env.ADOBE_COMMERCE_API_HOST || '').trim();
    return override || ENVIRONMENTS[getEnvironment()].apiHost;
}

function getBaseUrl() {
    return `https://${getApiHost()}/${getTenantId()}/V1`;
}

function getClientId()     { return process.env.ADOBE_COMMERCE_CLIENT_ID     || ''; }
function getClientSecret() { return process.env.ADOBE_COMMERCE_CLIENT_SECRET || ''; }

/**
 * Fetch a URL and return { statusCode, body (parsed JSON) }.
 */
function httpRequest(url, options = {}, postData = null) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const reqOptions = {
            hostname: urlObj.hostname,
            path:     urlObj.pathname + urlObj.search,
            method:   options.method || 'GET',
            headers:  options.headers || {},
        };

        const req = https.request(reqOptions, (res) => {
            let data = '';
            res.on('data', chunk => { data += chunk; });
            res.on('end', () => {
                try {
                    resolve({ statusCode: res.statusCode, body: JSON.parse(data) });
                } catch {
                    resolve({ statusCode: res.statusCode, body: data });
                }
            });
        });

        req.on('error', reject);
        req.setTimeout(15000, () => { req.destroy(new Error('Request timed out')); });

        if (postData) req.write(postData);
        req.end();
    });
}

/**
 * Get a cached OAuth bearer token from Adobe IMS.
 * @returns {Promise<string>} access token
 */
async function getToken() {
    if (_cachedToken && _cachedExpiresAt > Date.now()) {
        return _cachedToken;
    }

    const clientId     = getClientId();
    const clientSecret = getClientSecret();

    if (!clientId || !clientSecret) {
        throw new Error('ADOBE_COMMERCE_CLIENT_ID and ADOBE_COMMERCE_CLIENT_SECRET must be set.');
    }

    const postData = new URLSearchParams({
        grant_type:    'client_credentials',
        client_id:     clientId,
        client_secret: clientSecret,
        scope:         IMS_SCOPE,
    }).toString();

    const imsUrl = process.env.ADOBE_COMMERCE_IMS_TOKEN_URL || 'https://ims-na1.adobelogin.com/ims/token/v3';

    const { statusCode, body } = await httpRequest(imsUrl, {
        method:  'POST',
        headers: {
            'Content-Type':   'application/x-www-form-urlencoded',
            'Content-Length': Buffer.byteLength(postData),
        },
    }, postData);

    if (statusCode >= 400 || !body.access_token) {
        throw new Error(`Adobe IMS token request failed (HTTP ${statusCode}): ${body.error_description || body.error || JSON.stringify(body)}`);
    }

    const expiresIn  = body.expires_in || 3600;
    _cachedToken     = body.access_token;
    _cachedExpiresAt = Date.now() + Math.max(60, expiresIn - 60) * 1000;

    return _cachedToken;
}

/**
 * Make an authenticated GET request to the ACCS REST API.
 * @param {string} path  e.g. '/products/NA-GW-002'
 * @param {object} [query]
 */
async function apiGet(path, query = {}) {
    const token    = await getToken();
    const clientId = getClientId();

    const qs  = Object.keys(query).length ? '?' + new URLSearchParams(query).toString() : '';
    const url = getBaseUrl() + '/' + path.replace(/^\//, '') + qs;

    const { statusCode, body } = await httpRequest(url, {
        method:  'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'x-api-key':     clientId,
            'Accept':        'application/json',
        },
    });

    if (statusCode >= 400) {
        throw new Error(`ACCS API error (HTTP ${statusCode}) for ${path}: ${body?.message || JSON.stringify(body)}`);
    }

    return body;
}

/**
 * Fetch a single product by SKU and return its custom_attributes as a flat object.
 * @param {string} sku
 * @returns {Promise<{ sku, name, price, status, custom: Record<string,string> }>}
 */
async function getProductAttributes(sku) {
    const product = await apiGet(`/products/${encodeURIComponent(sku)}`);

    const custom = {};
    for (const attr of product.custom_attributes || []) {
        custom[attr.attribute_code] = attr.value;
    }

    return {
        sku:    product.sku,
        name:   product.name,
        price:  product.price,
        status: product.status,
        custom,
    };
}

/**
 * When a product lookup fails or returns no fulfillment attribute, infer the
 * fulfillment code from well-known SKU prefixes.
 *
 * Add new prefixes here as new fulfillment suppliers are onboarded.
 */
const SKU_PREFIX_FULFILLMENT = {
    'CPPC-': 'CPPC',
};

function inferFulfillmentFromSku(sku) {
    if (!sku) return null;
    for (const [prefix, code] of Object.entries(SKU_PREFIX_FULFILLMENT)) {
        if (sku.toUpperCase().startsWith(prefix.toUpperCase())) {
            return code;
        }
    }
    return null;
}

/**
 * Enrich an array of order line items with their fulfillment attribute.
 *
 * Strategy (in order):
 *   1. Look up the product in ACCS and read the fulfillment custom attribute.
 *   2. If not found or attribute is empty, infer from SKU prefix (e.g. CPPC-*).
 *   3. If still null, leave as null (will be handled as unrouted).
 *
 * @param {object[]} items  - order.items from the webhook payload
 * @param {import('@azure/functions').InvocationContext} context
 * @returns {Promise<object[]>} items with `fulfillment` injected after `sku`
 */
async function enrichItemsWithFulfillment(items, context) {
    const results = await Promise.allSettled(
        items.map(async (item) => {
            const sku = item.sku;
            if (!sku) return item;

            let fulfillment = null;

            // 1. Try ACCS product lookup
            try {
                const { custom } = await getProductAttributes(sku);
                fulfillment = custom.fulfillment ?? null;
            } catch (err) {
                context?.warn?.(`ACCS lookup failed for SKU ${sku}: ${err.message}`);
            }

            // 2. Fallback: infer from SKU prefix
            if (!fulfillment) {
                const inferred = inferFulfillmentFromSku(sku);
                if (inferred) {
                    context?.log?.(`SKU ${sku}: fulfillment inferred from prefix → ${inferred}`);
                    fulfillment = inferred;
                }
            }

            // Inject fulfillment right after sku
            const enriched = {};
            for (const [k, v] of Object.entries(item)) {
                enriched[k] = v;
                if (k === 'sku') enriched.fulfillment = fulfillment;
            }
            return enriched;
        })
    );

    return results.map((r, i) => r.status === 'fulfilled' ? r.value : items[i]);
}

module.exports = { getToken, apiGet, getProductAttributes, enrichItemsWithFulfillment };
