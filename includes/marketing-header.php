<?php
require_once __DIR__ . '/marketing-site.php';

$marketingNavItems = [
    ['label' => 'Our Products', 'href' => marketing_site_url('/categories')],
    ['label' => 'Our Science', 'href' => marketing_site_url('/our-science')],
    ['label' => 'Our Quality', 'href' => marketing_site_url('/our-quality')],
    ['label' => 'About Us', 'href' => marketing_site_url('/about-us')],
    ['label' => 'Contact', 'href' => marketing_site_url('/contact')],
    ['label' => 'Shop Now', 'href' => marketing_site_url('/search?q=&page=1&sort=&filter='), 'cta' => true],
];
?>
  <header class="marketing-header">
    <div class="marketing-header__wrapper">
      <div class="marketing-masthead">
        <p>Third-Party Tested | Practitioner Focused | Science Driven</p>
      </div>
      <nav class="marketing-nav" id="marketing-nav" aria-label="Main navigation">
        <button
          type="button"
          class="marketing-nav__toggle"
          id="marketing-nav-toggle"
          aria-expanded="false"
          aria-controls="marketing-nav-panel"
          aria-label="Open navigation menu"
        >
          <span class="marketing-nav__toggle-icon" aria-hidden="true"></span>
        </button>

        <div class="marketing-nav__brand">
          <a href="<?= htmlspecialchars(marketing_site_url('/')) ?>" aria-label="NutraAxis home">
            <img
              src="<?= htmlspecialchars(marketing_site_asset_url('/icons/app-logo.svg')) ?>"
              alt="NutraAxis"
              width="160"
              height="40"
            />
          </a>
        </div>

        <div class="marketing-nav__sections" id="marketing-nav-panel">
          <ul>
            <?php foreach ($marketingNavItems as $item): ?>
            <li<?= !empty($item['cta']) ? ' class="marketing-nav__cta-item"' : '' ?>>
              <a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="marketing-nav__tools">
          <a href="<?= htmlspecialchars(marketing_site_url('/login')) ?>" aria-label="Account">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true">
              <path d="M27 28C27 28.5523 27.4477 29 28 29C28.5523 29 29 28.5523 29 28H27ZM20.2351 20.3774L20.94 21.0867L20.9464 21.0803L20.2351 20.3774ZM15.9876 24.599L15.2806 25.3062C15.6703 25.6958 16.3017 25.6967 16.6926 25.3082L15.9876 24.599ZM11.7649 20.3774L11.0536 21.0803L11.0579 21.0846L11.7649 20.3774ZM3 28C3 28.5523 3.44772 29 4 29C4.55228 29 5 28.5523 5 28H3ZM10 10H11C11 7.24326 13.2433 5 16 5V4V3C12.1387 3 9 6.13869 9 10H10ZM16 4V5C18.7567 5 21 7.24326 21 10H22H23C23 6.13869 19.8613 3 16 3V4ZM22 10H21C21 12.7567 18.7567 15 16 15V16V17C19.8613 17 23 13.8613 23 10H22ZM16 16V15C13.2433 15 11 12.7567 11 10H10H9C9 13.8613 12.1387 17 16 17V16ZM28 28H29C29 26.2354 28.81 24.0404 27.9092 22.2556C27.4505 21.3466 26.7952 20.5197 25.8688 19.9238C24.9388 19.3255 23.7972 19 22.433 19V20V21C23.4688 21 24.2231 21.2432 24.7868 21.6058C25.3542 21.9708 25.7908 22.497 26.1238 23.1567C26.8064 24.5094 27 26.3144 27 28H28ZM22.433 20V19H21.1464V20V21H22.433V20ZM21.1464 20V19C20.5425 19 19.9547 19.2384 19.5237 19.6746L20.2351 20.3774L20.9464 21.0803C20.9937 21.0324 21.0658 21 21.1464 21V20ZM20.2351 20.3774L19.5301 19.6682L15.2827 23.8897L15.9876 24.599L16.6926 25.3082L20.94 21.0867L20.2351 20.3774ZM15.9876 24.599L16.6946 23.8918L12.472 19.6702L11.7649 20.3774L11.0579 21.0846L15.2806 25.3062L15.9876 24.599ZM11.7649 20.3774L12.4763 19.6746C12.0465 19.2396 11.4633 19 10.8536 19V20V21C10.9367 21 11.0051 21.0312 11.0536 21.0803L11.7649 20.3774ZM10.8536 20V19H9.56701V20V21H10.8536V20ZM9.56701 20V19C8.20284 19 7.06122 19.3255 6.13117 19.9238C5.20476 20.5197 4.54952 21.3466 4.09077 22.2556C3.18996 24.0404 3 26.2354 3 28H4H5C5 26.3144 5.19355 24.5094 5.87624 23.1567C6.20924 22.497 6.64575 21.9708 7.21316 21.6058C7.77692 21.2432 8.53118 21 9.56701 21V20Z" fill="#2A2E33"></path>
            </svg>
          </a>
          <a href="<?= htmlspecialchars(marketing_site_url('/search')) ?>" aria-label="Search">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true">
              <path d="M14.5 6C10.0817 6 6.5 9.58172 6.5 14C6.5 18.4183 10.0817 22 14.5 22C18.9183 22 22.5 18.4183 22.5 14C22.5 9.58172 18.9183 6 14.5 6ZM4.5 14C4.5 8.47715 9.47715 3.5 14.5 3.5C19.5228 3.5 24.5 8.47715 24.5 14C24.5 19.0228 19.5228 24 14.5 24C9.47715 24 4.5 19.0228 4.5 14ZM26.2071 26.7929L29.5 30.0858L28.0858 31.5L24.7929 28.2071C23.191 29.3647 21.4045 30.125 19.5 30.5V28.5C20.9583 28.1667 22.2917 27.5417 23.5 26.625L26.2071 26.7929Z" fill="#2A2E33"></path>
            </svg>
          </a>
          <a href="<?= htmlspecialchars(marketing_site_url('/cart')) ?>" aria-label="Cart">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true">
              <path d="M10 26C10.5523 26 11 26.4477 11 27C11 27.5523 10.5523 28 10 28C9.44772 28 9 27.5523 9 27C9 26.4477 9.44772 26 10 26ZM24 26C24.5523 26 25 26.4477 25 27C25 27.5523 24.5523 28 24 28C23.4477 28 23 27.5523 23 27C23 26.4477 23.4477 26 24 26ZM6 6H8.68L10.68 20.18C10.77 20.78 11.3 21.2 11.9 21.2H23.5C24.1 21.2 24.63 20.78 24.72 20.18L26 12H9.1M10.68 8H24.3L23.1 19.2H11.9L10.68 8Z" fill="#2A2E33"></path>
            </svg>
          </a>
        </div>
      </nav>
    </div>
  </header>
  <div class="marketing-header__spacer" aria-hidden="true"></div>
