<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Language\LanguageInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\sitewide_alert\SitewideAlertManager;

// cspell:ignore d'essai

/**
 * Defines a class for testing the sitewide alert entity.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\Entity\SitewideAlert
 */
final class SitewideAlertEntityTest extends SitewideAlertKernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['content_translation', 'language'];

  /**
   * The sitewide alert manager.
   *
   * @var \Drupal\sitewide_alert\SitewideAlertManager
   */
  private SitewideAlertManager $sitewideAlertManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->container->get('content_translation.manager')->setEnabled('sitewide_alert', 'sitewide_alert', TRUE);

    // Rebuild container to make sure the sitewide alert manager is available.
    $this->container->get('kernel')->rebuildContainer();

    $this->sitewideAlertManager = $this->container->get('sitewide_alert.sitewide_alert_manager');

  }

  /**
   * Covers ::isPublished.
   *
   * @covers ::isPublished
   */
  public function testIsPublished(): void {
    $alert = $this->createSiteWideAlert();
    $this->assertTrue($alert->isPublished());

    $alert = $this->createSiteWideAlert([
      'status' => FALSE,
    ]);
    $this->assertFalse($alert->isPublished());
  }

  /**
   * Test basic crud.
   *
   * Tests basic entity crud.
   */
  public function testEntityCrud(): void {
    $name = $this->randomMachineName();
    $alert = $this->createSiteWideAlert([
      'name' => $name,
    ]);
    \Drupal::entityTypeManager()->getStorage('sitewide_alert')->loadUnchanged($alert->id());
    $this->assertEquals($name, $alert->label());
  }

  /**
   * Tests creating and retrieving alerts in multiple languages.
   */
  public function testAlertDefaultLanguage(): void {
    $alert = $this->createSiteWideAlert([
      'langcode' => 'fr',
    ]);
    $this->assertEquals('fr', $alert->language()->getId());
    $this->assertCount(0, $this->sitewideAlertManager->activeSitewideAlerts());

    \Drupal::service('language.default')->set(\Drupal::languageManager()->getLanguage('fr'));
    \Drupal::languageManager()->reset();

    $this->assertCount(1, $this->sitewideAlertManager->activeSitewideAlerts());

    $alert = $this->createSiteWideAlert([
      'langcode' => 'it',
    ]);
    $this->assertCount(1, $this->sitewideAlertManager->activeSitewideAlerts());

    \Drupal::service('language.default')->set(\Drupal::languageManager()->getLanguage(LanguageInterface::LANGCODE_NOT_SPECIFIED));
    \Drupal::languageManager()->reset();

    $this->assertCount(0, $this->sitewideAlertManager->activeSitewideAlerts());
  }

  /**
   * Tests alert translations and default language.
   */
  public function testAlertTranslations(): void {
    $this->createSiteWideAlert(['message' => 'Message test']);

    $alert = $this->createSiteWideAlert(['message' => 'Message test']);
    $this->assertEquals('en', $alert->language()->getId());
    $this->assertCount(2, $this->sitewideAlertManager->activeSitewideAlerts());

    $translation = $alert->addTranslation('fr', ['message' => "message d'essai"] + $alert->toArray());
    $translation->save();
    // There should still only be 2 alerts after adding a translation.
    $this->assertCount(2, $this->sitewideAlertManager->activeSitewideAlerts());

    // Let's create another alert in another default language.
    $alert = $this->createSiteWideAlert([
      'langcode' => 'fr',
      'message' => "message d'essai",
    ]);

    \Drupal::service('language.default')->set(\Drupal::languageManager()->getLanguage('fr'));
    \Drupal::languageManager()->reset();

    $alerts = $this->sitewideAlertManager->activeSitewideAlerts();

    $this->assertCount(2, $alerts);
    foreach ($alerts as $alert) {
      // Should not be the first alert as it only exists in English.
      $this->assertNotEquals(1, $alert->id());
      $this->assertEquals("message d'essai", $alert->get('message')->value);
    }
  }

  /**
   * Tests that show_untranslated returns alerts in all languages.
   */
  public function testShowUntranslatedReturnsAllAlerts(): void {
    // Create alerts in different languages.
    $this->createSiteWideAlert(['langcode' => 'en']);
    $this->createSiteWideAlert(['langcode' => 'fr']);
    $this->createSiteWideAlert(['langcode' => 'it']);

    // Default behavior (show_untranslated = FALSE) should only return English.
    $this->assertCount(1, $this->sitewideAlertManager->activeSitewideAlerts());

    // Enable show_untranslated.
    \Drupal::configFactory()
      ->getEditable('sitewide_alert.settings')
      ->set('show_untranslated', TRUE)
      ->save();

    // Rebuild the manager to pick up new config.
    $this->sitewideAlertManager = $this->container->get('sitewide_alert.sitewide_alert_manager');

    $this->assertCount(3, $this->sitewideAlertManager->activeSitewideAlerts());
  }

  /**
   * Tests show_untranslated disabled filters by current language.
   */
  public function testShowUntranslatedDisabledFiltersByLanguage(): void {
    $this->createSiteWideAlert(['langcode' => 'en']);
    $this->createSiteWideAlert(['langcode' => 'fr']);

    // Default: show_untranslated is FALSE, current language is English.
    $alerts = $this->sitewideAlertManager->activeSitewideAlerts();
    $this->assertCount(1, $alerts);

    // Switch to French.
    \Drupal::service('language.default')->set(\Drupal::languageManager()->getLanguage('fr'));
    \Drupal::languageManager()->reset();

    $alerts = $this->sitewideAlertManager->activeSitewideAlerts();
    $this->assertCount(1, $alerts);
    $alert = reset($alerts);
    $this->assertEquals('fr', $alert->language()->getId());
  }

  /**
   * Tests show_untranslated with translations present.
   */
  public function testShowUntranslatedWithTranslations(): void {
    // Create an English alert with a French translation.
    $alert = $this->createSiteWideAlert(['langcode' => 'en']);
    $translation = $alert->addTranslation('fr', ['message' => "message d'essai"] + $alert->toArray());
    $translation->save();

    // Create a French-only alert (no English version).
    $this->createSiteWideAlert(['langcode' => 'fr']);

    // With show_untranslated disabled, English context should return only
    // the English alert (the French-only one is excluded).
    $this->assertCount(1, $this->sitewideAlertManager->activeSitewideAlerts());

    // Enable show_untranslated.
    \Drupal::configFactory()
      ->getEditable('sitewide_alert.settings')
      ->set('show_untranslated', TRUE)
      ->save();

    $this->sitewideAlertManager = $this->container->get('sitewide_alert.sitewide_alert_manager');

    // Should return both alerts.
    $this->assertCount(2, $this->sitewideAlertManager->activeSitewideAlerts());
  }

  /**
   * Tests show_untranslated works through activeVisibleSitewideAlerts.
   *
   * The renderer calls activeVisibleSitewideAlerts (not activeSitewideAlerts
   * directly), so we verify the setting propagates through the scheduling
   * filter layer.
   */
  public function testShowUntranslatedWithActiveVisibleAlerts(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    // Create a scheduled English alert that is currently visible.
    $this->createSiteWideAlert([
      'langcode' => 'en',
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    // Create a French-only alert (non-scheduled, always visible).
    $this->createSiteWideAlert(['langcode' => 'fr']);

    // Default: only the English alert should appear.
    $this->assertCount(1, $this->sitewideAlertManager->activeVisibleSitewideAlerts());

    // Enable show_untranslated.
    \Drupal::configFactory()
      ->getEditable('sitewide_alert.settings')
      ->set('show_untranslated', TRUE)
      ->save();

    $this->sitewideAlertManager = $this->container->get('sitewide_alert.sitewide_alert_manager');

    // Both alerts should appear.
    $this->assertCount(2, $this->sitewideAlertManager->activeVisibleSitewideAlerts());
  }

  /**
   * Tests preSave clears scheduled date when scheduled is unchecked.
   *
   * @covers ::preSave
   */
  public function testPreSaveClearsScheduledDateWhenUnchecked(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    // Confirm dates are set.
    $this->assertNotNull($alert->getScheduledStartDateTime());
    $this->assertNotNull($alert->getScheduledEndDateTime());

    // Uncheck scheduled and save.
    $alert->set('scheduled_alert', FALSE);
    $alert->save();

    // Reload entity from storage.
    $reloaded = \Drupal::entityTypeManager()
      ->getStorage('sitewide_alert')
      ->loadUnchanged($alert->id());

    $this->assertNull($reloaded->getScheduledStartDateTime());
    $this->assertNull($reloaded->getScheduledEndDateTime());
  }

  /**
   * Tests isScheduledToShowAt returns TRUE at exact start time.
   *
   * @covers ::isScheduledToShowAt
   */
  public function testIsScheduledToShowAtExactStartTime(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $startTime = $now->modify('+1 hour');
    $endTime = $now->modify('+2 hours');

    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $startTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $endTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $dateTime = new \DateTime('@' . $startTime->getTimestamp());
    $this->assertTrue($alert->isScheduledToShowAt($dateTime));
  }

  /**
   * Tests isScheduledToShowAt returns FALSE at exact end time.
   *
   * End time boundary is exclusive (<).
   *
   * @covers ::isScheduledToShowAt
   */
  public function testIsScheduledToShowAtExactEndTime(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $startTime = $now->modify('-2 hours');
    $endTime = $now->modify('+1 hour');

    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $startTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $endTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $dateTime = new \DateTime('@' . $endTime->getTimestamp());
    $this->assertFalse($alert->isScheduledToShowAt($dateTime));
  }

  /**
   * Tests isScheduledToShowAt returns FALSE before start.
   *
   * @covers ::isScheduledToShowAt
   */
  public function testIsScheduledToShowAtBeforeStart(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $startTime = $now->modify('+1 hour');
    $endTime = $now->modify('+2 hours');

    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $startTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $endTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $dateTime = new \DateTime('@' . $now->getTimestamp());
    $this->assertFalse($alert->isScheduledToShowAt($dateTime));
  }

  /**
   * Tests isScheduledToShowAt returns FALSE after end.
   *
   * @covers ::isScheduledToShowAt
   */
  public function testIsScheduledToShowAtAfterEnd(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $startTime = $now->modify('-2 hours');
    $endTime = $now->modify('-1 hour');

    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $startTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $endTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $dateTime = new \DateTime('@' . $now->getTimestamp());
    $this->assertFalse($alert->isScheduledToShowAt($dateTime));
  }

  /**
   * Tests isScheduledToShowAt returns TRUE for non-scheduled alerts.
   *
   * @covers ::isScheduledToShowAt
   */
  public function testIsScheduledToShowAtNotScheduledReturnsTrue(): void {
    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => FALSE,
    ]);

    $dateTime = new \DateTime();
    $this->assertTrue($alert->isScheduledToShowAt($dateTime));
  }

  /**
   * Tests getScheduledEndDateTime().
   *
   * @covers ::getScheduledEndDateTime
   *
   * @throws \Exception
   */
  public function testGetScheduledEndTime(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+7 days')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);
    $end_date = $alert->getScheduledEndDateTime();
    $this->assertInstanceOf(DrupalDateTime::class, $end_date);
    $this->assertEquals($now->modify('+7 days')->getTimestamp(), $end_date->getTimestamp());

    // Test for NULL date.
    $alert = $this->createSiteWideAlert();
    $this->assertNull($alert->getScheduledEndDateTime());
  }

  /**
   * Tests getScheduledStartDateTime().
   *
   * @covers ::getScheduledStartDateTime
   *
   * @throws \Exception
   */
  public function testGetScheduledStartTime(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+7 days')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);
    $start_date = $alert->getScheduledStartDateTime();
    $this->assertInstanceOf(DrupalDateTime::class, $start_date);
    $this->assertEquals($now->getTimestamp(), $start_date->getTimestamp());

    // Test for NULL date.
    $alert = $this->createSiteWideAlert();
    $this->assertNull($alert->getScheduledStartDateTime());
  }

}
