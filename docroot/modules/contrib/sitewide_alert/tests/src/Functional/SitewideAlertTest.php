<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\sitewide_alert\Traits\SitewideAlertTestTrait;

/**
 * Defines a class for testing site-wide alert functionality.
 *
 * @group sitewide_alert
 */
final class SitewideAlertTest extends BrowserTestBase {

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
   * Tests visibility on admin pages.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAlertsNotShownOnAdminPages(): void {
    $random = $this->getRandomGenerator();
    $sentences = $random->sentences(10);
    $this->createSiteWideAlert([
      'message' => [
        'value' => $sentences,
      ],
    ]);

    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $this->drupalGet('/admin/config');
    $assert = $this->assertSession();
    $assert->elementNotExists('css', '[data-sitewide-alert]');

    \Drupal::configFactory()->getEditable('sitewide_alert.settings')->set('show_on_admin', TRUE)->save();
    $this->drupalGet('/admin/config');
    $assert->elementExists('css', '[data-sitewide-alert]');
  }

}
