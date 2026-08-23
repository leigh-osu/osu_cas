<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_layouts\FunctionalJavascriptTests;

use Drupal\FunctionalJavascriptTests\PerformanceTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\ui_patterns\Traits\ConfigImporterTrait;
use Drupal\Tests\ui_patterns\Traits\TestContentCreationTrait;
use Drupal\Tests\ui_patterns\Traits\TestDataTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Performance measuring of layout builder sections.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_layouts')]
#[RunTestsInSeparateProcesses]
final class LayoutBuilderRenderPerformanceTest extends PerformanceTestBase {

  use TestContentCreationTrait;
  use TestDataTrait;
  use ConfigImporterTrait;

  /**
   * The default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * The tested node.
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ui_patterns',
    'ui_patterns_test',
    'ui_patterns_layouts',
    'field_ui',
    'layout_builder',
    'block',
  ];

  /**
   * Tests preview and output of props.
   */
  public function testRenderSections(): void {
    $config_import = $this->loadConfigFixture(__DIR__ . '/../../fixtures/config/core.entity_view_display.node.page.full.yml');
    $ui_patterns_config = &$config_import['third_party_settings']['layout_builder']['sections'][0]['layout_settings']['ui_patterns'];
    $test_data = $this->loadTestDataFixture();
    $test_set = $test_data->getTestSet('textfield_default');
    $this->node = $this->createTestContentNode('page', $test_set['entity'] ?? []);
    $ui_patterns_config = $this->buildUiPatternsConfig($test_set);
    $config_import['third_party_settings']['layout_builder']['sections'][0]['layout_id'] = 'ui_patterns:' . \str_replace('-', '_', $test_set['component']['component_id']);
    $section = $config_import['third_party_settings']['layout_builder']['sections'][0];

    for ($i = 0; $i < 1000; ++$i) {
      $config_import['third_party_settings']['layout_builder']['sections'][$i + 1] = $section;
    }
    $this->importConfigFixture(
      'core.entity_view_display.node.page.full',
      $config_import
    );

    $this->collectPerformanceData(function () {
      $this->drupalGet('node/' . $this->node->id());
    }, 'UIPatternsRenderSections');
  }

}
