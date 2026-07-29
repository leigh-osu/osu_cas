(function ($, Drupal, once) {
  Drupal.behaviors.ical = {
    attach: function (context, settings) {
      let isAndroid = navigator.userAgent.toLowerCase().indexOf("android") > -1;
      if (isAndroid) {
        $(once('ical-icon', '.ical-icon', context)).each(function () {
          let google = 'https://calendar.google.com/calendar/u/0/r?cid=';
          let currentLink = $(this).attr('href');
          $(this).attr('href', google + currentLink);
        });
      }
    }
  };
}(jQuery, Drupal, once));

