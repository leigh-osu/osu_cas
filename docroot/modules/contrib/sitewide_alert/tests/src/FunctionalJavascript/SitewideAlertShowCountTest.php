<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\sitewide_alert\Traits\SitewideAlertTestTrait;

/**
 * Defines a class for testing site-wide alert functionality.
 *
 * @group sitewide_alert
 */
final class SitewideAlertShowCountTest extends WebDriverTestBase {

  use SitewideAlertTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sitewide_alert', 'sitewide_alert_block', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createSiteWideAlert();
    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $this->drupalPlaceBlock('sitewide_alert_block');
  }

  /**
   * Tests alerts counter.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAlertsCount(): void {
    $this->drupalGet('<front>');
    $assert = $this->assertSession();
    $this->assertNotNull($assert->waitForElementVisible('css', '[data-uuid]', 1000));
    $assert->elementNotExists('css', '.alert-count');

    \Drupal::configFactory()->getEditable('sitewide_alert.settings')->set('show_count', TRUE)->save();
    $this->drupalGet('<front>');

    $this->createSiteWideAlert();
    $this->drupalGet('<front>');
    $this->assertNotNull($assert->waitForElementVisible('css', '.alert-count'));
    $assert->pageTextContains('1 of 2 alerts');
  }

  /**
   * Tests alerts js counter update.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testAlertCountUpdateOnDelete(): void {
    \Drupal::configFactory()->getEditable('sitewide_alert.settings')
      ->set('show_count', TRUE)
      ->set('refresh_interval', 1)
      ->save();

    $alert2 = $this->createSiteWideAlert();

    $this->drupalGet('<front>');
    $assert = $this->assertSession();
    $alert_count = $assert->waitForElementVisible('css', '.alert-count');
    $this->assertNotNull($alert_count);
    $assert->pageTextContains('1 of 2 alerts');

    $alert3 = $this->createSiteWideAlert();
    $alert3_selector = '[data-uuid="' . $alert3->uuid() . '"]';
    $this->assertNotNull($assert->waitForElementVisible('css', $alert3_selector, 3000));
    // Check alerts text updated.
    $assert->pageTextContains('1 of 3 alerts');

    $alert2_selector = '[data-uuid="' . $alert2->uuid() . '"]';
    $this->click("$alert2_selector .js-dismiss-button");
    $this->assertTrue($assert->waitForElementRemoved('css', $alert2_selector, 1000));

    // Check text updated again.
    $assert->pageTextContains('1 of 2 alerts');

    $this->drupalGet('<front>');
    $assert = $this->assertSession();
    $alert_count = $assert->waitForElementVisible('css', '.alert-count');
    $this->assertNotNull($alert_count);
    $assert->pageTextContains('1 of 2 alerts');
  }

}
