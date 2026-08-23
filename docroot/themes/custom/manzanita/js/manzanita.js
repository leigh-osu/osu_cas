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
   * Deliberate divider sections are component-less (no .block inside), but
   * so are sections whose field blocks all rendered nothing at all (an
   * empty field block emits zero markup) — e.g. the story display's
   * tags/organizations band. The two are told apart by paint: a divider
   * only IS a divider if it shows a background colour, and white doesn't
   * count on a white page.
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
        if (hasContent) {
          return;
        }
        // Does an element paint a visible background colour? White is
        // treated as unpainted: the page ground is white, so a white band
        // is indistinguishable from blank space and safe to collapse.
        const paints = (el) => {
          const style = getComputedStyle(el);
          if (style.backgroundImage !== 'none') {
            return true;
          }
          const rgba = style.backgroundColor.match(/rgba?\(([^)]+)\)/);
          if (!rgba) {
            return false;
          }
          const [r, g, b, a = 1] = rgba[1].split(',').map(parseFloat);
          return a > 0 && (r < 255 || g < 255 || b < 255);
        };
        // Hide the whole section, not just the layout: bootstrap_styles
        // paints backgrounds AND spacing (e.g. "p-2 mt-1 mb-1") on outer
        // wrapper divs, which otherwise survive as a blank padded band.
        // The section renders as a chain of sole-child wrappers
        // (styles div > .container > .layout); climb to the outermost,
        // noting on the way whether any of it paints a colour.
        let wrapper = layout;
        let painted =
          paints(layout) || Array.from(layout.children).some(paints);
        while (
          wrapper.parentElement &&
          wrapper.parentElement.children.length === 1 &&
          !wrapper.parentElement.classList.contains('node__content')
        ) {
          wrapper = wrapper.parentElement;
          painted = painted || paints(wrapper);
        }
        if (!layout.querySelector('.block') && painted) {
          return; // Component-less AND coloured: a deliberate divider band.
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
      });
    },
  };

  /**
   * Apply / MyCAS follow the menu into its hamburger.
   *
   * The header pair (search block template) shows while the menu is
   * horizontal (d-md-flex; both menus collapse at the md boundary). Below
   * that, the pair joins the END of whichever collapsed menu the page has:
   * superfish's accordion clone (ul.sf-accordion, main menu) or madrone's
   * group mobile clone (#group-content-menu-accordion). Both clones are
   * built by other scripts on load/resize, so this sweeps on an interval
   * briefly and again on resize, marking its items with .cas-utility-item
   * so it never double-appends.
   */
  Drupal.behaviors.casUtilityMenuLinks = {
    attach(context) {
      once('cas-utility-links', 'body', context).forEach(() => {
        const LINKS = [
          { text: Drupal.t('Apply'), href: 'https://admissions.oregonstate.edu/apply-choose-application' },
          { text: Drupal.t('MyCAS'), href: '/mycas' },
        ];
        const append = (ul, liClass, aClass) => {
          if (!ul || ul.querySelector(':scope > .cas-utility-item')) {
            return;
          }
          LINKS.forEach(({ text, href }) => {
            const li = document.createElement('li');
            li.className = `${liClass} cas-utility-item`;
            const a = document.createElement('a');
            a.className = aClass;
            a.href = href;
            a.textContent = text;
            li.appendChild(a);
            ul.appendChild(li);
          });
        };
        const sweep = () => {
          append(
            document.querySelector('ul.sf-accordion'),
            'sf-depth-1 sf-no-children nav-item',
            'sf-depth-1 nav-link',
          );
          const bucket = document.querySelector('#group-content-menu-accordion');
          if (bucket) {
            append(bucket.querySelector('ul.menu--level-1'), 'nav-item', 'nav-link');
          }
        };
        sweep();
        let tries = 0;
        const timer = setInterval(() => {
          sweep();
          if (tries += 1, tries > 20) {
            clearInterval(timer);
          }
        }, 500);
        window.addEventListener('resize', () => {
          window.setTimeout(sweep, 250);
        });
      });
    },
  };
})(Drupal, once);
