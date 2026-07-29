/**
 * @file
 * Custom JavaScript for vertical tabs.
 * phpcs:ignoreFile
 */
(function ($, Drupal, once) {
  Drupal.behaviors.osuParagraphsVerticalTabs = {
    attach: function (context, settings) {
      once('osu-paragraphs-vertical-tabs', 'div.paragraph.tab-wrapper-paragraph', context).forEach((tabWrapperElement) => {
        /**
         * Handles click events for tab links within a tab wrapper.
         * Prevents the default action of the event, processes the tab click to show the
         * corresponding tab content, and updates the active states and ARIA attributes.
         *
         * @function handleClick
         * @param {Event} event - The click event fired by the tab link.
         */
        const handleClick = (event) => {
          event.preventDefault();

          const $tabWrapper = $(tabWrapperElement);
          const $clickedTabLink = $(event.currentTarget);
          const targetSelector = $clickedTabLink.attr('href');
          const $targetContent = $(targetSelector);
          const $parentTabs = $clickedTabLink.closest('.tab-wrapper-paragraph__tabs');

          // Get the height of all the tabs.
          const $allTabHeight = calculateTabHeights($tabWrapper.find('.tab-wrapper-paragraph__tab'));
          const $allActiveTabs = $tabWrapper.find('.tab-wrapper-paragraph__tab.active');
          const $allActiveTabLinks = $tabWrapper.find('.tab-wrapper-paragraph__tab-link--active');
          const $allActiveTabContents = $tabWrapper.find('.tab-wrapper-paragraph__tab-content--active');
          if ($clickedTabLink.hasClass('tab-wrapper-paragraph__tab-link--active')) {
            if (window.innerWidth <= 768) {
              $clickedTabLink.removeClass('tab-wrapper-paragraph__tab-link--active');
              $clickedTabLink.parent().removeClass('active');
              $targetContent.removeClass('tab-wrapper-paragraph__tab-content--active').css('max-height', '').attr('aria-hidden', 'true');
              return;
            }
          }
          // Remove active classes and aria-hidden attributes
          $allActiveTabs.removeClass('active');
          $allActiveTabLinks.removeClass('tab-wrapper-paragraph__tab-link--active');
          $allActiveTabContents.removeClass('tab-wrapper-paragraph__tab-content--active').css('max-height', '').attr('aria-hidden', 'true');

          // Add active classes and remove aria-hidden attribute
          $clickedTabLink.parent().addClass('active');
          $clickedTabLink.addClass('tab-wrapper-paragraph__tab-link--active');
          $targetContent.addClass('tab-wrapper-paragraph__tab-content--active').removeAttr('aria-hidden');

          // Adjust heights
          $targetContent.css('max-height', $targetContent.prop('scrollHeight') + 'px');
          // Set the height of the osu-tabs container to the height of the controlledPanel
          // if window width is greater than 769 px.
          if (window.innerWidth > 768) {
            const $targetTabContentHeight = $targetContent.prop('scrollHeight');
            // Check to ensure
            // that the new height is not smaller than the total tab height.
            if ($allTabHeight > $targetTabContentHeight) {
              $parentTabs.css('height', $allTabHeight + 'px');
            } else {
              $parentTabs.css('height', $targetTabContentHeight + 'px');
            }
          }

        };

        const $tabLinks = $(tabWrapperElement).find('a.tab-wrapper-paragraph__tab-link');
        $tabLinks.on('click', handleClick);

        $tabLinks.on('keydown', function (e) {
          // Enter (13) or Space key (32)
          if (e.which === 13 || e.which === 32) {
            e.preventDefault();
            $(this).trigger('click');
          }
        });

      });
      /**
       * Handles changes in the URL hash and triggers click events based on the hash value.
       * Parses the hash and determines if it relates to a tab link or tab content,
       * then simulates a click event on the corresponding element.
       * @function handleHashChange
       * @return {void}
       */
      const handleHashChange = () => {
        const hash = window.location.hash;
        if (hash) {
          const $tabArr = hash.split('-');
          if ($tabArr[0] === '#tab') {
            if ($tabArr[1] === 'link') {
              $(`a[id="${hash.substring(1)}"]`).trigger('click');
            } else if ($tabArr[1] === 'content') {
              $(`a[href="${hash}"]`).trigger('click');
            }
          }
        }
      };
      /**
       * Adjusts the layout and styling of tab wrapper elements based on the current window width.
       *
       * This function handles window resize events to ensure proper height calculations
       * for elements with the `tab-wrapper-paragraph` class. It dynamically adjusts
       * the maximum height of active tab content and the height of parent tabs based on
       * whether the viewport width is greater than 768 pixels (desktop) or 768 pixels or less (mobile).
       *
       * For desktop viewports (width > 768px), it recalculates and sets the maximum height for
       * active tab content and adjusts the parent tabs height to accommodate all tabs within the container.
       * For mobile viewports (width <= 768px), it resets any fixed height styles applied to the parent tabs.
       *
       * The function uses jQuery for DOM manipulation and works on `div` elements
       * with the class `tab-wrapper-paragraph`.
       * @function handleWindowResize
       * @return {void}
       */
      const handleWindowResize = () => {
        const width = window.innerWidth;
        const $tabWrappers = $('div.paragraph.tab-wrapper-paragraph');

        $tabWrappers.each(function () {
          const $wrapper = $(this);
          const $activeContent = $wrapper.find('.tab-wrapper-paragraph__tab-content--active');
          const $parentTabs = $wrapper.find('.tab-wrapper-paragraph__tabs');

          if (width > 768 && $activeContent.length) {
            // Recalculate heights on resize for desktop
            const $allTabHeight = calculateTabHeights($wrapper.find('.tab-wrapper-paragraph__tab'));

            const contentHeight = $activeContent.prop('scrollHeight');

            $activeContent.css('max-height', contentHeight + 'px');
            $parentTabs.css('height', Math.max($allTabHeight, contentHeight) + 'px');
          } else if (width <= 768) {
            // Reset fixed heights on mobile
            $parentTabs.css('height', '');
          }
        });
      };
      /**
       * Calculates the total height of the tab elements.
       *
       * @function calculateTabHeights
       * @param {jQuery} $tabsCollection - A jQuery collection of tab elements whose heights are to be calculated.
       * @returns {number} The total height of all elements in the collection, rounded up to the nearest integer.
       */
      const calculateTabHeights = ($tabsCollection) => {
        let totalHeight = 0;
        $tabsCollection.each(function () {
          totalHeight += Math.ceil($(this).outerHeight(true));
        });
        return totalHeight;
      };

      if (window.innerWidth > 768) {
        // Find all first tabs and for each one click on it.
        once('osu-paragraphs-vertical-tabs-first-tab', 'div.paragraph.tab-wrapper-paragraph', context).forEach((tabWrapperElement) => {
          $(tabWrapperElement).find('a.tab-wrapper-paragraph__tab-link').first().trigger('click');
        });
      }
      // If an anchor link is used, open the linked tab item.
      handleHashChange();
      // If we load on the same page, run the script again.
      $(window).on('hashchange', handleHashChange);
      // If we resize the browser, ensure the opened tab is still open.
      $(window).on('resize', handleWindowResize);
    },
  };
})(jQuery, Drupal, once);
