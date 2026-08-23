/**
 * @file
 */

(function ($, Drupal) {
  Drupal.behaviors.nodeRevisionDeleteSummaies = {
    attach(context, settings) {
      // Display the action in the vertical tab summary.
      $(context)
        .find('.node-revision-delete-settings-form')
        .drupalSetSummary((context) => {
          const override = context.querySelector(
            'input[name="node_revision_delete[override]"]',
          );
          const enabledPlugins = context.querySelectorAll(
            '.node-revision-delete-plugin-settings input[name$="[status]"]:checked',
          );

          const isOverridden = override.checked;
          const isEnabled = enabledPlugins.length > 0;

          if (isOverridden && isEnabled)
            return Drupal.t('Overridden, revision deletion is enabled');
          if (isOverridden && !isEnabled)
            return Drupal.t('Overridden, revision deletion is disabled');
          if (!isOverridden && isEnabled)
            return Drupal.t('Using defaults, revision deletion is enabled');

          return Drupal.t('Using defaults, revision deletion is disabled');
        });
    },
  };
})(jQuery, Drupal);
