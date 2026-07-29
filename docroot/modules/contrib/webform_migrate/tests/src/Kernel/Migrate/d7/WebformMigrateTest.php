<?php

namespace Drupal\Tests\webform_migrate\Kernel\Migrate\d7;

use Drupal\Tests\webform_migrate\Traits\WebformMigrateAssertionsTrait;

/**
 * Tests webform migrations.
 *
 * @group webform_migrate
 * @requires module webform
 */
class WebformMigrateTest extends WebformMigrateTestBase {

  use WebformMigrateAssertionsTrait;

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'editor',
    'field',
    'file',
    'filter',
    'migrate',
    'migrate_drupal',
    'node',
    'options',
    'system',
    'text',
    'user',
    'webform',
    'webform_migrate',
    'webform_node',
  ];

  /**
   * Returns the drupal-relative path to the database fixture file.
   *
   * @return string
   *   The path to the database file.
   */
  public function getDatabaseFixtureFilePath() {
    return \Drupal::service('extension.list.module')->getPath('webform_migrate') . '/tests/fixtures/drupal7_webform.php';
  }

  /**
   * Returns the absolute path to the file system fixture directory.
   *
   * @return string
   *   The absolute path to the file system fixture directory.
   */
  public function getFilesystemFixturePath() {
    return implode(DIRECTORY_SEPARATOR, [
      DRUPAL_ROOT,
      \Drupal::service('extension.list.module')->getPath('webform_migrate'),
      'tests',
      'fixtures',
      'files',
    ]);
  }

  /**
   * Tests the migration of webforms and webform submissions.
   *
   * @dataProvider providerWebformMigrations
   */
  public function testWebformMigrations(bool $classic_node_migration) {
    $this->setClassicNodeMigration($classic_node_migration);

    // Execute the relevant migrations.
    $this->executeWebformMigrations($classic_node_migration);

    // Check the forms.
    $this->assertWebform3Values();
    $this->assertWebform4Values();
    $this->assertWebform5Values();

    // Check the form submissions. There must be 10.
    $this->assertWebformSubmission1Values();
    $this->assertWebformSubmission2Values();
    $this->assertWebformSubmission3Values();
    $this->assertWebformSubmission4Values();
    $this->assertWebformSubmission5Values();
    $this->assertWebformSubmission6Values();
    $this->assertWebformSubmission7Values();
    $this->assertWebformSubmission8Values();
    $this->assertWebformSubmission9Values();
    $this->assertWebformSubmission10Values();
  }

  /**
   * Data provider for ::testWebformMigrations().
   *
   * @return array
   *   The test cases.
   */
  public static function providerWebformMigrations() {
    $test_cases = [
      'Classic node migration' => [
        'Classic node migration' => TRUE,
      ],
      'Complete node migration' => [
        'Classic node migration' => FALSE,
      ],
    ];

    // Drupal 8.8.x only has 'classic' node migrations.
    // @see https://www.drupal.org/node/3105503
    if (version_compare(\Drupal::VERSION, '8.9', '<')) {
      $test_cases = array_filter($test_cases, function ($test_case) {
        return $test_case['Classic node migration'];
      });
    }

    return $test_cases;
  }

}
