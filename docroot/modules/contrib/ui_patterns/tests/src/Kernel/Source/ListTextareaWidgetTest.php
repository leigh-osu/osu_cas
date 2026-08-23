<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\ListTextareaWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test ListTextareaWidget.
 *
 * @internal
 */
#[CoversClass(ListTextareaWidget::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ListTextareaWidgetTest extends SourcePluginsTestBase {

  /**
   * Test ListTextareaWidget Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('list_textarea_');
  }

}
