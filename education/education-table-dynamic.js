/**
 * Dynamic Education Resources table loader for nutraaxislabs.com / AEM (production).
 *
 * Usage in Digital Author html-loader block:
 *   1. Keep #edu-tbody empty (loading row only)
 *   2. Include search + filterEdu() from the page HTML
 *   3. Load this script:
 *        <script src="https://nutraaxisweb.azurewebsites.net/education/education-table-dynamic.js"></script>
 *
 * Access: guests and non-allowed ACCS groups are redirected home.
 * Allowed group IDs: 4 Practitioner, 8 Employee, 9 Sales, 10 Practitioner-Not Exempt.
 */
(function educationTableDynamic(global) {
  'use strict';

  var DEFAULT_API_URL = 'https://nutraaxisweb.azurewebsites.net/api/public/education-resources.php';
  var DEFAULT_GRAPHQL_ENDPOINT = 'https://na1.api.commerce.adobe.com/VLuKe3eeTwf1D5oxmLBfcr/graphql';
  var AUTH_COOKIE = 'auth_dropin_user_token';
  var DEFAULT_ALLOWED_GROUP_IDS = [4, 8, 9, 10];
  var ACCESS_OK_CLASS = 'edu-access-ok';

  function allowedGroupIds() {
    return Array.isArray(global.EDU_ALLOWED_GROUP_IDS) && global.EDU_ALLOWED_GROUP_IDS.length
      ? global.EDU_ALLOWED_GROUP_IDS.map(Number)
      : DEFAULT_ALLOWED_GROUP_IDS.slice();
  }

  function homeUrl() {
    if (typeof global.EDU_ACCESS_HOME_URL === 'string' && global.EDU_ACCESS_HOME_URL) {
      return global.EDU_ACCESS_HOME_URL;
    }
    return String(global.location.origin || 'https://www.nutraaxislabs.com') + '/';
  }

  function graphqlEndpoint() {
    return global.EDU_GRAPHQL_ENDPOINT || DEFAULT_GRAPHQL_ENDPOINT;
  }

  function getCookie(name) {
    var parts = String(document.cookie || '').split(';');
    var prefix = name + '=';
    for (var i = 0; i < parts.length; i += 1) {
      var part = parts[i].trim();
      if (part.indexOf(prefix) === 0) {
        return decodeURIComponent(part.slice(prefix.length));
      }
    }
    return '';
  }

  function decodeGroupUid(uid) {
    if (uid == null || uid === '') {
      return 0;
    }
    var raw = String(uid).trim();
    if (/^\d+$/.test(raw)) {
      return parseInt(raw, 10);
    }
    try {
      var decoded = global.atob(raw);
      var match = String(decoded).match(/(\d+)\s*$/);
      if (match) {
        return parseInt(match[1], 10);
      }
    } catch (e) {
      /* not base64 */
    }
    return 0;
  }

  function redirectHome() {
    var dest = homeUrl();
    if (global.location.href === dest) {
      return false;
    }
    global.location.replace(dest);
    return false;
  }

  function markAccessOk() {
    document.documentElement.classList.add(ACCESS_OK_CLASS);
    return true;
  }

  function fetchCustomerGroupId(token) {
    var query = 'query EDU_CUSTOMER_GROUP { customer { group { uid } } }';
    return fetch(graphqlEndpoint(), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: 'Bearer ' + token,
        Store: 'default',
      },
      credentials: 'omit',
      mode: 'cors',
      body: JSON.stringify({ query: query }),
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('Group lookup failed (HTTP ' + response.status + ').');
      }
      return response.json();
    }).then(function (payload) {
      var errors = payload && Array.isArray(payload.errors) ? payload.errors : [];
      var authError = errors.some(function (err) {
        return err && err.extensions && err.extensions.category === 'graphql-authentication';
      });
      if (authError || !payload || !payload.data || !payload.data.customer) {
        return 0;
      }
      return decodeGroupUid(payload.data.customer.group && payload.data.customer.group.uid);
    });
  }

  function ensurePractitionerAccess() {
    if (global.EDU_SKIP_ACCESS_GATE === true) {
      return Promise.resolve(markAccessOk());
    }
    if (document.documentElement.classList.contains(ACCESS_OK_CLASS)) {
      return Promise.resolve(true);
    }

    var token = getCookie(AUTH_COOKIE);
    if (!token) {
      return Promise.resolve(redirectHome());
    }

    return fetchCustomerGroupId(token)
      .then(function (groupId) {
        if (allowedGroupIds().indexOf(groupId) === -1) {
          return redirectHome();
        }
        return markAccessOk();
      })
      .catch(function () {
        return redirectHome();
      });
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function resourceButtonHtml(item) {
    var type = String(item.type || '');
    var url = String(item.url || '');
    if (!url) {
      return '<span style="color:var(--text-muted);">—</span>';
    }

    var isVideo = type.toLowerCase() === 'video';
    var label = isVideo ? 'Open Video' : 'Open PDF';
    var icon = isVideo
      ? '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>'
      : '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 18 15 15"/></svg>';

    return ''
      + '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer"'
      + ' style="display:inline-flex;align-items:center;gap:6px;background:var(--teal-pale);color:var(--teal-dark);'
      + 'font-size:.82rem;font-weight:700;padding:7px 16px;border-radius:50px;border:1px solid var(--border);'
      + 'text-decoration:none;transition:background .2s,color .2s;"'
      + ' onmouseover="this.style.background=\'var(--teal)\';this.style.color=\'#fff\';"'
      + ' onmouseout="this.style.background=\'var(--teal-pale)\';this.style.color=\'var(--teal-dark)\';">'
      + icon
      + label + '</a>';
  }

  function typeBadgeHtml(type) {
    var label = escapeHtml(type || '');
    return ''
      + '<span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:50px;'
      + 'background:var(--teal-pale);color:var(--teal-dark);font-size:.78rem;font-weight:700;">'
      + label
      + '</span>';
  }

  function rowHtml(item, index) {
    var bg = index % 2 === 1 ? ' background: var(--bg-light);' : '';
    return ''
      + '<tr class="edu-row" data-edu-id="' + escapeHtml(item.id || '') + '" style="border-bottom: 1px solid var(--border);' + bg + '">'
      + '<td style="padding: 14px 20px; font-weight: 600;">' + escapeHtml(item.description) + '</td>'
      + '<td style="padding: 14px 20px;">' + typeBadgeHtml(item.type) + '</td>'
      + '<td style="padding: 14px 20px; text-align: center;">' + resourceButtonHtml(item) + '</td>'
      + '</tr>';
  }

  function applyZebraStripes(tbody) {
    var visibleIndex = 0;
    tbody.querySelectorAll('.edu-row').forEach(function (row) {
      if (row.style.display === 'none') {
        return;
      }
      row.style.background = visibleIndex % 2 === 1 ? 'var(--bg-light)' : '';
      visibleIndex += 1;
    });
  }

  function renderRows(tbody, items) {
    tbody.innerHTML = items.map(function (item, index) {
      return rowHtml(item, index);
    }).join('');

    applyZebraStripes(tbody);

    if (typeof global.filterEdu === 'function') {
      var search = document.getElementById('edu-search');
      global.filterEdu(search ? search.value : '');
    }
  }

  function renderError(tbody, message) {
    tbody.innerHTML = ''
      + '<tr><td colspan="3" style="padding:24px;text-align:center;color:var(--text-muted);">'
      + escapeHtml(message)
      + '</td></tr>';
  }

  function loadEducationTable(options) {
    options = options || {};
    var apiUrl = options.apiUrl || global.EDU_TABLE_API_URL || DEFAULT_API_URL;
    var tbody = document.getElementById('edu-tbody');

    if (!tbody) {
      return Promise.reject(new Error('Could not find #edu-tbody on this page.'));
    }

    renderError(tbody, 'Loading education resources…');

    return fetch(apiUrl, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'omit',
      mode: 'cors',
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('API request failed (HTTP ' + response.status + ').');
        }
        return response.json();
      })
      .then(function (payload) {
        var items = Array.isArray(payload.items) ? payload.items : [];
        if (items.length === 0) {
          throw new Error('API returned no education resources.');
        }

        renderRows(tbody, items);
        return payload;
      })
      .catch(function (error) {
        renderError(
          tbody,
          'Unable to load education resources: ' + (error && error.message ? error.message : String(error))
        );
        throw error;
      });
  }

  function start() {
    ensurePractitionerAccess().then(function (allowed) {
      if (!allowed) {
        return;
      }
      loadEducationTable().catch(function () {
        /* surfaced in table */
      });
    });
  }

  global.EducationTableDynamic = {
    load: loadEducationTable,
    ensureAccess: ensurePractitionerAccess,
    DEFAULT_API_URL: DEFAULT_API_URL,
    ALLOWED_GROUP_IDS: DEFAULT_ALLOWED_GROUP_IDS.slice(),
  };

  if (global.EDU_TABLE_AUTO_LOAD !== false) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', start);
    } else {
      start();
    }
  }
})(window);
