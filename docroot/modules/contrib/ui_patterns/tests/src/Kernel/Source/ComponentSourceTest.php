<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\ComponentSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test ComponentSource.
 *
 * @internal
 */
#[CoversClass(ComponentSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComponentSourceTest extends SourcePluginsTestBase {

  /**
   * Test ComponentSource Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('component_');
  }

}
