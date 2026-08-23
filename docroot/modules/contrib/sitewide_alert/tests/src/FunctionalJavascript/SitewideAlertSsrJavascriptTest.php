<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\sitewide_alert\Traits\SitewideAlertTestTrait;

/**
 * Tests JavaScript behavior for server-side rendered alerts.
 *
 * @group sitewide_alert
 */
final class SitewideAlertSsrJavascriptTest extends WebDriverTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable SSR.
    $this->config('sitewide_alert.settings')
      ->set('server_side_render', TRUE)
      ->save();
  }

  /**
   * Tests that dismissing an SSR alert stores dismissal in localStorage.
   */
  public function testSsrAlertDismissStoresInLocalStorage(): void {
    $alert = $this->createSiteWideAlert([
      'dismissible' => TRUE,
      'message' => [
        'value' => 'Dismissible SSR Alert',
        'format' => 'plain_text',
      ],
    ]);

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));
    $this->drupalGet('<front>');

    $page = $this->getSession()->getPage();
    $assertSession = $this->assertSession();

    // Wait for the alert to be visible.
    $alertSelector = '[data-uuid="' . $alert->uuid() . '"]';
    $assertSession->waitForElementVisible('css', $alertSelector);

    // Verify alert is present.
    $alertElement = $page->find('css', $alertSelector);
    $this->assertNotNull($alertElement, 'SSR alert should be present on page');

    // Click the dismiss button.
    $dismissButton = $alertElement->find('css', '.js-dismiss-button');
    $this->assertNotNull($dismissButton, 'Dismiss button should exist');
    $dismissButton->click();

    // Wait for alert to be removed from DOM.
    $assertSession->assertNoElementAfterWait('css', $alertSelector);

    // Verify localStorage has the dismissal recorded.
    $localStorageKey = 'alert-dismissed-' . $alert->uuid();
    $storedValue = $this->getSession()->evaluateScript(
      "return localStorage.getItem('$localStorageKey')"
    );
    $this->assertNotNull($storedValue, 'Dismissal should be stored in localStorage');
    $this->assertIsNumeric($storedValue, 'Stored value should be a timestamp');
  }

  /**
   * Tests that previously dismissed SSR alerts are removed on page load.
   */
  public function testSsrAlertRemovedIfPreviouslyDismissed(): void {
    $alert = $this->createSiteWideAlert([
      'dismissible' => TRUE,
      'message' => [
        'value' => 'Previously Dismissed Alert',
        'format' => 'plain_text',
      ],
    ]);

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));

    // Set localStorage dismissal before visiting the page.
    $this->drupalGet('<front>');
    $localStorageKey = 'alert-dismissed-' . $alert->uuid();
    $dismissalTimestamp = time();
    $this->getSession()->evaluateScript(
      "localStorage.setItem('$localStorageKey', '$dismissalTimestamp')"
    );

    // Reload the page - the SSR alert should be removed by JS.
    $this->drupalGet('<front>');

    $page = $this->getSession()->getPage();
    $assertSession = $this->assertSession();

    // Wait for JS to process.
    $assertSession->waitForElement('css', '[data-sitewide-alert]');

    // Give JS time to process SSR alerts and remove dismissed ones.
    $this->assertJsCondition(
      "document.querySelector('[data-uuid=\"" . $alert->uuid() . "\"]') === null",
      5000,
      'Dismissed SSR alert should be removed by JavaScript'
    );
  }

  /**
   * Tests that multiple SSR alerts can have dismiss handlers attached.
   */
  public function testMultipleSsrAlertsDismissIndependently(): void {
    $alert1 = $this->createSiteWideAlert([
      'dismissible' => TRUE,
      'name' => 'Alert One',
      'message' => [
        'value' => 'First dismissible alert',
        'format' => 'plain_text',
      ],
    ]);

    $alert2 = $this->createSiteWideAlert([
      'dismissible' => TRUE,
      'name' => 'Alert Two',
      'message' => [
        'value' => 'Second dismissible alert',
        'format' => 'plain_text',
      ],
    ]);

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));
    $this->drupalGet('<front>');

    $page = $this->getSession()->getPage();
    $assertSession = $this->assertSession();

    $alert1Selector = '[data-uuid="' . $alert1->uuid() . '"]';
    $alert2Selector = '[data-uuid="' . $alert2->uuid() . '"]';

    // Wait for both alerts to be visible.
    $assertSession->waitForElementVisible('css', $alert1Selector);
    $assertSession->waitForElementVisible('css', $alert2Selector);

    // Dismiss only the first alert.
    $alert1Element = $page->find('css', $alert1Selector);
    $dismissButton1 = $alert1Element->find('css', '.js-dismiss-button');
    $dismissButton1->click();

    // Wait for first alert to be removed.
    $assertSession->assertNoElementAfterWait('css', $alert1Selector);

    // Second alert should still be present.
    $alert2Element = $page->find('css', $alert2Selector);
    $this->assertNotNull($alert2Element, 'Second alert should still be visible');

    // Verify only first alert is in localStorage.
    $stored1 = $this->getSession()->evaluateScript(
      "return localStorage.getItem('alert-dismissed-" . $alert1->uuid() . "')"
    );
    $stored2 = $this->getSession()->evaluateScript(
      "return localStorage.getItem('alert-dismissed-" . $alert2->uuid() . "')"
    );

    $this->assertNotNull($stored1, 'First alert dismissal should be stored');
    $this->assertNull($stored2, 'Second alert should not be dismissed');
  }

  /**
   * Tests that non-dismissible SSR alerts don't have dismiss functionality.
   */
  public function testNonDismissibleSsrAlertHasNoButton(): void {
    $alert = $this->createSiteWideAlert([
      'dismissible' => FALSE,
      'message' => [
        'value' => 'Non-dismissible alert',
        'format' => 'plain_text',
      ],
    ]);

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));
    $this->drupalGet('<front>');

    $page = $this->getSession()->getPage();
    $assertSession = $this->assertSession();

    $alertSelector = '[data-uuid="' . $alert->uuid() . '"]';
    $assertSession->waitForElementVisible('css', $alertSelector);

    $alertElement = $page->find('css', $alertSelector);
    $this->assertNotNull($alertElement, 'Alert should be present');

    // Should not have a dismiss button.
    $dismissButton = $alertElement->find('css', '.js-dismiss-button');
    $this->assertNull($dismissButton, 'Non-dismissible alert should not have dismiss button');

    // Verify data-dismissible attribute is false.
    $this->assertEquals('false', $alertElement->getAttribute('data-dismissible'));
  }

  /**
   * Tests that a non-dismissible SSR alert ignores a stale localStorage entry.
   *
   * Regression test: if an alert UUID exists in localStorage (from when it was
   * previously dismissible), changing it to non-dismissible must not cause
   * processSsrAlerts() to remove it.
   */
  public function testNonDismissibleSsrAlertIgnoresLocalStorageDismissal(): void {
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

    // Reload. processSsrAlerts() reads data-dismissible="false" from the DOM
    // and must not remove the alert despite the localStorage entry.
    $this->drupalGet('<front>');

    $alertSelector = '[data-uuid="' . $alert->uuid() . '"]';
    $this->assertSession()->waitForElementVisible('css', $alertSelector);
    $this->assertNotNull(
      $this->getSession()->getPage()->find('css', $alertSelector),
      'Non-dismissible SSR alert should remain visible even with a stale localStorage dismissal entry'
    );
  }

  /**
   * Tests that dismissal-ignore-before is respected.
   */
  public function testDismissalIgnoreBeforeIsRespected(): void {
    // Create alert with dismissal-ignore-before set to future time.
    $futureTimestamp = time() + 3600;
    $alert = $this->createSiteWideAlert([
      'dismissible' => TRUE,
      'dismissible_ignore_before_time' => $futureTimestamp,
      'message' => [
        'value' => 'Alert with ignore-before',
        'format' => 'plain_text',
      ],
    ]);

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));

    // Set an old dismissal timestamp (before ignore-before time).
    $this->drupalGet('<front>');
    $localStorageKey = 'alert-dismissed-' . $alert->uuid();
    $oldDismissalTimestamp = time() - 3600;
    $this->getSession()->evaluateScript(
      "localStorage.setItem('$localStorageKey', '$oldDismissalTimestamp')"
    );

    // Reload - alert should still show because dismissal is before ignore time.
    $this->drupalGet('<front>');

    $assertSession = $this->assertSession();
    $alertSelector = '[data-uuid="' . $alert->uuid() . '"]';

    // Alert should still be visible since old dismissal is ignored.
    $assertSession->waitForElementVisible('css', $alertSelector);
    $alertElement = $this->getSession()->getPage()->find('css', $alertSelector);
    $this->assertNotNull($alertElement, 'Alert should be visible when dismissal is before ignore-before time');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clear localStorage between tests.
    if ($this->getSession()->isStarted()) {
      $this->getSession()->evaluateScript('localStorage.clear()');
    }
    parent::tearDown();
  }

}
