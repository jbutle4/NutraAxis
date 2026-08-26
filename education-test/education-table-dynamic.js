/**
 * Dynamic Education Resources table loader for nutraaxislabs.com / AEM.
 *
 * Usage in Digital Author html-loader block:
 *   1. Keep #edu-tbody empty (loading row only)
 *   2. Include search + filterEdu() from the page HTML
 *   3. Load this script:
 *        <script src="https://nutraaxisweb.azurewebsites.net/education-test/education-table-dynamic.js"></script>
 */
(function educationTableDynamic(global) {
  'use strict';

  var DEFAULT_API_URL = 'https://nutraaxisweb.azurewebsites.net/api/public/education-resources.php';

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

  global.EducationTableDynamic = {
    load: loadEducationTable,
    DEFAULT_API_URL: DEFAULT_API_URL,
  };

  if (global.EDU_TABLE_AUTO_LOAD !== false) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        loadEducationTable().catch(function () {
          /* surfaced in table */
        });
      });
    } else {
      loadEducationTable().catch(function () {
        /* surfaced in table */
      });
    }
  }
})(window);
