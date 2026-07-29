<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\ui_patterns\LoggerStub;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_patterns\SchemaManager\Canonicalizer;
use Drupal\ui_patterns\SchemaManager\CompatibilityChecker;
use Drupal\ui_patterns\SchemaManager\ReferencesResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test CompatibilityChecker service.
 *
 * @internal
 */
#[CoversClass(CompatibilityChecker::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class CompatibilityCheckerTest extends UnitTestCase {

  /**
   * Test the method ::isCompatible().
   */
  #[DataProvider('provideCompatibilityCheckerData')]
  public function testIsCompatible(array $referenceSchema, array $testData): void {
    $validator = new CompatibilityChecker(new Canonicalizer(), new ReferencesResolver(new LoggerStub()));

    foreach ($testData as $test) {
      $result = $validator->isCompatible($test['schema'], $referenceSchema);
      self::assertEquals((bool) $test['result'], $result);
    }
  }

  /**
   * Provide data for testIsCompatible.
   */
  public static function provideCompatibilityCheckerData(): \Generator {
    $file_contents = \file_get_contents(__DIR__ . '/../../fixtures/CompatibilityCheckerData.yml');
    $sources = $file_contents ? Yaml::decode($file_contents) : [];

    foreach ($sources as $source) {
      yield $source['label'] => [
        $source['schema'],
        $source['tests'],
      ];
    }
  }

}
