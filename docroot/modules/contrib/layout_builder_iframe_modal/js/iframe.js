/**
 * Implementation of the openIframe Ajax command.
 *
 * This command uses the default openDialog command to initialize a dialog. It
 * then attaches event listeners to the iframe and window to handle closing and
 * resizing the dialog.
 */
(function ($, Drupal) {
  Drupal.AjaxCommands.prototype.openIframe = function (ajax, response, status) {
    Drupal.AjaxCommands.prototype.openDialog(ajax, response, status);
    this.openIframe.initialResize(ajax.wrapper);
    this.openIframe.focusIframe(ajax.wrapper);
    this.openIframe.initMessageListener();
    Drupal.AjaxCommands.prototype.openIframe._scrollPosition = window.scrollY;
  };

  /**
   * Set the size of the dialog to fill most of the viewport.
   */
  Drupal.AjaxCommands.prototype.openIframe.initialResize = function (wrapper) {
    const dialog = $(`#${wrapper}`);
    const dialogHeight = window.innerHeight - 180;
    const dialogWidth = window.innerWidth - 180;
    dialog.dialog('option', 'height', dialogHeight);
    dialog.dialog('option', 'width', dialogWidth);
  };

  /**
   * Try to focus the window of the iframe.
   */
  Drupal.AjaxCommands.prototype.openIframe.focusIframe = function (wrapper) {
    try {
      const dialog = $(`#${wrapper}`);
      const iframe = $(dialog).find('iframe');
      $(iframe).on('load', function () {
        this.contentWindow.focus();
      });
    } catch (e) {
      // Empty.
    }
  };

  /**
   * Attaches a message event listener to the window.
   *
   * The loaded document in the iframe will redirect to a special route, which
   * uses postMessage to send a message to the parent. This means we can
   * trigger a rebuild of the layout, which in return will close the dialog.
   */
  Drupal.AjaxCommands.prototype.openIframe.initMessageListener = function () {
    if (this._isInitialized) {
      return;
    }

    this._isInitialized = true;

    window.addEventListener(
      'message',
      function (e) {
        if (e.data === 'LBIM_REDIRECT') {
          $('#edit-rebuild-layout').trigger('click');
        }
      },
      false,
    );
  };

  // Stores the last scroll position before rebuild happened.
  Drupal.AjaxCommands.prototype.openIframe._scrollPosition = 0;

  /**
   * Scrolls to the position before the layout rebuild was triggered.
   *
   * The scroll only happens if the delta between then and now is above a
   * certain threshold. This prevents unnecessary scrolling.
   */
  Drupal.AjaxCommands.prototype.scrollToBlock = function () {
    const scrollY = Drupal.AjaxCommands.prototype.openIframe._scrollPosition;
    if (scrollY) {
      window.setTimeout(function () {
        if (Math.abs(window.scrollY - scrollY) > 300) {
          window.scrollTo(0, scrollY);
        }
        Drupal.AjaxCommands.prototype.openIframe._scrollPosition = 0;
      }, 200);
    }
  };
})(jQuery, Drupal);
