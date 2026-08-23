<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\NumberWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test NumberWidget.
 *
 * @internal
 */
#[CoversClass(NumberWidget::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class NumberWidgetTest extends SourcePluginsTestBase {

  /**
   * Test NumberWidget Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('number_');
  }

}
