<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_patterns\SchemaManager\Canonicalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the Canonicalizer.
 *
 * @internal
 */
#[CoversClass(Canonicalizer::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class CanonicalizerTest extends UnitTestCase {

  /**
   * Test the method ::canonicalize().
   */
  #[DataProvider('provideCanonicalizerData')]
  public function testCanonicalize(array $schema, array $expected): void {
    $canonicalizer = new Canonicalizer();
    $canonicalized = $canonicalizer->canonicalize($schema);
    self::assertEqualsCanonicalizing($canonicalized, $expected);
  }

  /**
   * Provide data for testCanonicalize.
   */
  public static function provideCanonicalizerData(): \Generator {
    $file_contents = \file_get_contents(__DIR__ . '/../../fixtures/CanonicalizerData.yml');
    $sources = $file_contents ? Yaml::decode($file_contents) : [];

    foreach ($sources as $source) {
      foreach ($source['tests'] as $test) {
        $label = $source['label'] . ':' . $test['label'];

        yield $label => [
          $test['schema'],
          $test['expected'],
        ];
      }
    }
  }

}
