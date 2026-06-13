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

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') closeMenu();
    });
  })();
  </script>

</body>
</html>
