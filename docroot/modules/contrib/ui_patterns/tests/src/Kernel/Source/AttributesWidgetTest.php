<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\AttributesWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test AttributesWidget.
 *
 * @internal
 */
#[CoversClass(AttributesWidget::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class AttributesWidgetTest extends SourcePluginsTestBase {

  /**
   * Test AttributesWidget Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('attributes_');
    $this->runSourcePluginTests('attributes_', __DIR__ . '/../../../fixtures/source_only_tests.yml');
  }

}
