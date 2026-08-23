<?php

declare(strict_types=1);

namespace Drupal\Tests\date_ap_style\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Date AP Style settings form.
 */
class DateApStyleSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['date_ap_style'];

  /**
   * A user with permission to administer date ap style settings.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a user with admin permissions.
    $this->adminUser = $this->drupalCreateUser([
      'administer ap style settings',
      'access administration pages',
    ]);
  }

  /**
   * Tests that the settings form loads and displays all expected fields.
   */
  public function testSettingsFormDisplay(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/regional/date-ap-style');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the page title is correct.
    $this->assertSession()->pageTextContains('AP style date display settings');

    // Check that all expected form fields are present.
    $this->assertSession()->fieldExists('always_display_year');
    $this->assertSession()->fieldExists('use_today');
    $this->assertSession()->fieldExists('cap_today');
    $this->assertSession()->fieldExists('display_day');
    $this->assertSession()->fieldExists('display_time');
    $this->assertSession()->fieldExists('hide_date');
    $this->assertSession()->fieldExists('time_before_date');
    $this->assertSession()->fieldExists('display_noon_and_midnight');
    $this->assertSession()->fieldExists('capitalize_noon_and_midnight');
    $this->assertSession()->fieldExists('use_all_day');
    $this->assertSession()->fieldExists('month_only');
    $this->assertSession()->fieldExists('separator');
    $this->assertSession()->fieldExists('timezone');

    // Check that separator field has the correct options.
    $this->assertSession()->optionExists('separator', 'endash');
    $this->assertSession()->optionExists('separator', 'to');
    $this->assertSession()->optionExists('separator', 'hyphen');

    // Check that timezone field has default option.
    $this->assertSession()->optionExists('timezone', '');
  }

  /**
   * Tests that the settings form can be submitted and saves values correctly.
   */
  public function testSettingsFormSubmission(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/regional/date-ap-style');

    // Submit the form with specific values.
    $edit = [
      'always_display_year' => TRUE,
      'use_today' => TRUE,
      'cap_today' => TRUE,
      'display_day' => TRUE,
      'display_time' => TRUE,
      'hide_date' => FALSE,
      'time_before_date' => TRUE,
      'display_noon_and_midnight' => TRUE,
      'capitalize_noon_and_midnight' => TRUE,
      'use_all_day' => TRUE,
      'month_only' => FALSE,
      'separator' => 'endash',
      'timezone' => 'America/New_York',
    ];

    $this->submitForm($edit, 'Save configuration');

    // Check that the form was saved successfully.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify that the values were saved correctly.
    $config = $this->config('date_ap_style.settings');
    $this->assertEquals(TRUE, $config->get('always_display_year'));
    $this->assertEquals(TRUE, $config->get('use_today'));
    $this->assertEquals(TRUE, $config->get('cap_today'));
    $this->assertEquals(TRUE, $config->get('display_day'));
    $this->assertEquals(TRUE, $config->get('display_time'));
    $this->assertEquals(FALSE, $config->get('hide_date'));
    $this->assertEquals(TRUE, $config->get('time_before_date'));
    $this->assertEquals(TRUE, $config->get('display_noon_and_midnight'));
    $this->assertEquals(TRUE, $config->get('capitalize_noon_and_midnight'));
    $this->assertEquals(TRUE, $config->get('use_all_day'));
    $this->assertEquals(FALSE, $config->get('month_only'));
    $this->assertEquals('endash', $config->get('separator'));
    $this->assertEquals('America/New_York', $config->get('timezone'));
  }

  /**
   * Tests that the form shows default values correctly.
   */
  public function testSettingsFormDefaults(): void {
    $this->drupalLogin($this->adminUser);

    // Set some specific config values.
    $this->config('date_ap_style.settings')
      ->set('always_display_year', TRUE)
      ->set('separator', 'endash')
      ->set('timezone', 'America/Chicago')
      ->save();

    $this->drupalGet('admin/config/regional/date-ap-style');

    // Check that the form shows the saved values.
    $this->assertSession()->checkboxChecked('always_display_year');
    $this->assertSession()->fieldValueEquals('separator', 'endash');
    $this->assertSession()->fieldValueEquals('timezone', 'America/Chicago');
  }

  /**
   * Tests form access permissions.
   */
  public function testSettingsFormAccess(): void {
    // Test that anonymous users cannot access the form.
    $this->drupalGet('admin/config/regional/date-ap-style');
    $this->assertSession()->statusCodeEquals(403);

    // Test that users without proper permissions cannot access the form.
    $regularUser = $this->drupalCreateUser();
    $this->drupalLogin($regularUser);
    $this->drupalGet('admin/config/regional/date-ap-style');
    $this->assertSession()->statusCodeEquals(403);

    // Test that admin users can access the form.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/regional/date-ap-style');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests form validation and error handling.
   */
  public function testSettingsFormValidation(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/regional/date-ap-style');

    // The form should accept all valid combinations since it's mostly
    // checkboxes and select fields with predefined options. This test ensures
    // the form structure is valid and can be submitted without validation
    // errors.
    $edit = [
      'separator' => 'to',
      'timezone' => '',
    ];

    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

}
