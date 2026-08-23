<?php

namespace Drupal\Tests\webform_migrate\Kernel\Migrate\d7;

use Drupal\Core\Site\Settings;
use Drupal\Tests\migrate_drupal\Kernel\MigrateDrupalTestBase;

/**
 * Base class for Webform migration kernel tests.
 *
 * @requires module webform
 */
abstract class WebformMigrateTestBase extends MigrateDrupalTestBase {

  /**
   * Returns the drupal-relative path to the database fixture file.
   *
   * @return string
   *   The path to the database file.
   */
  abstract public function getDatabaseFixtureFilePath();

  /**
   * Returns the absolute path to the file system fixture directory.
   *
   * @return string
   *   The absolute path to the file system fixture directory.
   */
  abstract public function getFilesystemFixturePath();

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->loadFixture($this->getDatabaseFixtureFilePath());
    $module_handler = \Drupal::moduleHandler();

    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    if ($module_handler->moduleExists('node')) {
      $this->installEntitySchema('node');
      $this->installSchema('node', 'node_access');
    }
    if ($module_handler->moduleExists('comment')) {
      $this->installEntitySchema('comment');
      $this->installSchema('comment', 'comment_entity_statistics');
    }
    if ($module_handler->moduleExists('webform')) {
      $this->installEntitySchema('webform_submission');
      $this->installSchema('webform', 'webform');
    }
    // Webform node assumes that node.body field storage is always present.
    // Let's install all default configuration.
    $module_list = array_keys($module_handler->getModuleList());
    $this->installConfig($module_list);
  }

  /**
   * Sets the type of the node migration.
   *
   * @param bool $classic_node_migration
   *   Whether nodes should be migrated with the 'classic' way. If this is
   *   FALSE, and the current Drupal instance has the 'complete' migration, then
   *   the complete node migration will be used.
   */
  protected function setClassicNodeMigration(bool $classic_node_migration) {
    $current_method = Settings::get('migrate_node_migrate_type_classic', FALSE);

    if ($current_method !== $classic_node_migration) {
      $this->setSetting('migrate_node_migrate_type_classic', $classic_node_migration);
    }
  }

  /**
   * Executes migrations of the media source database.
   *
   * @param bool $classic_node_migration
   *   Whether the classic node migration has to be executed or not.
   */
  protected function executeWebformMigrations(bool $classic_node_migration = FALSE) {
    // The Drupal 8|9 entity revision migration causes a file not found
    // exception without properly migrated files. For this test, it is enough to
    // properly migrate the public files.
    $fs_fixture_path = $this->getFilesystemFixturePath();
    $file_migration = $this->getMigration('d7_file');
    $source = $file_migration->getSourceConfiguration();
    $source['constants']['source_base_path'] = $fs_fixture_path;
    $file_migration->set('source', $source);

    // Ignore errors of migrations that aren't provided by Webform Migrate.
    $this->startCollectingMessages();

    $this->executeMigration($file_migration);
    $this->executeMigrations([
      'd7_view_modes',
      'd7_field',
      'd7_node_type',
      'd7_field_instance',
      'd7_field_formatter_settings',
      'd7_field_instance_widget_settings',
      'd7_filter_format',
      'd7_user_role',
      'd7_user',
      $classic_node_migration ? 'd7_node' : 'd7_node_complete',
    ]);
    $this->stopCollectingMessages();

    $this->startCollectingMessages();
    $this->executeMigrations([
      'd7_webform',
    ]);
    $this->stopCollectingMessages();
    $this->assertEmpty($this->migrateMessages);

    $this->startCollectingMessages();
    $this->executeMigrations([
      'd7_webform_submission',
    ]);
    $this->stopCollectingMessages();
    $this->assertEmpty($this->migrateMessages);
  }

}
