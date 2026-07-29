(function (Drupal, drupalSettings, once) {
  const sitewideAlertsSelector = '[data-sitewide-alert]';

  const shouldShowOnThisPage = (pages = [], negate = true) => {
    if (pages.length === 0) {
      return true;
    }

    let pagePathMatches = false;
    const currentPath = window.location.pathname;

    for (let i = 0; i < pages.length; i++) {
      const baseUrl = drupalSettings.path.baseUrl.slice(0, -1);
      const page = baseUrl + pages[i];
      // Check if we have to deal with a wild card.
      if (page.charAt(page.length - 1) === '*') {
        if (currentPath.startsWith(page.substring(0, page.length - 1))) {
          pagePathMatches = true;
          break;
        }
      } else if (page === currentPath) {
        pagePathMatches = true;
        break;
      }
    }

    return negate ? !pagePathMatches : pagePathMatches;
  };

  const alertWasDismissed = (alert) => {
    // Only dismissible alerts can be dismissed.
    if (!alert.dismissible) {
      return false;
    }

    if (!(`alert-dismissed-${alert.uuid}` in window.localStorage)) {
      return false;
    }

    const dismissedAtTimestamp = Number(
      window.localStorage.getItem(`alert-dismissed-${alert.uuid}`),
    );

    // If the visitor has already dismissed the alert, but we are supposed to ignore dismissals before a set date.
    return dismissedAtTimestamp >= alert.dismissalIgnoreBefore;
  };

  const updateLandmarkRole = () => {
    const roots = document.querySelectorAll(sitewideAlertsSelector);
    roots.forEach((root) => {
      const hasAlerts = root.querySelector('[data-uuid]') !== null;
      if (hasAlerts) {
        root.setAttribute('role', 'region');
        root.setAttribute('aria-label', Drupal.t('Site alerts'));
      } else {
        root.removeAttribute('role');
        root.removeAttribute('aria-label');
      }
    });
  };

  const updateAlertCount = () => {
    if (!drupalSettings.sitewideAlert.showCount) {
      return;
    }
    const roots = document.querySelectorAll(sitewideAlertsSelector);

    roots.forEach((root) => {
      let count = 0;
      const alerts = root.querySelectorAll('[data-uuid]');
      const totalCount = alerts.length;
      alerts.forEach((alert) => {
        count++;
        const alertCountContainer = alert.querySelector('.alert-count');
        if (!alertCountContainer) {
          return;
        }

        // @Todo Dispatch custom event here?
        alertCountContainer.innerHTML = Drupal.t(
          '@current_count of @total_count alerts',
          {
            '@current_count': count,
            '@total_count': totalCount,
          },
        );
      });
    });
  };

  const removeAlert = (alert) => {
    alert.dispatchEvent(
      new CustomEvent('sitewide-alert-removed', {
        bubbles: true,
        composed: true,
      }),
    );

    alert.remove();
    updateAlertCount();
    updateLandmarkRole();
  };

  const dismissAlert = (alert) => {
    window.localStorage.setItem(
      `alert-dismissed-${alert.uuid}`,
      String(Math.round(new Date().getTime() / 1000)),
    );
    document
      .querySelectorAll(`[data-uuid="${alert.uuid}"]`)
      .forEach((alert) => {
        alert.dispatchEvent(
          new CustomEvent('sitewide-alert-dismissed', {
            bubbles: true,
            composed: true,
          }),
        );
        removeAlert(alert);
      });
  };

  const buildAlertElement = (alert) => {
    const alertElement = document.createElement('div');
    alertElement.innerHTML = alert.renderedAlert;

    if (alert.dismissible) {
      const dismissButtons =
        alertElement.getElementsByClassName('js-dismiss-button');
      for (let i = 0; i < dismissButtons.length; i++) {
        dismissButtons[i].addEventListener('click', () => dismissAlert(alert));
      }
    }

    return alertElement.firstElementChild;
  };

  /**
   * Process server-side rendered alerts.
   *
   * This runs synchronously to:
   * 1. Check dismissal status in localStorage and remove dismissed alerts
   * 2. Attach dismiss button handlers to SSR alerts
   */
  const processSsrAlerts = () => {
    const roots = document.querySelectorAll(sitewideAlertsSelector);
    roots.forEach((root) => {
      const existingAlerts = root.querySelectorAll('[data-uuid]');
      existingAlerts.forEach((alertElement) => {
        const uuid = alertElement.dataset.uuid;
        const dismissalIgnoreBefore =
          parseInt(alertElement.dataset.dismissalIgnoreBefore, 10) || 0;
        const isDismissible = alertElement.dataset.dismissible === 'true';

        // Create alert data object for existing functions.
        const alertData = {
          uuid,
          dismissalIgnoreBefore,
          dismissible: isDismissible,
        };

        // Remove dismissed alerts.
        if (alertWasDismissed(alertData)) {
          removeAlert(alertElement);
          return;
        }

        // Attach dismiss handler if dismissible.
        if (isDismissible) {
          const dismissButtons =
            alertElement.getElementsByClassName('js-dismiss-button');
          for (let i = 0; i < dismissButtons.length; i++) {
            dismissButtons[i].addEventListener('click', () =>
              dismissAlert(alertData),
            );
          }
        }
      });
    });
  };

  const fetchAlerts = () => {
    const url = new URL(
      Drupal.url('sitewide_alert/load'),
      window.location.origin,
    );
    // Strip credentials from the URL to avoid "Request cannot be constructed
    // from a URL that includes credentials" errors with the Fetch API
    // (e.g. on sites using HTTP Basic Authentication).
    url.username = '';
    url.password = '';
    return fetch(url.toString())
      .then((res) => res.json())
      .then(
        (result) => result.sitewideAlerts,
        // Note: it's important to handle errors here
        // instead of a catch() block so that we don't swallow
        // exceptions from actual bugs in components.
        (error) => {
          console.error(error);
        },
      );
  };

  const removeStaleAlerts = (alerts) => {
    const roots = document.querySelectorAll(sitewideAlertsSelector);
    roots.forEach((root) => {
      const existingAlerts = root.querySelectorAll('[data-uuid]');

      // We have to convert and filter existing alerts based on newly fetched alerts.
      // This can be done by comparing uuids.
      // If the uuid can't be found in fetched alerts,
      // the alert with the same uuid should be removed.
      const alertsToBeRemoved = Array.from(existingAlerts).filter(
        (alert) => !alerts.includes(alert.getAttribute('data-uuid')),
      );

      alertsToBeRemoved.forEach((alert) => removeAlert(alert));
    });
  };

  const initAlerts = () => {
    const roots = document.querySelectorAll(sitewideAlertsSelector);
    // Fetch alerts and prepare rendering.
    fetchAlerts().then((alerts) => {
      removeStaleAlerts(alerts.map((alert) => alert.uuid));
      alerts.forEach((alert) => {
        // Check if alert should be dismissed.
        const dismissed = alertWasDismissed(alert);
        // Check if current page is one of the pages the alert should be shown on or not.
        const showOnThisPage = shouldShowOnThisPage(
          alert.showOnPages,
          alert.negateShowOnPages,
        );
        roots.forEach((root) => {
          // Check for existing alert element.
          const existingAlertElement = root.querySelector(
            `[data-uuid="${alert.uuid}"]`,
          );

          if (showOnThisPage && !dismissed) {
            if (
              existingAlertElement &&
              existingAlertElement.dataset.changed === alert.changed
            ) {
              return;
            }
            const renderableAlertElement = buildAlertElement(alert);
            // To prevent an alert from being rendered multiple times
            // replace the old alert with the new one when new alerts are being fetched.
            if (existingAlertElement) {
              root.replaceChild(renderableAlertElement, existingAlertElement);
            } else {
              root.appendChild(renderableAlertElement);
            }

            renderableAlertElement.dispatchEvent(
              new CustomEvent('sitewide-alert-rendered', {
                bubbles: true,
                composed: true,
              }),
            );

            Drupal.attachBehaviors(renderableAlertElement);
            return;
          }

          // Remove alert if it is on the page and should no longer be.
          if ((dismissed || !showOnThisPage) && existingAlertElement) {
            removeAlert(existingAlertElement);
          }
        });
        updateAlertCount();
      });
      updateLandmarkRole();
    });
  };

  /**
   * Check if window.history pushstate is available
   * @returns {boolean}
   */
  const supportsHistoryPushState = () => {
    return 'pushState' in window.history && window.history.pushState !== null;
  };

  /**
   * Check if window.history replaceState is available
   * @returns {boolean}
   */
  const supportsHistoryReplaceState = () => {
    return (
      'replaceState' in window.history && window.history.replaceState !== null
    );
  };

  /**
   * Trigger CustomEvent sitewidealerts.popstate.
   *
   * @param thisArg
   * @param argArray
   */
  const triggerHistoryEvent = (thisArg, argArray) => {
    const event = new CustomEvent('sitewidealerts.popstate', {
      detail: { state: thisArg, options: argArray },
    });
    window.dispatchEvent(event);
  };

  /**
   * Add Proxy to standard pushState function to fire CustomEvent.
   *
   * Nor history.pushState either history.replaceState will trigger a popstate event.
   * Therefor a proxy behavior is added to trigger a CustomEvent whenever the history is changed.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/API/History/pushState
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Window/popstate_event
   */
  const proxyPushState = () => {
    if (supportsHistoryPushState()) {
      window.history.pushState = new Proxy(window.history.pushState, {
        apply(target, thisArg, argArray) {
          // triggerEvent
          triggerHistoryEvent(thisArg, argArray);
          // execute original
          return target.apply(thisArg, argArray);
        },
      });
    }
    if (supportsHistoryReplaceState()) {
      window.history.replaceState = new Proxy(window.history.replaceState, {
        apply(target, thisArg, argArray) {
          // triggerEvent
          triggerHistoryEvent(thisArg, argArray);
          // execute original
          return target.apply(thisArg, argArray);
        },
      });
    }
  };

  function debounce(func, wait, immediate) {
    let timeout;
    return function (...args) {
      const context = this;
      const later = function () {
        timeout = null;
        if (!immediate) func.apply(context, args);
      };
      const callNow = immediate && !timeout;
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
      if (callNow) func.apply(context, args);
    };
  }

  /**
   * Reinitialize the alters on A: CustomEvent and B: Standard popstate.
   *
   * @see shouldShowOnThisPage
   * @see initAlerts
   */
  const historyListener = () => {
    const debounced = debounce(initAlerts, 2000);
    window.addEventListener('sitewidealerts.popstate', debounced);
    window.addEventListener('popstate', debounced);
  };

  Drupal.behaviors.sitewide_alert_init = {
    attach: (context) => {
      once('sitewide_alerts_init', 'html', context).forEach(() => {
        // Process any SSR alerts first (attach handlers, check dismissals).
        processSsrAlerts();
        updateLandmarkRole();
        // Skip initial fetch if alerts were rendered server-side.
        if (drupalSettings.sitewideAlert.serverSideRender !== true) {
          initAlerts();
        }
        proxyPushState();
        historyListener();
        if (drupalSettings.sitewideAlert.automaticRefresh === true) {
          const interval = setInterval(
            () => initAlerts(),
            drupalSettings.sitewideAlert.refreshInterval < 1000
              ? 1000
              : drupalSettings.sitewideAlert.refreshInterval,
          );
          // Clear interval if automatic refresh has been turned off.
          // Only do this if an interval has previously been set.
          if (!drupalSettings.sitewideAlert.automaticRefresh) {
            clearInterval(interval);
          }
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
