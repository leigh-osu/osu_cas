<?php

namespace Drupal\Tests\views_date_format_sql\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Views;

/**
 * Tests views_date_format_sql field dependencies.
 *
 * @group views_date_format_sql
 */
class ViewsDateFormatSqlDependenciesTest extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'file',
    'image',
    'media',
    'user',
    'system',
    'text',
    'views',
    'views_date_format_sql',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('user', ['users_data']);

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('view');

    $this->installConfig([
      'field',
      'filter',
      'file',
      'image',
      'media',
      'user',
      'text',
      'views',
      'views_date_format_sql',
    ]);
  }

  /**
   * Asserts that dependencies are not modified.
   */
  public function testUnmodifiedViewDependencies() {
    $view = Views::getView('media');

    $expected = [
      'config' => ['image.style.thumbnail'],
      'module' => ['image', 'media', 'user'],
    ];
    $this->assertEquals($view->getDependencies(), $expected);
  }

  /**
   * Asserts that dependencies are modified.
   */
  public function testModifiedViewDependencies() {
    $view = Views::getView('media');

    $display = &$view->storage->getDisplay('default');
    $display['display_options']['fields']['changed']['format_date_sql'] = 1;

    $expected = [
      'config' => ['image.style.thumbnail'],
      'module' => ['image', 'media', 'user', 'views_date_format_sql'],
    ];
    $this->assertEquals($view->getDependencies(), $expected);
  }

}
