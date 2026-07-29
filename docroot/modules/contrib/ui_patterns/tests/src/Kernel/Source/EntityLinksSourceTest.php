<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\EntityLinksSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test EntityLinksSource.
 *
 * @internal
 */
#[CoversClass(EntityLinksSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class EntityLinksSourceTest extends SourcePluginsTestBase {

  /**
   * Test EntityLinksSource Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('entity_links_');
  }

}
