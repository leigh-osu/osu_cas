<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\PathSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test PathSource.
 *
 * @internal
 */
#[CoversClass(PathSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class PathSourceTest extends SourcePluginsTestBase {

  /**
   * Test PathSource Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('path_');
  }

}
