/**
 * @file
 * Contains date_all_day.js.
 */

(function ($, Drupal, window, document) {
  'use strict';

  // Datetime Range All Day.
  Drupal.behaviors.date_all_day = {
    attach: function (context, settings) {

      $('.field--widget-daterange-all-day fieldset').each(function () {
        var $this = $(this);
        var all_day_checkbox = $this.find('[name$="[all_day]"]');
        var date_start_field = $this.find('[name$="[value][date]"]');
        var date_end_field = $this.find('[name$="[end_value][date]"]');
        var time_start_field = $this.find('[name$="[value][time]"]');
        var time_end_field = $this.find('[name$="[end_value][time]"]');
        var time_start = '00:00:00';
        var time_end = '23:59:59';

        // Show or hide the time fields depending on the checkbox status.
        function changeAllDay() {
          if (all_day_checkbox.is(':checked')) {
            time_start_field.hide();
            time_end_field.hide();
            if (date_start_field.val() !== '') {
              time_start_field.val(time_start);
            }
            if (date_end_field.val() !== '') {
              time_end_field.val(time_end);
            }
          }
          else {
            time_start_field.show();
            time_end_field.show();
          }
        }
        changeAllDay();

        all_day_checkbox.change(changeAllDay);

        // Change the time field values depending on the checkbox status.
        date_start_field.change(function () {
          if (all_day_checkbox.is(':checked')) {
            if (date_start_field.val() !== '') {
              time_start_field.val(time_start);
            }
            else {
              time_start_field.val('');
            }
          }
        });
        date_end_field.change(function () {
          if (all_day_checkbox.is(':checked')) {
            if (date_end_field.val() !== '') {
              time_end_field.val(time_end);
            }
            else {
              time_end_field.val('');
            }
          }

        });

      });

    }
  };
})(jQuery, Drupal, this, this.document);
