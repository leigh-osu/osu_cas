/**
 * @file
 * Attaches behaviors for the toc_js_per_node module.
 */

(function ($, Drupal) {
  /**
   * Provides the summary information for the node form vertical tabs.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the node form summaries.
   */
  Drupal.behaviors.tocjsPerNodeSummary = {
    attach() {
      // The drupalSetSummary method required for this behavior is not available
      // on the Blocks administration page, so we need to make sure this
      // behavior is processed only if drupalSetSummary is defined.
      if (typeof $.fn.drupalSetSummary === 'undefined') {
        return;
      }

      function tocSummary(context) {
        const active = context.querySelector(
          '[data-drupal-selector="edit-toc-js-active"]',
        );
        if (active && active.checked) {
          return Drupal.t('Enabled');
        }
      }

      $('[data-drupal-selector="edit-toc-js"]').drupalSetSummary(tocSummary);
    },
  };
})(jQuery, Drupal);
