<?php

require_once __DIR__ . '/marketing-site.php';

function provider_signup_landing_css_version(): string
{
    static $version = null;

    if ($version !== null) {
        return $version;
    }

    $path = dirname(__DIR__) . '/assets/css/provider-signup-landing.css';

    return $version = is_readable($path) ? (string) filemtime($path) : '1';
}

function provider_signup_landing_hero_image_url(): string
{
    return marketing_site_asset_url('/images/ourscience.jpg');
}

function provider_signup_landing_apply_url(): string
{
    return '/provider-signup/application.php';
}

function provider_signup_render_landing_page(): void
{
    $heroImage = provider_signup_landing_hero_image_url();
    $productsUrl = marketing_site_url('/categories');
    $applyUrl = provider_signup_landing_apply_url();
    ?>
<div class="na-providers">
  <section class="hero" style="background: url('<?= htmlspecialchars($heroImage) ?>') center/cover no-repeat;">
    <div class="container">
      <div class="hero-inner">
        <div class="hero-text">
          <div class="section-label">For Practitioners</div>
          <h1>Your Practice. Your Pricing.<br/><span>Your Clinic Store.</span></h1>
          <p>NutraAxis gives practitioners a dedicated, co-branded store for evidence-informed supplements &mdash; without the cost of carrying inventory. You set your pricing, invite your patients, and we handle distribution and fulfillment behind the scenes.</p>
          <div class="hero-actions">
            <a class="btn-cta" href="<?= htmlspecialchars($applyUrl) ?>">Apply for Provider Access</a>
            <a class="btn-secondary" href="<?= htmlspecialchars($productsUrl) ?>">Explore Our Products</a>
          </div>
        </div>
        <div class="hero-visual">
          <div class="quality-card">
            <div class="qc-header">
              <div class="qc-header-icon">
                <svg aria-hidden="true" focusable="false" fill="none" width="22" height="22" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
              </div>
              <div class="qc-header-text">
                <strong>Clinic Store</strong>
                <span>Your Practice Name Here</span>
              </div>
              <div class="qc-verified-pill">Active</div>
            </div>
            <div class="qc-body">
              <div class="qc-row">
                <div class="qc-dot">
                  <svg aria-hidden="true" focusable="false" fill="none" width="15" height="15" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="qc-row-text">
                  <strong>Co-Branded Storefront</strong>
                  <span>A private ordering page for your patients only</span>
                </div>
                <span class="qc-pass">Live</span>
              </div>
              <div class="qc-row">
                <div class="qc-dot">
                  <svg aria-hidden="true" focusable="false" fill="none" width="15" height="15" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="qc-row-text">
                  <strong>Provider-Set Pricing</strong>
                  <span>You control the retail price on every product</span>
                </div>
                <span class="qc-pass">Your Control</span>
              </div>
              <div class="qc-row">
                <div class="qc-dot">
                  <svg aria-hidden="true" focusable="false" fill="none" width="15" height="15" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="qc-row-text">
                  <strong>Zero Inventory</strong>
                  <span>No stock to purchase, store, or manage</span>
                </div>
                <span class="qc-pass">$0 Held</span>
              </div>
              <div class="qc-row">
                <div class="qc-dot">
                  <svg aria-hidden="true" focusable="false" fill="none" width="15" height="15" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="qc-row-text">
                  <strong>Fulfillment Handled</strong>
                  <span>Every order picked, packed, and shipped for you</span>
                </div>
                <span class="qc-pass">Managed</span>
              </div>
            </div>
            <div class="qc-footer">
              <span class="qc-footer-label">Built On:</span>
              <span class="qc-tag">Evidence-Informed Formulas</span>
              <span class="qc-tag">LOT-Tested Quality</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="why-section">
    <div class="container">
      <div class="section-header">
        <div class="section-label">Why NutraAxis</div>
        <h2 class="section-heading">Built for Practitioner-Guided Care</h2>
        <p class="section-sub">The same evidence-informed formulation and quality standards behind every NutraAxis product now extend into how you dispense them.</p>
      </div>
      <div class="feature-grid">
        <div class="feature-card">
          <div class="feature-icon">
            <svg aria-hidden="true" focusable="false" fill="none" width="22" height="22" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <h3>Evidence-Informed Formulas</h3>
          <p>Every product is developed through clinical literature review, with ingredients selected for bioavailability and documented evidence.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <svg aria-hidden="true" focusable="false" fill="none" width="22" height="22" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <h3>LOT-Tested Quality</h3>
          <p>Third-party verified, cGMP-manufactured, with a Certificate of Analysis available for every batch you dispense.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <svg aria-hidden="true" focusable="false" fill="none" width="22" height="22" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>
            </svg>
          </div>
          <h3>Six Wellness Pillars</h3>
          <p>A systems-based product architecture spanning metabolic, hormonal, mood, aging, digestive, and physical comfort support.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <svg aria-hidden="true" focusable="false" fill="none" width="22" height="22" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-4a4 4 0 100-8 4 4 0 000 8zm6 4a4 4 0 100-8"/>
            </svg>
          </div>
          <h3>Practitioner-First Support</h3>
          <p>Education resources and product information built for clinical use, not consumer marketing repurposed for your patients.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="clinic-process">
    <div class="container">
      <div class="section-header">
        <div class="section-label">How It Works</div>
        <h2 class="section-heading">Your Clinic Store, Start to Finish</h2>
        <p class="section-sub">A dispensing model built to remove inventory risk from your practice, not add steps to it.</p>
      </div>
      <div class="process-track">
        <div class="process-step">
          <div class="process-num">1</div>
          <h3>Apply for Access</h3>
          <p>Submit your provider application for a quick verification review.</p>
        </div>
        <div class="process-step">
          <div class="process-num filled">2</div>
          <h3>Launch Your Store</h3>
          <p>Get a co-branded Clinic Store built on the full NutraAxis catalog.</p>
        </div>
        <div class="process-step">
          <div class="process-num">3</div>
          <h3>Set Your Pricing</h3>
          <p>Choose the retail price your patients see on every product, product by product.</p>
        </div>
        <div class="process-step">
          <div class="process-num filled">4</div>
          <h3>Invite Your Patients</h3>
          <p>Share your private store link so patients can order directly, on your recommendation.</p>
        </div>
        <div class="process-step">
          <div class="process-num">5</div>
          <h3>We Fulfill the Order</h3>
          <p>We pick, pack, and ship each order. You never hold or manage stock.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="economics-section">
    <div class="container">
      <div class="economics-grid">
        <div class="economics-copy">
          <div class="section-label">Your Margin</div>
          <h3>Straightforward Economics, No Inventory Risk</h3>
          <p>Every product carries a standard wholesale acquisition cost. Your Clinic Store lets you set the retail price your patients pay &mdash; the difference between the two is yours.</p>
          <ul>
            <li>
              <span class="li-dot"><svg aria-hidden="true" focusable="false" fill="none" width="12" height="12" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
              No upfront purchase, storage, or shrinkage cost
            </li>
            <li>
              <span class="li-dot"><svg aria-hidden="true" focusable="false" fill="none" width="12" height="12" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
              Pricing is yours to adjust at any time
            </li>
            <li>
              <span class="li-dot"><svg aria-hidden="true" focusable="false" fill="none" width="12" height="12" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
              We manage distribution, fulfillment, and shipping
            </li>
          </ul>
        </div>
        <div class="economics-panel">
          <div class="econ-row">
            <span class="econ-label">Wholesale Acquisition Cost</span>
            <span class="econ-value">Set by NutraAxis</span>
          </div>
          <div class="econ-row">
            <span class="econ-label">Your Retail Price</span>
            <span class="econ-value">Set by You</span>
          </div>
          <div class="econ-row total">
            <span class="econ-label">Your Margin</span>
            <span class="econ-value">Retail Price &minus; Acquisition Cost</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="guarantee">
    <div class="container">
      <div class="guarantee-inner">
        <div class="guarantee-icon-wrap">
          <svg aria-hidden="true" focusable="false" fill="none" height="36" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="36">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>
        <div class="guarantee-text">
          <h3>The Same Standard, Now Behind Your Storefront</h3>
          <p>Your Clinic Store runs on the same evidence-informed formulations and quality standards as the rest of NutraAxis &mdash; every product you dispense carries a Certificate of Analysis your patients can access.</p>
        </div>
        <div class="guarantee-list">
          <div class="g-item">
            <div class="g-dot"><svg aria-hidden="true" focusable="false" fill="none" height="12" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" width="12"><polyline points="20 6 9 17 4 12"/></svg></div>
            Practitioner-Guided
          </div>
          <div class="g-item">
            <div class="g-dot"><svg aria-hidden="true" focusable="false" fill="none" height="12" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" width="12"><polyline points="20 6 9 17 4 12"/></svg></div>
            Quality Verified
          </div>
          <div class="g-item">
            <div class="g-dot"><svg aria-hidden="true" focusable="false" fill="none" height="12" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" width="12"><polyline points="20 6 9 17 4 12"/></svg></div>
            Zero Inventory
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="cta-banner">
    <div class="container">
      <div class="cta-inner">
        <div class="cta-left">
          <div class="cta-doc-icon">
            <svg aria-hidden="true" focusable="false" fill="none" height="32" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" width="32">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </div>
          <div class="cta-copy">
            <h2>Ready to Launch Your Clinic Store?</h2>
            <p>Apply for provider access and set up your co-branded store &mdash; no inventory required.</p>
          </div>
        </div>
        <a class="btn-white" href="<?= htmlspecialchars($applyUrl) ?>">
          Apply for Provider Access
          <svg aria-hidden="true" focusable="false" fill="none" height="16" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" width="16">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </a>
      </div>
    </div>
  </section>

  <section class="disclaimer">
    <div class="container">
      <p>These statements have not been evaluated by the Food and Drug Administration. NutraAxis supplements are not intended to diagnose, treat, cure, or prevent any disease. Products are intended to support general wellness. All product claims, ingredients, and labeling are subject to applicable DSHEA and FTC guidelines. Provider participation is subject to application review and program terms.</p>
    </div>
  </section>
</div>
    <?php
}

function provider_signup_render_application_start_page(string $startError = ''): void
{
    ?>
<div class="na-providers">
  <section class="apply-section apply-section--standalone">
    <div class="container">
      <div class="section-header">
        <div class="section-label">Provider Application</div>
        <h2 class="section-heading">Apply for Provider Access</h2>
        <p class="section-sub">Enter your email to begin a draft application. Save progress at any time and submit once all required company, compliance, and banking information is complete.</p>
      </div>
      <div class="apply-card">
        <p>We will email you a secure link to continue your application. Already started? Use the link from your confirmation email.</p>
        <?php if ($startError !== ''): ?>
        <div class="apply-alert" role="alert"><?= htmlspecialchars($startError) ?></div>
        <?php endif; ?>
        <form class="apply-form" method="post" action="/provider-signup/start.php">
          <label>Provider email address
            <input type="email" name="provider_email" required autocomplete="email" placeholder="you@yourpractice.com" />
          </label>
          <button class="btn-cta" type="submit">Start application</button>
        </form>
        <p class="apply-note">Your application stays in draft until you submit it for operations review.</p>
        <p class="apply-note"><a href="/provider-signup/">← Back to For Providers</a></p>
      </div>
    </div>
  </section>
</div>
    <?php
}
