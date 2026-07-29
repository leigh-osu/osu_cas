<?php

declare(strict_types=1);

namespace Drupal\Tests\views_selective_filters\Kernel\Plugin\views\filter;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use Drupal\views_selective_filters\Plugin\views\filter\Selective;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the selective Views filter plugin.
 */
#[CoversClass(Selective::class)]
#[Group('views_selective_filters')]
#[RunTestsInSeparateProcesses]
class SelectiveTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'views',
    'views_selective_filters',
  ];

  /**
   * Tests content type selective filters can build their options form.
   */
  public function testContentTypeOptionsFormBuilds(): void {
    $this->installConfig(['system', 'views']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $view_id = 'test_content_type_selective_filter';
    View::create([
      'id' => $view_id,
      'label' => $view_id,
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'fields' => [
              'type' => [
                'id' => 'type',
                'table' => 'node_field_data',
                'field' => 'type',
                'relationship' => 'none',
                'plugin_id' => 'field',
                'entity_type' => 'node',
                'entity_field' => 'type',
              ],
            ],
            'filters' => [
              'type_selective' => [
                'id' => 'type_selective',
                'table' => 'node_field_data',
                'field' => 'type_selective',
                'relationship' => 'none',
                'plugin_id' => 'views_selective_filters_filter',
                'entity_type' => 'node',
                'entity_field' => 'type',
                'selective_display_field' => 'type',
              ],
            ],
          ],
        ],
      ],
    ])->save();

    $view = Views::getView($view_id);
    $view->setDisplay();
    $view->initHandlers();

    $form = [];
    $view->filter['type_selective']->buildOptionsForm($form, new FormState());

    $this->assertArrayHasKey('selective_display_sort', $form);
  }

}
