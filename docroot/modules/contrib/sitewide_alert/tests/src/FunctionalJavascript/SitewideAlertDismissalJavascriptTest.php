<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\sitewide_alert\Traits\SitewideAlertTestTrait;

/**
 * Tests JavaScript dismissal behavior for sitewide alerts.
 *
 * @group sitewide_alert
 */
final class SitewideAlertDismissalJavascriptTest extends WebDriverTestBase {

  use SitewideAlertTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sitewide_alert'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that a non-dismissible alert ignores a stale localStorage entry.
   *
   * Regression test: if an alert UUID exists in localStorage (from when the
   * alert was previously dismissible), changing it to non-dismissible must
   * not cause alertWasDismissed() to hide it during the client-side fetch.
   */
  public function testNonDismissibleAlertIgnoresLocalStorageDismissal(): void {
    $alert = $this->createSiteWideAlert([
      'dismissible' => TRUE,
      'message' => [
        'value' => 'Alert that becomes non-dismissible',
        'format' => 'plain_text',
      ],
    ]);

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));

    // Visit page and plant a dismissal entry in localStorage for this alert.
    $this->drupalGet('<front>');
    $localStorageKey = 'alert-dismissed-' . $alert->uuid();
    $this->getSession()->evaluateScript(
      "localStorage.setItem('$localStorageKey', String(Math.round(Date.now() / 1000)))"
    );

    // Change the alert to non-dismissible.
    $alert->set('dismissible', FALSE)->save();

    // Reload. initAlerts() fetches JSON where dismissible=false and calls
    // alertWasDismissed(). The guard must return false, keeping the alert.
    $this->drupalGet('<front>');

    $alertSelector = '[data-uuid="' . $alert->uuid() . '"]';
    $this->assertSession()->waitForElementVisible('css', $alertSelector);
    $this->assertNotNull(
      $this->getSession()->getPage()->find('css', $alertSelector),
      'Non-dismissible alert must be visible even when its UUID is in localStorage'
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->getSession()->isStarted()) {
      $this->getSession()->evaluateScript('localStorage.clear()');
    }
    parent::tearDown();
  }

}
