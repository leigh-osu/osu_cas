<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group sitewide_alert
 */
final class SitewideAlertLimitToPagesTest extends SitewideAlertKernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['content_translation', 'language'];

  /**
   * Tests empty value in limit_to_pages field.
   */
  public function testEmptyLimitValue(): void {
    $alert = $this->createSiteWideAlert();
    $pages = $alert->getPagesToShowOn();
    $this->assertCount(0, $pages);
  }

  /**
   * Tests simple value in limit_to_pages field.
   */
  public function testSimpleLimitValue(): void {
    $alert = $this->createSiteWideAlert(['limit_to_pages' => '/some/page']);
    $pages = $alert->getPagesToShowOn();
    $this->assertCount(1, $pages);
    $this->assertContains('/some/page', $pages);
  }

  /**
   * Tests several values in limit_to_pages field.
   */
  public function testBunchOfSimpleLimitValues() : void {
    $alert = $this->createSiteWideAlert([
      'limit_to_pages' => '
        /some/page
        /another/page
        /third/page
      ',
    ]);
    $pages = $alert->getPagesToShowOn();
    $this->assertCount(3, $pages);
    $this->assertContains('/some/page', $pages);
    $this->assertContains('/another/page', $pages);
    $this->assertContains('/third/page', $pages);
  }

  /**
   * Tests value with wildcard in limit_to_pages field.
   */
  public function testLimitValueWithWildcard() : void {
    $alert = $this->createSiteWideAlert(['limit_to_pages' => '/some/*']);
    $pages = $alert->getPagesToShowOn();
    $this->assertCount(1, $pages);
    $this->assertContains('/some/*', $pages);
  }

  /**
   * Tests value that starts not from '/' in limit_to_pages field.
   */
  public function testIncorrectLimitValue() : void {
    $alert = $this->createSiteWideAlert(['limit_to_pages' => 'invalid/path']);
    $pages = $alert->getPagesToShowOn();
    $this->assertCount(0, $pages);
  }

  /**
   * Tests adding prefix to limit_to_pages paths for translatable alerts.
   */
  public function testLimitToPagesOnTranslatableAlert(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->container->get('content_translation.manager')->setEnabled('sitewide_alert', 'sitewide_alert', TRUE);
    $this->container->get('kernel')->rebuildContainer();

    $alert = $this->createSiteWideAlert(['limit_to_pages' => '/some/page']);

    $pages = $alert->getPagesToShowOn();
    $this->assertCount(2, $pages);
    $this->assertContains('/some/page', $pages);
    $this->assertContains('/en/some/page', $pages);
  }

}
