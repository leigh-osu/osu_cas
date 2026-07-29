/**
 * @file
 * Colorbox Media Video integration.
 */

(function($) {
  Drupal.behaviors.colorboxMediaVideo = {
    attach: function (context, settings) {

      if (typeof $.colorbox !== 'function' || typeof settings.colorbox === 'undefined') {
        return;
      }

      if (settings.colorbox.mobiledetect && window.matchMedia) {
        // Disable Colorbox for small screens.
        var mq = window.matchMedia('(max-device-width: ' + settings.colorbox.mobiledevicewidth + ')');
        if (mq.matches) {
          $.colorbox.remove();
          return;
        }
      }

      settings.colorbox.rel = function () {
        return $(this).data('colorbox-gallery')
      };

      settings.colorbox.html = function() {
        return $(this).data('colorbox-media-video-modal');
      }

      once('init-colorbox', '.colorbox-media-video', context).forEach(element => {
        $(element).colorbox(settings.colorbox);
      });

    }
  };
})(jQuery);
