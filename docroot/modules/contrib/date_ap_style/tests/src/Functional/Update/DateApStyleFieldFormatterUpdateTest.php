<?php

declare(strict_types=1);

namespace Drupal\Tests\date_ap_style\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests update path for date_ap_style field formatter configurations.
 */
class DateApStyleFieldFormatterUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    // Use relative path to our PHP database dump.
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/date_ap_style_pre_update.php.gz',
    ];
  }

  /**
   * Tests that field formatter configurations are properly updated.
   *
   * This test verifies that existing field formatter configurations
   * are correctly migrated when the module is updated.
   */
  public function testFieldFormatterConfigurationUpdate() {
    // Verify the initial state before updates.
    $this->assertConfigurationState('before');

    // Run the updates.
    $this->runUpdates();

    // Verify the state after updates.
    $this->assertConfigurationState('after');

    // Verify specific migration logic worked correctly.
    $this->assertConfigurationMigration();
  }

  /**
   * Assert configuration state before or after updates.
   */
  private function assertConfigurationState($state) {
    $config_factory = \Drupal::configFactory();

    if ($state === 'before') {
      // Before updates: old config should exist, new should not.
      $old_config = $config_factory->get('date_ap_style.dateapstylesettings');
      $new_config = $config_factory->get('date_ap_style.settings');

      $this->assertFalse($old_config->isNew(), 'Old configuration exists before update');
      $this->assertTrue($new_config->isNew(), 'New configuration does not exist before update');

      // Check that old config has integer values (our dump has mixed types)
      $use_today = $old_config->get('use_today');
      $this->assertTrue(is_int($use_today) || is_string($use_today), 'Old config should use string or integer values, not boolean');

      // Check for presence of invalid keys that should be cleaned up.
      $raw_data = $old_config->getRawData();
      $this->assertArrayHasKey('_core', $raw_data, 'Old config should have _core key before migration');
    }
    else {
      // After updates: old config should be gone, new should exist.
      $old_config = $config_factory->get('date_ap_style.dateapstylesettings');
      $new_config = $config_factory->get('date_ap_style.settings');

      $this->assertTrue($old_config->isNew(), 'Old configuration was removed after update');
      $this->assertFalse($new_config->isNew(), 'New configuration exists after update');

      // Check that new config has proper boolean values.
      $this->assertIsBool($new_config->get('use_today'), 'New config should use boolean values after migration');
      $this->assertIsBool($new_config->get('cap_today'), 'New config should use boolean values after migration');
    }
  }

  /**
   * Assert that the configuration migration worked correctly.
   */
  private function assertConfigurationMigration() {
    $config_factory = \Drupal::configFactory();

    // Test main configuration migration.
    $config = $config_factory->get('date_ap_style.settings');

    // Verify boolean fields are actual booleans, not strings.
    $boolean_fields = [
      'always_display_year',
      'use_today',
      'cap_today',
      'display_day',
      'display_time',
      'hide_date',
      'time_before_date',
      'display_noon_and_midnight',
      'capitalize_noon_and_midnight',
      'use_all_day',
      'month_only',
    ];

    foreach ($boolean_fields as $field) {
      $value = $config->get($field);
      $this->assertIsBool($value, "Field '{$field}' should be boolean, got: " . gettype($value));
    }

    // Verify invalid keys were removed.
    $raw_data = $config->getRawData();
    $this->assertArrayNotHasKey('_core', $raw_data, '_core key should be removed after migration');
    $this->assertArrayNotHasKey('date_ap_style_settings', $raw_data, 'date_ap_style_settings key should be removed after migration');

    // Test field formatter configuration migration.
    $this->assertFieldFormatterSettingsMigration();
  }

  /**
   * Assert that field formatter settings were migrated correctly.
   */
  private function assertFieldFormatterSettingsMigration() {
    $config_factory = \Drupal::configFactory();

    // Check Article content type field formatter.
    $article_display = $config_factory->get('core.entity_view_display.node.article.default');
    $article_date_settings = $article_display->get('content.field_article_date.settings');

    $this->assertNotEmpty($article_date_settings, 'Article date field should have settings configured');

    // Verify boolean conversion in field formatter settings.
    $boolean_settings = [
      'use_today',
      'cap_today',
      'display_day',
      'display_time',
      'time_before_date',
      'display_noon_and_midnight',
      'capitalize_noon_and_midnight',
      'use_all_day',
    ];

    foreach ($boolean_settings as $setting) {
      if (isset($article_date_settings[$setting])) {
        $this->assertIsBool($article_date_settings[$setting],
          "Article field setting '{$setting}' should be boolean, got: " . gettype($article_date_settings[$setting]));
      }
    }

    // Check Page content type field formatter.
    $page_display = $config_factory->get('core.entity_view_display.node.page.default');
    $page_time_settings = $page_display->get('content.field_time.settings');

    $this->assertNotEmpty($page_time_settings, 'Page time field should have settings configured');

    // Verify specific values were converted correctly.
    if (isset($page_time_settings['always_display_year'])) {
      $this->assertIsBool($page_time_settings['always_display_year'],
        'Page field always_display_year should be boolean after migration');
    }
  }

}
