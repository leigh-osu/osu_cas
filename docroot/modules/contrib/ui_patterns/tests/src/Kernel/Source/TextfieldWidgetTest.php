<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\TextfieldWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test TextfieldWidget.
 *
 * @internal
 */
#[CoversClass(TextfieldWidget::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class TextfieldWidgetTest extends SourcePluginsTestBase {

  /**
   * Test TextfieldWidget Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('textfield_');
  }

}
