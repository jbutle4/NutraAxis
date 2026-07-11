<?php
require_once __DIR__ . '/marketing-site.php';
?>
  <footer class="marketing-footer">
    <div class="marketing-footer__content">
      <div class="marketing-footer__info">
        <ul>
          <li>© <?= date('Y') ?> NutraAxis. All rights reserved.</li>
          <li>
            <a href="https://www.google.com/maps/place/3420+Fairlane+Farms+Rd,+Wellington,+FL+33414" target="_blank" rel="noopener noreferrer">
              3420 Fairlane Farms Rd, Suite 200, Wellington, FL 33414
            </a>
          </li>
          <li><a href="tel:+14076718070"><strong>(220) 226-8635</strong></a></li>
          <li><a href="mailto:sales@nutraaxislabs.com">sales@nutraaxislabs.com</a></li>
        </ul>
      </div>

      <div class="marketing-footer__links">
        <p class="marketing-footer__column-title">Information</p>
        <ul>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/')) ?>">Home</a></li>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/categories')) ?>">Our Products</a></li>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/our-science')) ?>">Our Science</a></li>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/our-quality')) ?>">Our Quality</a></li>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/about-us')) ?>">About Us</a></li>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/contact')) ?>">Contact</a></li>
        </ul>
      </div>

      <div class="marketing-footer__links">
        <p class="marketing-footer__column-title">Support</p>
        <ul>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/privacy-policy')) ?>">Privacy</a></li>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/terms-and-conditions')) ?>">Terms</a></li>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/shipping-policy')) ?>">Shipping Policy</a></li>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/return-policy')) ?>">Return Policy</a></li>
          <li><a href="<?= htmlspecialchars(marketing_site_url('/faqs')) ?>">FAQ</a></li>
        </ul>
      </div>

      <div class="marketing-footer__contact">
        <p class="marketing-footer__contact-title">Stay Connected</p>
        <p class="marketing-footer__contact-lead">Sign up for the latest news and updates.</p>
        <div class="marketing-footer__social">
          <a href="https://www.facebook.com/people/NutraSync/61573634464720/" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
            <img src="<?= htmlspecialchars(marketing_site_asset_url('/icons/facebook.svg')) ?>" alt="" width="24" height="24" loading="lazy" />
          </a>
          <a href="https://www.linkedin.com/company/nutrasync/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
            <img src="<?= htmlspecialchars(marketing_site_asset_url('/icons/linkedin.svg')) ?>" alt="" width="24" height="24" loading="lazy" />
          </a>
          <a href="https://www.instagram.com/nutrasync1" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
            <img src="<?= htmlspecialchars(marketing_site_asset_url('/icons/instagram.svg')) ?>" alt="" width="24" height="24" loading="lazy" />
          </a>
        </div>
      </div>
    </div>

    <div class="marketing-footer__certifications">
      <div class="marketing-footer__certifications-row">
        <div class="marketing-footer__certification">
          <img
            src="<?= htmlspecialchars(marketing_site_asset_url('/media_14c025f751caf0a0d213b902f31782fb583706dc6.png?width=750&format=png&optimize=medium')) ?>"
            alt="Good Manufacturing Practice"
            loading="lazy"
            decoding="async"
          />
          <p>Good Manufacturing Practice</p>
        </div>
        <div class="marketing-footer__certification">
          <img
            src="<?= htmlspecialchars(marketing_site_asset_url('/media_1c8eb1fbcf25641d12f40dc8d9f42413eb2a5045a.png?width=750&format=png&optimize=medium')) ?>"
            alt="Manufactured In A Multi-certified registered facility"
            loading="lazy"
            decoding="async"
          />
          <p>Manufactured In A Multi-certified registered facility</p>
        </div>
        <div class="marketing-footer__certification">
          <img
            src="<?= htmlspecialchars(marketing_site_asset_url('/media_1d1fcf89f7d58cbfb29f84e551a028335826c9318.png?width=750&format=png&optimize=medium')) ?>"
            alt="Third Party Lab Tested"
            loading="lazy"
            decoding="async"
          />
          <p>Third Party Lab Tested</p>
        </div>
      </div>
    </div>

    <div class="marketing-footer__bottom">
      <div class="marketing-footer__bottom-row">
        <p class="marketing-footer__copyright">© <?= date('Y') ?> NutraAxis. All rights reserved.</p>
        <div class="marketing-footer__payments">
          <p class="marketing-footer__payments-title">Our Payment Partners</p>
          <img
            src="<?= htmlspecialchars(marketing_site_asset_url('/media_18ef89d65c039cbef860e242be7f7d88a8ceaea61.png?width=750&format=png&optimize=medium')) ?>"
            alt="Payment partners"
            loading="lazy"
            decoding="async"
          />
          <img
            src="<?= htmlspecialchars(marketing_site_asset_url('/media_167c6b7ff7d928020dd526b786dcc39a4bcd033a5.png?width=750&format=png&optimize=medium')) ?>"
            alt=""
            loading="lazy"
            decoding="async"
          />
        </div>
      </div>
    </div>
  </footer>

  <script>
  (function () {
    var toggle = document.getElementById('marketing-nav-toggle');
    var panel = document.getElementById('marketing-nav-panel');
    var header = document.querySelector('.marketing-header__wrapper');
    var spacer = document.querySelector('.marketing-header__spacer');

    function syncHeaderSpacer() {
      if (!header || !spacer) {
        return;
      }
      spacer.style.height = header.offsetHeight + 'px';
    }

    function closeNav() {
      if (!toggle || !panel) {
        return;
      }
      panel.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Open navigation menu');
      document.body.classList.remove('marketing-nav-open');
    }

    if (toggle && panel) {
      toggle.addEventListener('click', function () {
        var expanded = panel.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.setAttribute('aria-label', expanded ? 'Close navigation menu' : 'Open navigation menu');
        document.body.classList.toggle('marketing-nav-open', expanded);
      });

      panel.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeNav);
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          closeNav();
        }
      });
    }

    syncHeaderSpacer();
    window.addEventListener('resize', syncHeaderSpacer);
  })();
  </script>
</body>
</html>
