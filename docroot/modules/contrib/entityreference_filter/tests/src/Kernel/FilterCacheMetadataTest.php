<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\entityreference_filter\Traits\EntityReferenceFilterTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests cache metadata and dependencies of the entityreference filter.
 *
 * @group entityreference_filter
 *
 * @covers \Drupal\entityreference_filter\Plugin\views\filter\EntityReferenceFilterViewResult::getCacheTags
 * @covers \Drupal\entityreference_filter\Plugin\views\filter\EntityReferenceFilterViewResult::calculateDependencies
 */
#[RunTestsInSeparateProcesses]
class FilterCacheMetadataTest extends KernelTestBase {

  use EntityReferenceFilterTrait;

  /**
   * The machine name of the entityreference filter handler under test.
   */
  protected const FILTER_ID = 'field_taxonomy_reference_target_id_entityreference_filter';

  /**
   * The reference view referenced by the filter.
   */
  protected const REFERENCE_VIEW = 'test_entityreference_view_terms';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'taxonomy',
    'views',
    'entityreference_filter',
    'entityreference_filter_test_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['field', 'node', 'taxonomy']);

    // The reference view config depends on these vocabularies.
    Vocabulary::create(['vid' => 'test1', 'name' => 'test1'])->save();
    Vocabulary::create(['vid' => 'test2', 'name' => 'test2'])->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    // The test views reference the node__field_taxonomy_reference table.
    FieldStorageConfig::create([
      'field_name' => 'field_taxonomy_reference',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_taxonomy_reference',
      'entity_type' => 'node',
      'bundle' => 'article',
      'settings' => ['handler' => 'default'],
    ])->save();

    $this->createTestViews(['test_view', self::REFERENCE_VIEW]);
  }

  /**
   * Loads the entityreference filter handler from the default display.
   *
   * @return \Drupal\entityreference_filter\Plugin\views\filter\EntityReferenceFilterViewResult
   *   The filter handler.
   */
  protected function getFilterHandler() {
    $view = Views::getView('test_view');
    $this->assertNotNull($view, 'The test_view exists.');
    $view->setDisplay('default');
    $view->initHandlers();
    $this->assertArrayHasKey(self::FILTER_ID, $view->filter);
    return $view->filter[self::FILTER_ID];
  }

  /**
   * The reference view's base entity list cache tag is exposed by the filter.
   */
  public function testGetCacheTagsContainsBaseEntityListTag(): void {
    $tags = $this->getFilterHandler()->getCacheTags();
    $this->assertContains('taxonomy_term_list', $tags);
  }

  /**
   * The reference view is added as a config dependency of the filter.
   */
  public function testCalculateDependenciesContainsReferenceView(): void {
    $dependencies = $this->getFilterHandler()->calculateDependencies();
    $this->assertContains('views.view.' . self::REFERENCE_VIEW, $dependencies['config'] ?? []);
  }

}
