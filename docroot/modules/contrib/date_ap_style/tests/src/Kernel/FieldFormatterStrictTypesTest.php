<?php

declare(strict_types=1);

namespace Drupal\Tests\date_ap_style\Kernel;

use Drupal\views\Views;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests field formatter compatibility with strict_types declaration.
 *
 * This test ensures that the AP Style field formatters properly handle:
 * - Timestamp values that come as strings from the database
 * - Boolean options that come as integers (0/1) from form submissions.
 *
 * @group date_ap_style
 */
class FieldFormatterStrictTypesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'datetime',
    'datetime_range',
    'date_ap_style',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field', 'date_ap_style']);

    // Create a content type.
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
  }

  /**
   * Tests timestamp field formatter with string values.
   *
   * This reproduces the issue where timestamp values come through as strings
   * from the database, but the formatter expects integers with strict_types.
   */
  public function testTimestampFieldFormatterWithStringValue(): void {
    // Create a node with a created timestamp.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
      'created' => time(),
    ]);
    $node->save();

    // Configure the view display to use AP Style formatter.
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
    ]);

    // Configure the created field with AP Style formatter.
    // Use integer values (0/1) for boolean settings to simulate form
    // submission.
    $display->setComponent('created', [
      'type' => 'timestamp_ap_style',
      'settings' => [
        // Integer, not boolean.
        'always_display_year' => 1,
        'use_today' => 0,
        'cap_today' => 0,
        'display_day' => 1,
        'display_time' => 0,
        'hide_date' => 0,
        'time_before_date' => 0,
        'use_all_day' => 0,
        'month_only' => 0,
        'display_noon_and_midnight' => 0,
        'capitalize_noon_and_midnight' => 0,
        'timezone' => '',
      ],
    ]);
    $display->save();

    // Build the node view. This should NOT throw a TypeError.
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $build = $view_builder->view($node, 'default');

    // Render the output.
    $renderer = \Drupal::service('renderer');
    $output = (string) $renderer->renderRoot($build);

    // Assert that we got output without errors.
    $this->assertNotEmpty($output);
    $this->assertStringContainsString('Test Article', $output);
  }

  /**
   * Tests datetime field formatter with integer boolean options.
   */
  public function testDatetimeFieldFormatterWithIntegerBooleans(): void {
    // Create a datetime field.
    FieldStorageConfig::create([
      'field_name' => 'field_test_date',
      'entity_type' => 'node',
      'type' => 'datetime',
      'settings' => [
        'datetime_type' => 'datetime',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_test_date',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Test Date',
    ])->save();

    // Create a node with a date value.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Article with Date',
      'field_test_date' => '2024-01-15T10:30:00',
    ]);
    $node->save();

    // Configure the view display.
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
    ]);

    // Configure with integer boolean values (simulating form submission).
    $display->setComponent('field_test_date', [
      'type' => 'timestamp_ap_style',
      'settings' => [
        'always_display_year' => 1,
        'use_today' => 1,
        'cap_today' => 1,
        'display_day' => 0,
        'display_time' => 1,
        'hide_date' => 0,
        'time_before_date' => 0,
        'use_all_day' => 0,
        'month_only' => 0,
        'display_noon_and_midnight' => 1,
        'capitalize_noon_and_midnight' => 1,
        'timezone' => '',
      ],
    ]);
    $display->save();

    // Build and render the view.
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $build = $view_builder->view($node, 'default');

    $renderer = \Drupal::service('renderer');
    $output = (string) $renderer->renderRoot($build);

    // Assert successful rendering without TypeError.
    $this->assertNotEmpty($output);
    $this->assertStringContainsString('Test Article with Date', $output);
  }

  /**
   * Tests daterange field formatter with string timestamps.
   */
  public function testDateRangeFieldFormatterWithStringValues(): void {
    // Create a daterange field.
    FieldStorageConfig::create([
      'field_name' => 'field_date_range',
      'entity_type' => 'node',
      'type' => 'daterange',
      'settings' => [
        'datetime_type' => 'datetime',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_date_range',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Date Range',
    ])->save();

    // Create a node with a date range.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Article with Range',
      'field_date_range' => [
        'value' => '2024-01-15T10:00:00',
        'end_value' => '2024-01-15T16:00:00',
      ],
    ]);
    $node->save();

    // Configure the view display.
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
    ]);

    // Configure with integer boolean values.
    $display->setComponent('field_date_range', [
      'type' => 'daterange_ap_style',
      'settings' => [
        'always_display_year' => 1,
        'use_today' => 0,
        'cap_today' => 0,
        'display_day' => 0,
        'display_time' => 1,
        'hide_date' => 0,
        'time_before_date' => 0,
        'use_all_day' => 0,
        'month_only' => 0,
        'display_noon_and_midnight' => 0,
        'capitalize_noon_and_midnight' => 0,
        'separator' => 'to',
        'timezone' => '',
      ],
    ]);
    $display->save();

    // Build and render the view.
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $build = $view_builder->view($node, 'default');

    $renderer = \Drupal::service('renderer');
    $output = (string) $renderer->renderRoot($build);

    // Assert successful rendering without TypeError.
    $this->assertNotEmpty($output);
    $this->assertStringContainsString('Test Article with Range', $output);
  }

  /**
   * Tests that Views with integer boolean settings work correctly.
   *
   * This test loads a view configuration that has integer values (0/1)
   * for boolean settings, which is what causes the TypeError with strict_types.
   */
  public function testViewsWithIntegerBooleanSettings(): void {
    // Import the test view with integer boolean values.
    $config_path = __DIR__ . '/../../fixtures/views.view.test_strict_types.yml';
    $view_config = Yaml::decode(file_get_contents($config_path));

    $config_storage = \Drupal::service('config.storage');
    $config_storage->write('views.view.test_strict_types', $view_config);

    // Clear cache to load the new view.
    \Drupal::service('views.views_data')->clear();

    // Create some test nodes.
    for ($i = 0; $i < 3; $i++) {
      Node::create([
        'type' => 'article',
        'title' => 'Test Node ' . $i,
        'created' => time() - ($i * 86400),
      ])->save();
    }

    // Load and execute the view.
    $view = Views::getView('test_strict_types');
    $this->assertNotNull($view, 'View should be loaded');

    // This should NOT throw a TypeError even with integer boolean settings.
    $view->setDisplay('page_1');
    $view->preExecute();
    $view->execute();

    // Render the view.
    $renderer = \Drupal::service('renderer');
    $build = $view->buildRenderable('page_1');
    $output = (string) $renderer->renderRoot($build);

    $this->assertNotEmpty($output);
    $this->assertStringContainsString('Test Node', $output);
  }

}
