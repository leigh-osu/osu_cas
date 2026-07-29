<?php

namespace Drupal\Tests\eva\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\views\Views;

/**
 * Test options summary.
 *
 * @group eva
 */
class EvaOptionsSummaryKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'views',
    'eva',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install entity schemas.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // Install Views config storage.
    $this->installConfig(['views']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
  }

  /**
   * Tests unknown bundle fallback in optionsSummary().
   */
  public function testUnknownBundleInOptionsSummary() {
    $view = Views::getView('content');
    $this->assertNotNull($view, 'Core content view exists.');

    $view->storage->addDisplay('entity_view', 'EVA test', 'eva_test');
    $view->save();

    $view->initDisplay();

    /** @var \Drupal\views\Plugin\views\display\DisplayPluginBase $display */
    $display = $view->displayHandlers->get('eva_test');

    // Force options.
    $display->setOption('entity_type', 'node');
    $display->setOption('bundles', ['article', 'non_existing_bundle']);

    $categories = [];
    $options = [];

    $display->optionsSummary($categories, $options);

    $this->assertStringContainsString('Article', $options['bundles']['value']);
    $this->assertStringContainsString('Unknown bundle', $options['bundles']['value']);
  }

}
