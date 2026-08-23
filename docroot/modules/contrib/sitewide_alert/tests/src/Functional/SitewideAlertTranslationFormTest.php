<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\sitewide_alert\Traits\SitewideAlertTestTrait;

/**
 * Tests the sitewide alert translation form.
 *
 * @group sitewide_alert
 */
final class SitewideAlertTranslationFormTest extends BrowserTestBase {

  use SitewideAlertTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sitewide_alert',
    'content_translation',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to create and translate alerts.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add French language.
    $this->adminUser = $this->createUser([
      'administer sitewide alert entities',
      'add sitewide alert entities',
      'edit sitewide alert entities',
      'view published sitewide alert entities',
      'administer languages',
      'administer content translation',
      'create content translations',
      'update content translations',
      'translate any entity',
    ]);
    $this->drupalLogin($this->adminUser);

    // Add French language.
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm(['predefined_langcode' => 'fr'], 'Add language');

    // Enable translation for sitewide_alert.
    $this->drupalGet('admin/config/regional/content-language');
    $edit = [
      'entity_types[sitewide_alert]' => TRUE,
      'settings[sitewide_alert][sitewide_alert][translatable]' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');

    // Configure multiple alert styles so the style widget is visible.
    \Drupal::configFactory()
      ->getEditable('sitewide_alert.settings')
      ->set('alert_styles', "primary|Default\nwarning|Warning\ndanger|Danger")
      ->save();
  }

  /**
   * Tests that style value carries over to the translation form.
   */
  public function testStyleCarriesOverToTranslationForm(): void {
    // Create an alert with the 'warning' style.
    $alert = $this->createSiteWideAlert([
      'style' => 'warning',
    ]);

    // Navigate to the French translation add form.
    $this->drupalGet('admin/content/sitewide_alert/' . $alert->id() . '/translations/add/en/fr');
    $this->assertSession()->statusCodeEquals(200);

    // The style select should be pre-populated with the source language value.
    $this->assertSession()->fieldValueEquals('style', 'warning');
  }

  /**
   * Tests that style can be changed on the translation form.
   */
  public function testStyleCanBeChangedOnTranslationForm(): void {
    // Create an alert with the 'warning' style.
    $alert = $this->createSiteWideAlert([
      'style' => 'warning',
    ]);

    // Navigate to the French translation add form.
    $this->drupalGet('admin/content/sitewide_alert/' . $alert->id() . '/translations/add/en/fr');
    $this->assertSession()->statusCodeEquals(200);

    // Change the style to 'danger' and save.
    $this->submitForm([
      'style' => 'danger',
      'name[0][value]' => $alert->label(),
      'message[0][value]' => 'French alert message',
    ], 'Save');

    // Load the French translation and verify the style was saved.
    \Drupal::entityTypeManager()->getStorage('sitewide_alert')->resetCache();
    $alert = \Drupal::entityTypeManager()
      ->getStorage('sitewide_alert')
      ->load($alert->id());
    $translation = $alert->getTranslation('fr');
    $this->assertEquals('danger', $translation->get('style')->value);
  }

}
