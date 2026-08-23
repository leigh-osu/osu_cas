<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldFormatterSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test FieldFormatterSource.
 *
 * @internal
 */
#[CoversClass(FieldFormatterSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class FieldFormatterSourceTest extends SourcePluginsTestBase {

  /**
   * Test Field Property Plugin.
   */
  public function testPlugin(): void {
    $testData = self::loadTestDataFixture(__DIR__ . '/../../../fixtures/tests.formatter_per_item.yml');
    $testSets = $testData->getTestSets();

    foreach ($testSets as $test_set_name => $test_set) {
      if (!\str_starts_with($test_set_name, 'field_formatter_')) {
        continue;
      }
      $this->runSourcePluginTest($test_set);
    }
  }

}
