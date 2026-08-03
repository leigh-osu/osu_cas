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
   * Header search overlay, after D7 larch's #search-overlay.
   *
   * The header shows only a magnifier button (.cas-search-toggle); it opens
   * the full-viewport #cas-search-overlay holding the real search form,
   * focusing the input. Exit Search, Escape, or the toggle again close it
   * and return focus to the toggle. Markup: the search block template
   * override; styles: scss/layout/_cas_header.scss.
   */
  Drupal.behaviors.casSearchOverlay = {
    attach(context) {
      once('cas-search-overlay', '.cas-search-toggle', context).forEach(
        (toggle) => {
          const overlay = document.getElementById('cas-search-overlay');
          if (!overlay) {
            return;
          }
          const open = () => {
            overlay.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            const input = overlay.querySelector('input[type="search"]');
            if (input) {
              input.focus();
            }
          };
          const close = () => {
            overlay.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
          };
          toggle.addEventListener('click', () => {
            if (overlay.hidden) {
              open();
            } else {
              close();
            }
          });
          const exit = overlay.querySelector('.cas-search-overlay__exit');
          if (exit) {
            exit.addEventListener('click', close);
          }
          overlay.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
              close();
            }
          });
        },
      );
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
        // A background image or video IS the content: D7 "entity
        // background" bands migrate as empty paragraph blocks with the
        // image painted either on the section wrapper (1-col parallax
        // strips — an ancestor) or on the per-column block wrappers
        // (2-col photo columns — descendants). Either way the emptiness
        // is deliberate.
        if (
          layout.closest('.bg-image, .background-local-video') ||
          layout.closest('[style*="background-image"]') ||
          layout.querySelector('.bg-image, [style*="background-image"]')
        ) {
          return;
        }
        const hasContent =
          layout.textContent.trim() !== '' ||
          layout.querySelector('img, svg, iframe, video, audio, form, canvas');
        if (!hasContent) {
          // Hide the whole section, not just the layout: bootstrap_styles
          // paints backgrounds AND spacing (e.g. "p-2 mt-1 mb-1") on outer
          // wrapper divs, which otherwise survive as a blank padded band.
          // The section renders as a chain of sole-child wrappers
          // (styles div > .container > .layout); climb to the outermost.
          let wrapper = layout;
          while (
            wrapper.parentElement &&
            wrapper.parentElement.children.length === 1 &&
            !wrapper.parentElement.classList.contains('node__content')
          ) {
            wrapper = wrapper.parentElement;
          }
          // Only hide the climbed wrapper if it is itself empty — no text
          // or embedded media beyond the empty layout. Anything else means
          // a template put real content between wrapper and layout; hide
          // just the layout in that case.
          if (
            wrapper !== layout &&
            (wrapper.textContent.trim() !== '' ||
              wrapper.querySelector('img, svg, iframe, video, audio, form, canvas'))
          ) {
            wrapper = layout;
          }
          wrapper.style.display = 'none';
        }
      });
    },
  };
})(Drupal, once);
