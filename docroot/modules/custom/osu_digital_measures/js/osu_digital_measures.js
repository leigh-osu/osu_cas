/**
 * @file
 * Renders Digital Measures reports into their containers.
 *
 * Iterates over every report configured in
 * drupalSettings.osuDigitalMeasures.reports and calls the Digital Measures
 * web-profiles widget for each, so multiple reports can live on one page.
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.osuDigitalMeasures = {
    attach: function (context) {
      var reports = (drupalSettings.osuDigitalMeasures && drupalSettings.osuDigitalMeasures.reports) || {};

      Object.keys(reports).forEach(function (id) {
        var config = reports[id];

        // Only act on containers present in this context, once each.
        once('osu-digital-measures', config.container, context).forEach(function () {
          if (typeof window.dmWebProfiles === 'undefined') {
            return;
          }

          window.dmWebProfiles.showProfile({
            container: config.container,
            clientId: config.clientId,
            reportId: config.reportId,
            username: config.username
          }).then(function () {
            // Move the full link text into the title attribute and truncate the
            // visible text, matching the legacy D7 behaviour.
            $(config.container + ' a').each(function () {
              var text = $(this).text();
              $(this).prop('title', text);
              if (text.length > 30) {
                $(this).text(text.substr(0, 30) + '...');
              }
            });
          });
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
