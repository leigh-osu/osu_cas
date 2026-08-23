<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Tests the SitewideAlertManager service.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\SitewideAlertManager
 */
final class SitewideAlertManagerTest extends SitewideAlertKernelTestBase {

  /**
   * The sitewide alert manager.
   *
   * @var \Drupal\sitewide_alert\SitewideAlertManager
   */
  private $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpCurrentUser([], ['view published sitewide alert entities']);
    $this->manager = $this->container->get('sitewide_alert.sitewide_alert_manager');
  }

  /**
   * Tests that unpublished alerts are excluded from activeSitewideAlerts().
   *
   * @covers ::activeSitewideAlerts
   */
  public function testActiveSitewideAlertsExcludesUnpublished(): void {
    $this->createSiteWideAlert(['status' => 1]);
    $this->createSiteWideAlert(['status' => 0]);

    $alerts = $this->manager->activeSitewideAlerts();
    $this->assertCount(1, $alerts);
  }

  /**
   * Tests non-scheduled active alerts appear in activeVisibleSitewideAlerts().
   *
   * @covers ::activeVisibleSitewideAlerts
   */
  public function testActiveVisibleKeepsNonScheduledAlerts(): void {
    $this->createSiteWideAlert([
      'scheduled_alert' => FALSE,
    ]);

    $alerts = $this->manager->activeVisibleSitewideAlerts();
    $this->assertCount(1, $alerts);
  }

  /**
   * Tests scheduled alert within its time window appears.
   *
   * @covers ::activeVisibleSitewideAlerts
   */
  public function testActiveVisibleKeepsScheduledInWindow(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $alerts = $this->manager->activeVisibleSitewideAlerts();
    $this->assertCount(1, $alerts);
  }

  /**
   * Tests scheduled alert with future start is excluded.
   *
   * @covers ::activeVisibleSitewideAlerts
   */
  public function testActiveVisibleFiltersFutureScheduled(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+2 hours')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $alerts = $this->manager->activeVisibleSitewideAlerts();
    $this->assertCount(0, $alerts);
  }

  /**
   * Tests scheduled alert past its end time is excluded.
   *
   * @covers ::activeVisibleSitewideAlerts
   */
  public function testActiveVisibleExcludesExpiredScheduled(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-2 hours')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $alerts = $this->manager->activeVisibleSitewideAlerts();
    $this->assertCount(0, $alerts);
  }

  /**
   * Tests nextScheduledChange returns null with only non-scheduled alerts.
   *
   * @covers ::nextScheduledChange
   */
  public function testNextScheduledChangeNullWhenNoneScheduled(): void {
    $this->createSiteWideAlert([
      'scheduled_alert' => FALSE,
    ]);

    $this->assertNull($this->manager->nextScheduledChange());
  }

  /**
   * Tests nextScheduledChange returns end time of visible scheduled alert.
   *
   * @covers ::nextScheduledChange
   */
  public function testNextScheduledChangeReturnsExpiringTime(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $endTime = $now->modify('+1 hour');

    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $endTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $result = $this->manager->nextScheduledChange();
    $this->assertInstanceOf(DrupalDateTime::class, $result);
    $this->assertEquals($endTime->getTimestamp(), $result->getTimestamp());
  }

  /**
   * Tests nextScheduledChange returns start time of future scheduled alert.
   *
   * @covers ::nextScheduledChange
   */
  public function testNextScheduledChangeReturnsAppearingTime(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $startTime = $now->modify('+1 hour');

    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $startTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+2 hours')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $result = $this->manager->nextScheduledChange();
    $this->assertInstanceOf(DrupalDateTime::class, $result);
    $this->assertEquals($startTime->getTimestamp(), $result->getTimestamp());
  }

  /**
   * Tests nextScheduledChange returns the soonest of appearing vs expiring.
   *
   * @covers ::nextScheduledChange
   */
  public function testNextScheduledChangeReturnsSoonest(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    // Currently visible alert expiring in 2 hours.
    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+2 hours')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    // Future alert appearing in 1 hour (sooner than the 2-hour expiry).
    $futureStart = $now->modify('+1 hour');
    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $futureStart->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+3 hours')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $result = $this->manager->nextScheduledChange();
    $this->assertInstanceOf(DrupalDateTime::class, $result);
    $this->assertEquals($futureStart->getTimestamp(), $result->getTimestamp());
  }

}
