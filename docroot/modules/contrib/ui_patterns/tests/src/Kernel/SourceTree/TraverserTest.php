<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\SourceTree;

use Drupal\ui_patterns\SourceTree\TranslationApplier;
use Drupal\ui_patterns\SourceTree\TranslationExtractor;
use Drupal\ui_patterns\SourceTree\Traverser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test Traverser.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class TraverserTest extends TranslationBase {

  /**
   * Test TranslationApplier.
   */
  public function testApplier(): void {
    $traverser = new Traverser();
    $translation_applier = new TranslationApplier(
      ['node-10:source.value' => 'Replaced']
    );

    $typed_data = \Drupal::service('config.typed')->createFromNameAndData(
      'ui_patterns_slot_source',
      $this->testSourceTreeData[0]
    );

    $processors = [
      $translation_applier,
    ];
    $data = $this->testSourceTreeData[0];
    $traverser->traverse($typed_data, $processors, $data, []);
    self::assertEquals('Replaced', $data['source']['component']['props']['attributes']['source']['value']);
  }

  /**
   * Test TranslationExtractor.
   */
  public function testExtraction(): void {
    $traverser = new Traverser();
    $translation_extractor = new TranslationExtractor();
    $typed_data = \Drupal::service('config.typed')->createFromNameAndData(
      'ui_patterns_slot_source',
      $this->testSourceTreeData[0]
    );

    $processors = [
      $translation_extractor,
    ];
    $data = $this->testSourceTreeData[0];
    $traverser->traverse($typed_data, $processors, $data, []);
    $translations = $translation_extractor->getTranslations();
    self::assertEquals(3, count($translations));
  }

}
