/**
 * Dynamic product page enrichment loader for nutraaxislabs.com PDP pages.
 *
 * Reads the SKU from the current page URL (last path segment), POSTs it to
 * the Ops API, and injects the returned HTML into #pdp-enrichment.
 *
 * DA setup (enrichment/pdp/{sku} html-loader block):
 *   <div id="pdp-enrichment"></div>
 *   <script src="https://nutraaxisweb.azurewebsites.net/pdp-enrichment/pdp-enrichment-dynamic.js?v=43"></script>
 */
(function pdpEnrichmentDynamic(global) {
  'use strict';

  var DEFAULT_API_URL = 'https://nutraaxisweb.azurewebsites.net/coa-test/pdp-enrichment.php';
  var DISPLAY_CSS_URL = 'https://nutraaxisweb.azurewebsites.net/pdp-enrichment/pdp-enrichment-display.css?v=2';
  var MOUNT_STYLE = 'display:block;width:100%;max-width:720px;margin-left:auto;margin-right:auto;padding:24px 16px;box-sizing:border-box;font-family:inherit;color:inherit;';

  function ensureDisplayStyles() {
    if (document.getElementById('pdp-enrichment-display-css')) {
      return;
    }

    var link = document.createElement('link');
    link.id = 'pdp-enrichment-display-css';
    link.rel = 'stylesheet';
    link.href = DISPLAY_CSS_URL;
    document.head.appendChild(link);
  }

  function readSkuFromUrl() {
    var parts = global.location.pathname.replace(/\/+$/, '').split('/').filter(Boolean);
    if (parts.length === 0) {
      return null;
    }

    return String(parts[parts.length - 1] || '').toLowerCase();
  }

  function findMountNode() {
    return document.getElementById('pdp-enrichment')
      || document.querySelector('[data-pdp-enrichment]');
  }

  function renderError(mount, message) {
    mount.innerHTML = ''
      + '<p style="padding:16px 0;color:#666;font-size:.95rem;">'
      + String(message || 'Unable to load product enrichment.')
      + '</p>';
  }

  function requestEnrichment(apiUrl, sku) {
    return fetch(apiUrl, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ sku: sku }),
    }).then(function (response) {
      return response.json().catch(function () {
        return { ok: false, error: 'Invalid API response.' };
      }).then(function (payload) {
        if (!response.ok || !payload || payload.ok !== true || !payload.item) {
          throw new Error(payload && payload.error ? payload.error : 'API request failed.');
        }
        return payload;
      });
    });
  }

  function loadPdpEnrichment(options) {
    options = options || {};
    var mount = findMountNode();
    if (!mount) {
      return Promise.reject(new Error('Could not find #pdp-enrichment on this page.'));
    }

    var sku = options.sku || global.PDP_ENRICHMENT_SKU || readSkuFromUrl();
    if (!sku) {
      renderError(mount, 'Could not determine SKU from this page URL.');
      return Promise.reject(new Error('Missing SKU.'));
    }

    var apiUrl = options.apiUrl || global.PDP_ENRICHMENT_API_URL || DEFAULT_API_URL;

    renderError(mount, 'Loading product details…');

    return requestEnrichment(apiUrl, sku)
      .then(function (payload) {
        var item = payload.item;
        if (!item.html) {
          throw new Error('Published enrichment has no HTML content.');
        }

        ensureDisplayStyles();
        mount.classList.add('pdp-enrichment-content');
        mount.setAttribute('style', MOUNT_STYLE);
        mount.innerHTML = item.html;
        return payload;
      })
      .catch(function (error) {
        renderError(mount, error && error.message ? error.message : String(error));
        throw error;
      });
  }

  global.PdpEnrichmentDynamic = {
    load: loadPdpEnrichment,
    readSkuFromUrl: readSkuFromUrl,
    DEFAULT_API_URL: DEFAULT_API_URL,
  };

  if (global.PDP_ENRICHMENT_AUTO_LOAD !== false) {
    var boot = function () {
      loadPdpEnrichment().catch(function () {
        /* surfaced in mount node */
      });
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot);
    } else {
      boot();
    }
  }
})(window);
