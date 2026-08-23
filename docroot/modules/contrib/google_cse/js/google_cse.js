/**
 * @file
 * Adds Google Custom Search Watermark.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.googleCSECustomSearch = {
    attach: function (context, settings) {
      var getWatermarkBackground = function (value) {
        var googleCSEBaseUrl = 'https://www.google.com/cse/intl/';
        var googleCSEImageUrl = 'images/google_custom_search_watermark.gif';
        var language = drupalSettings.googlePSE.language + '/';
        return value ? '' : ' url(' + googleCSEBaseUrl + language + googleCSEImageUrl + ') left no-repeat';
      };
      var removeWatermark = function (e) {
        $(e.target).css('background', '#ffffff');
      };
      var addWatermark = function (e) {
        $(e.target).css('background', '#ffffff' + getWatermarkBackground($(e.target).val()));
      };

      var googleCSEWatermark = function (context, query) {
        // Find any core drupal search inputs on the page.
        var searchInputs = $(`[data-drupal-selector='${query}']`);

        if (drupalSettings.googlePSE.displayWatermark === true) {
          searchInputs.on("blur", addWatermark);
          searchInputs.each(function () {
            var event = {};
            event.target = this;
            addWatermark(event);
          });
        }
        else {
          searchInputs.on("blur", removeWatermark);
          searchInputs.each(function () {
            var event = {};
            event.target = this;
            removeWatermark(event);
          });
        }
        searchInputs.focus(removeWatermark);

      };

      googleCSEWatermark('[data-drupal-selector="search-block-form"] [data-drupal-form-fields="edit-keys--2"]', 'edit-keys');
      googleCSEWatermark('[data-drupal-selector="search-block-form"] [data-drupal-form-fields="edit-keys"]', 'edit-keys');
      googleCSEWatermark('[data-drupal-selector="search-form"]', 'edit-keys');
      googleCSEWatermark('[data-drupal-selector="google-cse-search-box-form"]', 'edit-query');
    }
  };
})(jQuery, Drupal, drupalSettings);
