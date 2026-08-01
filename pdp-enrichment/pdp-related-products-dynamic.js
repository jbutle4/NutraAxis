/**
 * Dynamic related / up-sell / cross-sell products loader for nutraaxislabs.com PDP pages.
 *
 * Reads the SKU from the current page URL (last path segment), queries the
 * ACCS Catalog Service GraphQL API for the product links entered per product
 * in the Commerce Admin (Related Products, Up-Sells, Cross-Sells), and
 * renders them as product cards.
 *
 * DA setup (enrichment/pdp/{sku} html-loader block or product template):
 *   <div id="pdp-related-products"></div>
 *   <script src="https://nutraaxisweb.azurewebsites.net/pdp-enrichment/pdp-related-products-dynamic.js?v=1"></script>
 *
 * Optional configuration on the mount element:
 *   data-link-types="related,upsell,crosssell"  (default: all three)
 *   data-heading-related="Related Products"
 *   data-heading-upsell="You May Also Like"
 *   data-heading-crosssell="Pairs Well With"
 */
(function pdpRelatedProducts(global) {
  'use strict';

  var COMMERCE_ENDPOINT = 'https://na1.api.commerce.adobe.com/VLuKe3eeTwf1D5oxmLBfcr/graphql';
  var COMMERCE_HEADERS = {
    'Content-Type': 'application/json',
    Store: 'default',
    'Magento-Store-Code': 'main_website_store',
    'Magento-Store-View-Code': 'default',
    'Magento-Website-Code': 'base',
  };
  var DISPLAY_CSS_URL = 'https://nutraaxisweb.azurewebsites.net/pdp-enrichment/pdp-related-products.css?v=1';
  var PRODUCT_URL_PREFIX = '/products/';

  var DEFAULT_SECTIONS = [
    { type: 'related', heading: 'Related Products' },
    { type: 'upsell', heading: 'You May Also Like' },
    { type: 'crosssell', heading: 'Pairs Well With' },
  ];

  var LINKS_QUERY = ''
    + 'query($skus:[String]!){'
    + 'products(skus:$skus){sku '
    + 'links(linkTypes:["related","upsell","crosssell"]){linkTypes '
    + 'product{sku name urlKey images(roles:["small_image"]){url} '
    + '... on SimpleProductView{price{final{amount{value currency}}}}'
    + '}}}}';

  function ensureDisplayStyles() {
    if (document.getElementById('pdp-related-products-css')) {
      return;
    }

    var link = document.createElement('link');
    link.id = 'pdp-related-products-css';
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
    return document.getElementById('pdp-related-products')
      || document.querySelector('[data-pdp-related-products]');
  }

  function sectionsFromMount(mount) {
    var typesAttr = (mount.getAttribute('data-link-types') || '').trim().toLowerCase();
    var enabled = typesAttr !== ''
      ? typesAttr.split(',').map(function (t) { return t.trim(); })
      : null;

    return DEFAULT_SECTIONS
      .filter(function (section) {
        return !enabled || enabled.indexOf(section.type) !== -1;
      })
      .map(function (section) {
        var override = mount.getAttribute('data-heading-' + section.type);
        return {
          type: section.type,
          heading: override !== null && override.trim() !== '' ? override.trim() : section.heading,
        };
      });
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function productHref(product) {
    var urlKey = String(product.urlKey || '').trim();
    var sku = String(product.sku || '').trim().toLowerCase();
    if (urlKey === '' || sku === '') {
      return null;
    }

    return PRODUCT_URL_PREFIX + encodeURIComponent(urlKey) + '/' + encodeURIComponent(sku);
  }

  function productImageUrl(product) {
    var images = product.images || [];
    var url = images.length > 0 ? String(images[0].url || '') : '';
    if (url === '') {
      return null;
    }

    return url + '/as/card.webp?width=400&quality=80';
  }

  function formatPrice(product) {
    var amount = product
      && product.price
      && product.price.final
      && product.price.final.amount;

    if (!amount || typeof amount.value !== 'number') {
      return '';
    }

    try {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: amount.currency || 'USD',
      }).format(amount.value);
    } catch (error) {
      return '$' + amount.value.toFixed(2);
    }
  }

  function renderCard(product) {
    var href = productHref(product);
    if (!href) {
      return '';
    }

    var imageUrl = productImageUrl(product);
    var price = formatPrice(product);

    return ''
      + '<a class="pdp-related-card" href="' + escapeHtml(href) + '">'
      + (imageUrl
        ? '<span class="pdp-related-card-image"><img loading="lazy" alt="' + escapeHtml(product.name) + '" src="' + escapeHtml(imageUrl) + '" /></span>'
        : '<span class="pdp-related-card-image pdp-related-card-image-empty"></span>')
      + '<span class="pdp-related-card-name">' + escapeHtml(product.name) + '</span>'
      + (price !== '' ? '<span class="pdp-related-card-price">' + escapeHtml(price) + '</span>' : '')
      + '</a>';
  }

  function renderSections(mount, links, sections) {
    var html = '';

    sections.forEach(function (section) {
      var cards = '';
      var seen = {};

      links.forEach(function (link) {
        var product = link.product;
        if (!product || (link.linkTypes || []).indexOf(section.type) === -1) {
          return;
        }
        if (seen[product.sku]) {
          return;
        }
        seen[product.sku] = true;
        cards += renderCard(product);
      });

      if (cards === '') {
        return;
      }

      html += ''
        + '<section class="pdp-related-section" data-link-type="' + escapeHtml(section.type) + '">'
        + '<h2 class="pdp-related-heading">' + escapeHtml(section.heading) + '</h2>'
        + '<div class="pdp-related-grid">' + cards + '</div>'
        + '</section>';
    });

    if (html === '') {
      mount.innerHTML = '';
      return false;
    }

    ensureDisplayStyles();
    mount.classList.add('pdp-related-products');
    mount.innerHTML = html;
    return true;
  }

  function fetchLinks(sku) {
    return fetch(COMMERCE_ENDPOINT, {
      method: 'POST',
      headers: COMMERCE_HEADERS,
      body: JSON.stringify({
        query: LINKS_QUERY,
        variables: { skus: [sku] },
      }),
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Commerce API request failed (' + response.status + ').');
        }
        return response.json();
      })
      .then(function (payload) {
        var products = payload && payload.data && payload.data.products;
        if (!products || products.length === 0) {
          return [];
        }
        return products[0].links || [];
      });
  }

  function loadRelatedProducts(options) {
    options = options || {};
    var mount = findMountNode();
    if (!mount) {
      return Promise.reject(new Error('Could not find #pdp-related-products on this page.'));
    }

    var sku = options.sku || global.PDP_RELATED_SKU || readSkuFromUrl();
    if (!sku) {
      mount.innerHTML = '';
      return Promise.reject(new Error('Could not determine SKU from this page URL.'));
    }

    var sections = sectionsFromMount(mount);

    return fetchLinks(sku)
      .then(function (links) {
        return renderSections(mount, links, sections);
      })
      .catch(function (error) {
        mount.innerHTML = '';
        throw error;
      });
  }

  global.PdpRelatedProducts = {
    load: loadRelatedProducts,
    readSkuFromUrl: readSkuFromUrl,
  };

  if (global.PDP_RELATED_AUTO_LOAD !== false) {
    var boot = function () {
      loadRelatedProducts().catch(function () {
        /* fails silently: section is simply omitted */
      });
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot);
    } else {
      boot();
    }
  }
})(window);
