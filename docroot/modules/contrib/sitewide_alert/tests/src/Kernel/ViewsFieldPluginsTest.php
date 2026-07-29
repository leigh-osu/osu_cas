<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

use Drupal\sitewide_alert\Plugin\views\field\SitewideAlertActive;
use Drupal\sitewide_alert\Plugin\views\field\SitewideAlertPageVisibility;
use Drupal\sitewide_alert\Plugin\views\field\SitewideAlertScheduled;
use Drupal\sitewide_alert\Plugin\views\field\SitewideAlertStyle;
use Drupal\views\ResultRow;

/**
 * Tests the custom Views field plugins for sitewide alerts.
 *
 * @group sitewide_alert
 */
final class ViewsFieldPluginsTest extends SitewideAlertKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views'];

  /**
   * Tests SitewideAlertActive field plugin.
   *
   * @covers \Drupal\sitewide_alert\Plugin\views\field\SitewideAlertActive::render
   */
  public function testActiveFieldPlugin(): void {
    $plugin = $this->createFieldPlugin(SitewideAlertActive::class);

    // Test active (truthy) value.
    $row = $this->createResultRow(['status' => 1]);
    $plugin->field_alias = 'status';
    $this->assertEquals('✔', $plugin->render($row));

    // Test inactive (falsy) value.
    $row = $this->createResultRow(['status' => 0]);
    $this->assertEquals('✘', $plugin->render($row));
  }

  /**
   * Tests SitewideAlertScheduled field plugin.
   *
   * @covers \Drupal\sitewide_alert\Plugin\views\field\SitewideAlertScheduled::render
   */
  public function testScheduledFieldPlugin(): void {
    $plugin = $this->createFieldPlugin(SitewideAlertScheduled::class);

    // Test scheduled (truthy) value.
    $row = $this->createResultRow(['scheduled_alert' => 1]);
    $plugin->field_alias = 'scheduled_alert';
    $this->assertEquals('✔', $plugin->render($row));

    // Test not scheduled (falsy) value.
    $row = $this->createResultRow(['scheduled_alert' => 0]);
    $this->assertEquals('✘', $plugin->render($row));
  }

  /**
   * Tests SitewideAlertStyle field plugin.
   *
   * @covers \Drupal\sitewide_alert\Plugin\views\field\SitewideAlertStyle::render
   */
  public function testStyleFieldPlugin(): void {
    $plugin = $this->createFieldPlugin(SitewideAlertStyle::class);

    // Test with configured style (primary maps to "Default" in config).
    $row = $this->createResultRow(['style' => 'primary']);
    $plugin->field_alias = 'style';
    $this->assertEquals('Default', $plugin->render($row));

    // Test with unknown style returns N/A.
    $row = $this->createResultRow(['style' => 'unknown_style']);
    $this->assertEquals('N/A', $plugin->render($row));

    // Test with empty value.
    $row = $this->createResultRow(['style' => '']);
    $this->assertEquals('', $plugin->render($row));
  }

  /**
   * Tests SitewideAlertPageVisibility field plugin.
   *
   * @covers \Drupal\sitewide_alert\Plugin\views\field\SitewideAlertPageVisibility::render
   */
  public function testPageVisibilityFieldPlugin(): void {
    $plugin = $this->createPageVisibilityPlugin();

    // Test alert with no page restrictions (all pages).
    $alert = $this->createSiteWideAlert(['limit_to_pages' => '']);
    $row = $this->createResultRow([], $alert);
    $result = $plugin->render($row);
    $this->assertIsArray($result);
    $this->assertEquals('All pages', $result['#plain_text']);

    // Test alert with specific pages.
    $alert = $this->createSiteWideAlert(['limit_to_pages' => "/node/1\n/about"]);
    $row = $this->createResultRow([], $alert);
    $result = $plugin->render($row);
    $this->assertIsArray($result);
    $this->assertStringContainsString('/node/1', $result['#markup']);
    $this->assertStringContainsString('/about', $result['#markup']);

    // Test custom all pages text.
    $plugin->options['all_pages_text'] = 'Everywhere';
    $alert = $this->createSiteWideAlert(['limit_to_pages' => '']);
    $row = $this->createResultRow([], $alert);
    $result = $plugin->render($row);
    $this->assertEquals('Everywhere', $result['#plain_text']);
  }

  /**
   * Creates a Views field plugin instance.
   *
   * @param string $class
   *   The plugin class name.
   *
   * @return \Drupal\views\Plugin\views\field\FieldPluginBase
   *   The field plugin instance.
   */
  private function createFieldPlugin(string $class): object {
    return new $class([], 'test', ['id' => 'test']);
  }

  /**
   * Creates a PageVisibility plugin with required Views context.
   *
   * @return \Drupal\sitewide_alert\Plugin\views\field\SitewideAlertPageVisibility
   *   The configured plugin.
   */
  private function createPageVisibilityPlugin(): SitewideAlertPageVisibility {
    $plugin = new TestablePageVisibilityPlugin([], 'test', ['id' => 'test']);
    $plugin->options['all_pages_text'] = 'All pages';
    return $plugin;
  }

  /**
   * Creates a ResultRow for testing.
   *
   * @param array $values
   *   Field values to set on the row.
   * @param object|null $entity
   *   Optional entity to attach to the row.
   *
   * @return \Drupal\views\ResultRow
   *   The result row.
   */
  private function createResultRow(array $values = [], ?object $entity = NULL): ResultRow {
    $row = new ResultRow($values);
    if ($entity) {
      $row->_entity = $entity;
    }
    return $row;
  }

}

/**
 * Testable version of SitewideAlertPageVisibility that bypasses Views context.
 */
class TestablePageVisibilityPlugin extends SitewideAlertPageVisibility {

  /**
   * {@inheritdoc}
   */
  public function getEntity(ResultRow $values) {
    return $values->_entity ?? NULL;
  }

}
