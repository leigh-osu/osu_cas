<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\EntityFieldSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test EntityFieldSource.
 *
 * @internal
 */
#[CoversClass(EntityFieldSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class EntityFieldSourceTest extends SourcePluginsTestBase {

  /**
   * Test EntityFieldSource Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('entity_field_');
  }

}
