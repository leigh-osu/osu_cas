<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\CheckboxWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test CheckboxWidget.
 *
 * @internal
 */
#[CoversClass(CheckboxWidget::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class CheckboxWidgetTest extends SourcePluginsTestBase {

  /**
   * Test CheckboxWidget Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('checkbox_');
  }

}
