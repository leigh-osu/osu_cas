<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\BlockSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test BlockSource.
 *
 * @internal
 */
#[CoversClass(BlockSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class BlockSourceTest extends SourcePluginsTestBase {

  /**
   * Test BlockSource Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('block_');
    $this->runSourcePluginTests('block_', __DIR__ . '/../../../fixtures/block_tests.yml');
  }

}
