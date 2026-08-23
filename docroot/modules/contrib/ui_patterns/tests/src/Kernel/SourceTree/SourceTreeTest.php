<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\SourceTree;

use Drupal\ui_patterns\SourceTree\SourceTree;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test SourceTree.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class SourceTreeTest extends TranslationBase {

  /**
   * Test __construct and toArray.
   */
  public function testConstructAndToArray(): void {
    $data = $this->testSourceTreeData[0];
    $tree = new SourceTree($data);
    self::assertEquals($data, $tree->toArray());
  }

  /**
   * Test getTranslations.
   */
  public function testGetTranslations(): void {
    $data = $this->testSourceTreeData[0];
    $tree = new SourceTree($data);
    $translations = $tree->getTranslations();
    self::assertNotEmpty($translations);

    // Check if we have any key starting with node-10.
    $found = FALSE;
    foreach ($translations as $key => $value) {
      if (strpos($key, 'node-10') === 0) {
        $found = TRUE;
        self::assertEquals('Default Value', $value);
      }
    }
    self::assertTrue($found, 'Translation for node-10 found');
  }

  /**
   * Test applyTranslations.
   */
  public function testApplyTranslations(): void {
    $data = $this->testSourceTreeData[0];
    $tree = new SourceTree($data);

    // Apply translations using a whole-node override keyed by node ID.
    $translations = [
      'node-10' => [
        'source_id' => 'attributes',
        'node_id' => 'node-10',
        'source' => [
          'value' => 'Translated Value',
        ],
      ],
    ];

    $tree->applyTranslations($translations);
    $result = $tree->toArray();

    self::assertEquals('Translated Value', $result['source']['component']['props']['attributes']['source']['value']);

    // Verify that subsequent getTranslations returns the NEW value.
    // This tests invalidation of cached TypedData.
    $new_translations = $tree->getTranslations();
    $found = FALSE;
    foreach ($new_translations as $key => $value) {
      if (strpos($key, 'node-10') === 0) {
        $found = TRUE;
        self::assertEquals('Translated Value', $value);
      }
    }
    self::assertTrue($found, 'New translation for node-10 found after application');
  }

  /**
   * Test applyTranslations on a leaf value (extracted format).
   */
  public function testApplyLeafTranslation(): void {
    $data = $this->testSourceTreeData[0];
    $tree = new SourceTree($data);

    // Extracted key style (node-10:source.value).
    $translations = [
      'node-10:source.value' => 'Translated Leaf',
    ];

    $tree->applyTranslations($translations);
    $result = $tree->toArray();

    self::assertEquals('Translated Leaf', $result['source']['component']['props']['attributes']['source']['value']);
  }

  /**
   * Test nested translation application.
   */
  public function testNestedTranslations(): void {
    $data = $this->testSourceTreeData[1];
    $tree = new SourceTree($data);

    // node-7 is deeply nested (wysiwyg).
    $translations = [
      'node-7' => [
        'source_id' => 'wysiwyg',
        'node_id' => 'node-7',
        'source' => [
          'value' => [
            'value' => 'translated deep',
            'format' => 'plain_text',
          ],
        ],
      ],
    ];

    $tree->applyTranslations($translations);
    $result = $tree->toArray();

    // Structure: component(node-3) -> slots/content/sources/0
    // /component(node-4) -> /component(node-6) -> /wysiwyg(node-7).
    $root = $result['source']['component'];
    self::assertNotNull($root, 'Root is null');

    $level1 = $root['slots']['content']['sources'][0]['source']['component'];
    self::assertNotNull($level1, 'Level 1 is null');

    $level2 = $level1['slots']['content']['sources'][0]['source']['component'];
    self::assertNotNull($level2, 'Level 2 is null');

    $level3 = $level2['slots']['content']['sources'][0]['source'];
    self::assertNotNull($level3, 'Level 3 is null');

    self::assertEquals('translated deep', $level3['value']['value']);
  }

  /**
   * Test empty translations.
   */
  public function testEmptyTranslations(): void {
    $data = $this->testSourceTreeData[0];
    $tree = new SourceTree($data);
    $original = $tree->toArray();

    $tree->applyTranslations([]);
    self::assertEquals($original, $tree->toArray());
  }

  /**
   * Trees differing only in translatable leaves share a structure signature.
   */
  public function testStructureSignatureIgnoresTranslatableLeaves(): void {
    $data = $this->testSourceTreeData[0];
    $tree_a = new SourceTree($data);

    $changed = $data;
    $changed['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'andere sprache';
    $tree_b = new SourceTree($changed);

    self::assertSame(
      $tree_a->getStructureSignature(),
      $tree_b->getStructureSignature(),
      'Leaf-only differences must not change the structure signature.',
    );
  }

  /**
   * Moving a node to another slot changes the structure signature.
   */
  public function testStructureSignatureChangesOnMove(): void {
    $data = $this->testSourceTreeData[0];
    $tree_a = new SourceTree($data);

    $moved = $data;
    $node = $moved['source']['component']['slots']['content']['sources'][0];
    unset($moved['source']['component']['slots']['content']['sources'][0]);
    $moved['source']['component']['slots']['image']['sources'][0] = $node;
    $tree_b = new SourceTree($moved);

    self::assertNotSame(
      $tree_a->getStructureSignature(),
      $tree_b->getStructureSignature(),
      'A moved node must change the structure signature.',
    );
  }

}
