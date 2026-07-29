<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\SelectWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test SelectWidget.
 *
 * @internal
 */
#[CoversClass(SelectWidget::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class SelectWidgetTest extends SourcePluginsTestBase {

  /**
   * Test SelectWidget Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('select_');
  }

}
