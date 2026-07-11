<?php

const MARKETING_SITE_ORIGIN = 'https://www.nutraaxislabs.com';

function marketing_site_origin(): string
{
    return MARKETING_SITE_ORIGIN;
}

function marketing_site_url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');

    return marketing_site_origin() . $path;
}

function marketing_site_asset_url(string $path): string
{
    return marketing_site_url($path);
}

function marketing_site_css_version(): string
{
    static $version = null;

    if ($version !== null) {
        return $version;
    }

    $path = dirname(__DIR__) . '/assets/css/marketing-site.css';

    return $version = is_readable($path) ? (string) filemtime($path) : '1';
}

/**
 * @return list<array{title: string, href: string, image: string}>
 */
function marketing_site_feature_grid_items(): array
{
    return [
        [
            'title' => 'Metabolic and Weight Health',
            'href'  => '/metabolic-and-weight-health',
            'image' => '/media_1cb84689cb5cb3f061ee673f6757d08e6384829c3.png?width=750&format=png&optimize=medium',
        ],
        [
            'title' => 'Hormone and Reproductive Health',
            'href'  => '/hormone-and-reproductive-health',
            'image' => '/media_1ee701d07c47b53545b0e7499f5dfa403e0ed0d66.png?width=750&format=png&optimize=medium',
        ],
        [
            'title' => 'Mood, Stress and Sleep',
            'href'  => '/mood-stress-sleep',
            'image' => '/media_1d3680edcc36f1008b2f748fa97f409ab0f6e98e7.png?width=750&format=png&optimize=medium',
        ],
        [
            'title' => 'Healthy Aging and Longevity',
            'href'  => '/healthy-aging-longevity',
            'image' => '/media_1e8a7afb6fd21cca76bb3ff8868410849166f518d.png?width=750&format=png&optimize=medium',
        ],
        [
            'title' => 'Digestive and Gut Health',
            'href'  => '/digestive-gut-health',
            'image' => '/media_15d29e615fa02cec2b845213be28cb0d2ccb5d4c7.png?width=750&format=png&optimize=medium',
        ],
        [
            'title' => 'Pain, Immune Response and Physical Comfort',
            'href'  => '/pain-inflammation-physical-comfort',
            'image' => '/media_1d80952835d40fb5becbd5af8ff903d607f8d8ea8.png?width=750&format=png&optimize=medium',
        ],
    ];
}

/**
 * @param array{
 *   title: string,
 *   description: string,
 *   background_image: string,
 *   primary_cta?: array{label: string, href: string}|null,
 *   secondary_cta?: array{label: string, href: string}|null,
 *   variant?: 'main'|'default'
 * } $hero
 */
function marketing_site_render_hero(array $hero): void
{
    $variant = $hero['variant'] ?? 'default';
    $backgroundImage = marketing_site_asset_url($hero['background_image']);
    $primaryCta = $hero['primary_cta'] ?? null;
    $secondaryCta = $hero['secondary_cta'] ?? null;
    ?>
  <section class="marketing-hero-container">
    <div class="marketing-hero marketing-hero--<?= htmlspecialchars($variant) ?>">
      <div
        class="marketing-hero__background"
        style="background-image: url('<?= htmlspecialchars($backgroundImage) ?>');"
      >
        <div class="marketing-hero__inner">
          <h2 class="marketing-hero__title"><?= htmlspecialchars($hero['title']) ?></h2>
          <p class="marketing-hero__description"><strong><?= htmlspecialchars($hero['description']) ?></strong></p>
          <?php if ($primaryCta !== null || $secondaryCta !== null): ?>
          <div class="marketing-hero__cta-group">
            <?php if ($primaryCta !== null): ?>
            <a
              class="marketing-button marketing-button--primary"
              href="<?= htmlspecialchars($primaryCta['href']) ?>"
            ><?= htmlspecialchars($primaryCta['label']) ?></a>
            <?php endif; ?>
            <?php if ($secondaryCta !== null): ?>
            <a
              class="marketing-button marketing-button--outline"
              href="<?= htmlspecialchars($secondaryCta['href']) ?>"
            ><?= htmlspecialchars($secondaryCta['label']) ?></a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
    <?php
}

function marketing_site_render_feature_grid(): void
{
    ?>
  <section class="marketing-feature-grid-container">
    <div class="marketing-feature-grid">
      <div class="marketing-feature-grid__items">
        <?php foreach (marketing_site_feature_grid_items() as $item): ?>
        <div class="marketing-feature-grid__item">
          <a
            class="marketing-feature-grid__link"
            href="<?= htmlspecialchars(marketing_site_url($item['href'])) ?>"
            title="<?= htmlspecialchars($item['title']) ?>"
          >
            <div class="marketing-feature-grid__image">
              <img
                src="<?= htmlspecialchars(marketing_site_asset_url($item['image'])) ?>"
                alt=""
                loading="lazy"
                decoding="async"
              />
            </div>
            <div class="marketing-feature-grid__label">
              <p><?= htmlspecialchars($item['title']) ?></p>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
    <?php
}

function marketing_site_render_signup_stub(): void
{
    ?>
  <section class="marketing-signup-stub">
    <div class="marketing-signup-stub__inner">
      <p class="marketing-signup-stub__eyebrow">Provider Signup</p>
      <h2 class="marketing-signup-stub__title">Registration workflow coming soon</h2>
      <p class="marketing-signup-stub__lead">
        This page will host the provider onboarding experience. Workflow steps and form requirements
        will be added here in a follow-up release.
      </p>
      <div class="marketing-signup-stub__panel" aria-label="Provider signup placeholder">
        <p class="marketing-signup-stub__panel-title">Stub workspace</p>
        <p>Future steps such as account details, practice verification, and agreement acceptance will appear in this section.</p>
      </div>
    </div>
  </section>
    <?php
}
