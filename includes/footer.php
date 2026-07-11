  <footer class="site-footer">
    <div class="container<?= !empty($pageContainerClass) ? ' ' . htmlspecialchars((string) $pageContainerClass) : '' ?>">
      <p>&copy; <?= date('Y') ?> <strong>NutraAxis</strong>. All rights reserved. <a href="/eula/">EULA</a> · <a href="/privacy-policy/">Privacy Policy</a></p>
    </div>
  </footer>

  <script>
  (function () {
    var toggle = document.getElementById('nav-toggle');
    var closeBtn = document.getElementById('nav-close');
    var panel = document.getElementById('nav-panel');
    var overlay = document.getElementById('nav-overlay');

    function openMenu() {
      panel.hidden = false;
      overlay.hidden = false;
      requestAnimationFrame(function () {
        panel.classList.add('is-open');
        overlay.classList.add('is-open');
      });
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Close navigation menu');
      document.body.classList.add('menu-open');
    }

    function closeMenu() {
      panel.classList.remove('is-open');
      overlay.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Open navigation menu');
      document.body.classList.remove('menu-open');
      window.setTimeout(function () {
        if (!panel.classList.contains('is-open')) {
          panel.hidden = true;
          overlay.hidden = true;
        }
      }, 250);
    }

    toggle.addEventListener('click', function () {
      if (toggle.getAttribute('aria-expanded') === 'true') closeMenu();
      else openMenu();
    });

    closeBtn.addEventListener('click', closeMenu);
    overlay.addEventListener('click', closeMenu);

    document.querySelectorAll('.nav-parent-toggle').forEach(function (button) {
      button.addEventListener('click', function () {
        var group = button.closest('.nav-group');
        if (!group) {
          return;
        }

        var expanded = group.classList.toggle('is-expanded');
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        var label = button.getAttribute('aria-label') || '';
        var section = label.replace(/^(Expand|Collapse)\s+/, '');
        button.setAttribute('aria-label', (expanded ? 'Collapse ' : 'Expand ') + section);
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') closeMenu();
    });

    var topChrome = document.querySelector('.site-top-chrome');
    var topChromeSpacer = document.querySelector('.site-top-chrome-spacer');
    function syncTopChromeSpacer() {
      if (!topChrome || !topChromeSpacer) {
        return;
      }
      var height = topChrome.offsetHeight;
      topChromeSpacer.style.height = height + 'px';
      document.documentElement.style.setProperty('--site-top-chrome-height', height + 'px');
    }
    syncTopChromeSpacer();
    window.addEventListener('resize', syncTopChromeSpacer);

    var portalSearch = document.querySelector('[data-portal-search]');
    if (portalSearch) {
      var searchInput = document.getElementById('portal-search-input');
      var searchResults = document.getElementById('portal-search-results');
      var searchTimer = null;
      var searchController = null;

      function escapeHtml(value) {
        return String(value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function closeSearchResults() {
        if (!searchResults || !searchInput) {
          return;
        }

        searchResults.hidden = true;
        searchResults.innerHTML = '';
        searchInput.setAttribute('aria-expanded', 'false');
      }

      function showSearchMessage(message, className) {
        if (!searchResults || !searchInput) {
          return;
        }

        searchResults.innerHTML = '<div class="' + className + '">' + escapeHtml(message) + '</div>';
        searchResults.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
      }

      function renderSearchResults(results) {
        if (!searchResults || !searchInput) {
          return;
        }

        if (!results.length) {
          showSearchMessage('No matching links found.', 'portal-search-empty');
          return;
        }

        searchResults.innerHTML = results.map(function (result) {
          var attrs = result.external ? ' target="_blank" rel="noopener noreferrer"' : '';
          var description = result.description
            ? '<div class="portal-search-result-desc">' + escapeHtml(result.description) + '</div>'
            : '';
          return '<a class="portal-search-result" role="option" href="' + escapeHtml(result.href) + '"' + attrs + '>'
            + '<span class="portal-search-result-title">'
            + '<span>' + escapeHtml(result.title) + '</span>'
            + '<span class="portal-search-result-type">' + escapeHtml(result.type || 'Link') + '</span>'
            + '</span>'
            + description
            + '</a>';
        }).join('');
        searchResults.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
      }

      function runPortalSearch(query) {
        if (searchController) {
          searchController.abort();
        }

        searchController = new AbortController();
        showSearchMessage('Searching...', 'portal-search-loading');

        fetch('/portal-search.php?q=' + encodeURIComponent(query), {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          signal: searchController.signal,
        })
          .then(function (response) {
            if (!response.ok) {
              throw new Error('Search failed');
            }
            return response.json();
          })
          .then(function (data) {
            renderSearchResults(Array.isArray(data.results) ? data.results : []);
          })
          .catch(function (error) {
            if (error.name === 'AbortError') {
              return;
            }
            showSearchMessage('Search is temporarily unavailable.', 'portal-search-empty');
          });
      }

      if (searchInput && searchResults) {
        searchInput.addEventListener('input', function () {
          var query = searchInput.value.trim();
          window.clearTimeout(searchTimer);

          if (query.length < 2) {
            closeSearchResults();
            return;
          }

          searchTimer = window.setTimeout(function () {
            runPortalSearch(query);
          }, 180);
        });

        searchInput.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            closeSearchResults();
            searchInput.blur();
          }
        });

        document.addEventListener('click', function (event) {
          if (!portalSearch.contains(event.target)) {
            closeSearchResults();
          }
        });
      }
    }

    function directPageInnerChild(inner, selector) {
      var children = inner.children;
      var i;
      for (i = 0; i < children.length; i++) {
        if (children[i].matches(selector)) {
          return children[i];
        }
      }
      return null;
    }

    function wrapListPageStickyTop() {
      document.querySelectorAll('.page-inner').forEach(function (inner) {
        if (inner.classList.contains('page-no-sticky-top') || inner.querySelector('.page-sticky-top')) {
          return;
        }

        // Form/edit and detail/view pages scroll normally; sticky wrapping breaks nested layouts.
        if (directPageInnerChild(inner, '.admin-form, .form-card, .account-grid')) {
          return;
        }

        var contentRoot = directPageInnerChild(inner, '.admin-table-wrap')
          || directPageInnerChild(inner, '.detail-grid')
          || directPageInnerChild(inner, '.capability-grid')
          || directPageInnerChild(inner, '.functions')
          || directPageInnerChild(inner, '.legal-prose');
        if (!contentRoot) {
          return;
        }

        var sticky = document.createElement('div');
        sticky.className = 'page-sticky-top';
        var node = inner.firstElementChild;
        while (node && node !== contentRoot) {
          var next = node.nextElementSibling;
          sticky.appendChild(node);
          node = next;
        }

        if (sticky.childElementCount === 0) {
          return;
        }

        inner.insertBefore(sticky, contentRoot);
      });
    }

    wrapListPageStickyTop();
  })();
  </script>

</body>
</html>
