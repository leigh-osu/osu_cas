/**
 * @file
 * Manzanita theme behaviors.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Hero sections: pull the section background up beneath the header ramp.
   *
   * A `cas-hero` container class on the first Layout Builder section (see
   * scss/layout/_cas_hero.scss) means the section background should sit
   * under the header + primary-menu bars. The height of that stack varies
   * by breakpoint and page, so measure the real distance from the banner's
   * top edge to the section and expose it as --cas-hero-pull; the CSS does
   * the actual pulling and content re-padding.
   */
  function casHeroMeasure(target) {
    const banner = document.querySelector('header[role="banner"]');
    if (!banner) {
      return;
    }
    // Reset before measuring so re-runs don't compound the offset.
    target.style.removeProperty('--cas-hero-pull');
    const pull =
      target.getBoundingClientRect().top - banner.getBoundingClientRect().top;
    if (pull > 0) {
      target.style.setProperty('--cas-hero-pull', `${pull}px`);
    }
  }

  Drupal.behaviors.casHero = {
    attach(context) {
      // The hero pull only makes sense for the FIRST Layout Builder section
      // (right below the header). On a later section the measured offset is
      // the full scroll distance, which would yank it up the whole page, so
      // scope the effect to the first section: its wrapper is the only
      // cas-hero element that contains the page's first .layout-builder__layout.
      const firstLayout = document.querySelector(
        'main[role="main"] .layout-builder__layout',
      );
      once('cas-hero', 'main .cas-hero', context).forEach((hero) => {
        // Leave the Layout Builder editor's preview alone.
        if (hero.closest('.layout-builder')) {
          return;
        }
        // Ignore a cas-hero on any section other than the first.
        if (!firstLayout || !hero.contains(firstLayout)) {
          return;
        }
        // Signal the CSS (header/menu ramp) that a valid hero is present.
        document.body.classList.add('has-cas-hero');
        // For video backgrounds the visible carrier is the outer
        // .background-local-video wrapper; pull that one up.
        const target = hero.closest('.background-local-video') || hero;
        const update = () => casHeroMeasure(target);
        update();
        window.addEventListener('resize', update);
        const banner = document.querySelector('header[role="banner"]');
        if (banner && 'ResizeObserver' in window) {
          new ResizeObserver(update).observe(banner);
        }
      });
    },
  };

  /**
   * Collapse Layout Builder sections whose blocks are all empty.
   *
   * D7 rendered empty paragraphs as nothing; the migration turns every
   * paragraph into an LB section with a min-height and (often) a background,
   * so an empty one paints as a stray coloured band — e.g. a 20px orange
   * strip stacked on a menu bar, which reads as a mis-centred bar.
   * Deliberate divider sections are component-less (no .block inside) and
   * are left alone; only sections that HAVE blocks, all of them empty of
   * text and embedded media, are hidden.
   */
  Drupal.behaviors.casCollapseEmptySections = {
    attach(context) {
      once(
        'cas-empty-section',
        'main .layout-builder__layout',
        context,
      ).forEach((layout) => {
        // Leave the Layout Builder editor alone.
        if (layout.closest('.layout-builder')) {
          return;
        }
        const blocks = layout.querySelectorAll('.block');
        if (!blocks.length) {
          return; // Component-less divider section: intentional.
        }
        const hasContent =
          layout.textContent.trim() !== '' ||
          layout.querySelector('img, svg, iframe, video, audio, form, canvas');
        if (!hasContent) {
          // Hide the painted wrapper (background sits on the section
          // wrapper around the layout), falling back to the layout itself.
          const wrapper = layout.closest('.bg-color') || layout;
          wrapper.style.display = 'none';
        }
      });
    },
  };
})(Drupal, once);
