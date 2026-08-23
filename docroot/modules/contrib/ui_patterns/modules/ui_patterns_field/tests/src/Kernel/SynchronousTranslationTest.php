<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_field\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\ui_patterns\Kernel\SourceTree\TranslationBase;
use Drupal\Tests\ui_patterns\Traits\TestContentCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests synchronized translations of the ui_patterns_source field.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns_field')]
#[RunTestsInSeparateProcesses]
final class SynchronousTranslationTest extends TranslationBase {

  use TestContentCreationTrait;

  /**
   * The default language stores its source tree as-is, with no overlay.
   */
  public function testDefaultLanguageStoresSourceTree(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();
    $loaded_node = Node::load($this->node->id());
    $field_sources = $loaded_node->get('field_source')->getValue();

    // Translations are not extracted/stored for the default language; they
    // are implicit in the source tree.
    self::assertArrayHasKey('source', $field_sources[0]);
    self::assertNotEmpty($field_sources[0]['source']);
  }

  /**
   * Test translations in translated content.
   */
  public function testTranslation() {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();
    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_node->save();
    $german_values = $german_node->get('field_source')->getValue();
    $german_value = $german_values[0];
    self::assertNotEmpty($german_value);

    $german_value['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'deutsch';
    $german_values[0] = $german_value;
    $german_node->set('field_source', $german_values);
    $german_node->save();

    $german_node = $this->node->getTranslation('de');
    $resaved_german_values = $german_node->get('field_source')->getValue();
    self::assertEquals('deutsch', $german_node->getTitle());

    // Check if translation is applied to source.
    self::assertEquals('deutsch', $resaved_german_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value']);
  }

  /**
   * Regression: saving a translation must not empty the original delta.
   *
   * After saving a German translation, reloading the node from storage must
   * still expose the full English source tree on the default translation —
   * neither an empty delta nor a bare `{translations: …}` wrapper.
   */
  public function testOriginalLanguageNotEmptiedAfterTranslationSave(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_node->save();

    $german_values = $german_node->get('field_source')->getValue();
    $german_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'deutsch';
    $german_node->set('field_source', $german_values);
    $german_node->save();

    // Hard reload from DB to eliminate any in-memory state.
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertNotNull($reloaded);

    $english_values = $reloaded->getUntranslated()->get('field_source')->getValue();

    self::assertNotEmpty($english_values, 'Original English field values must not be empty after translation save.');
    self::assertArrayHasKey(0, $english_values);
    self::assertArrayHasKey('source', $english_values[0]);
    self::assertIsArray($english_values[0]['source']);
    self::assertNotEmpty($english_values[0]['source'], 'Original English source column must not be empty.');

    // The default translation must never store the `{translations: …}` wrapper
    // — that wrapper is a translation-side artefact only.
    self::assertArrayNotHasKey(
      'translations',
      $english_values[0]['source'],
      'Default language must store the full source tree, not a translations wrapper.'
    );

    // And it must still contain the original strings.
    self::assertSame(
      'english',
      $english_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'],
      'Original English leaf value must survive a translation save.'
    );

    // Second delta (nested tree) must also be intact.
    self::assertArrayHasKey(1, $english_values);
    self::assertSame(
      'deep',
      $english_values[1]['source']['component']['slots']['content']['sources'][0]['source']['component']['slots']['content']['sources'][0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'],
      'Deeply nested original leaf must survive a translation save.'
    );
  }

  /**
   * A delta added to default language after translation creation is visible.
   *
   * Steps:
   *  1. Save English with only delta 0.
   *  2. Create and save a German translation (only has delta 0).
   *  3. Add delta 1 to the English node and save.
   *  4. Read the German field — delta 1 must now be present with the English
   *     source tree as its content.
   */
  public function testMissingDeltaFromDefaultLanguageIsFilled(): void {
    // Step 1: English with only first item.
    $this->node->set('field_source', [$this->testSourceTreeData[0]]);
    $this->node->save();

    // Step 2: German translation with only delta 0.
    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_node->save();

    // Step 3: Add delta 1 to English and save.
    $english_values = $this->node->get('field_source')->getValue();
    $english_values[] = $this->testSourceTreeData[1];
    $this->node->set('field_source', $english_values);
    $this->node->save();

    // Step 4: Reload German and verify delta 1 is present.
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertNotNull($reloaded);

    $german_values = $reloaded->getTranslation('de')
      ->get('field_source')
      ->getValue();

    self::assertCount(2, $german_values, 'German translation must expose both deltas after default-language gains a second item.');
    self::assertArrayHasKey(1, $german_values);
    self::assertNotEmpty($german_values[1]['source'], 'Delta 1 on the German translation must carry the English source tree.');
  }

  /**
   * Translated leaf must survive cache reset + reload.
   *
   * The pre-existing testTranslation() asserts against the in-memory entity
   * that was just saved, so a silent persistence failure would still let it
   * pass. This variant resets the storage cache and loads from DB to prove
   * the translated leaf actually round-trips through storage.
   */
  public function testTranslatedLeafPersistsAfterReload(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_values = $german_node->get('field_source')->getValue();
    $german_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'deutscher text';
    $german_node->set('field_source', $german_values);
    $german_node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertNotNull($reloaded);

    $de_translations = self::sourceList($reloaded->getTranslation('de'))->getTranslations();
    self::assertNotEmpty($de_translations, 'German translation map must be persisted.');
    self::assertContains('deutscher text', $de_translations, 'German translation map must contain the translated leaf.');

    $de_merged = $reloaded->getTranslation('de')
      ->get('field_source')
      ->getValue();
    self::assertSame(
      'deutscher text',
      $de_merged[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'],
      'Merged German read must expose the translated leaf after reload.',
    );

    $en_raw = self::sourceList($reloaded->getUntranslated())->getRawValues()[0]['source'];
    self::assertSame(
      'english',
      $en_raw['component']['slots']['content']['sources'][0]['source']['value']['value'],
      'Default language baseline leaf must survive a translation save.',
    );
  }

  /**
   * Regression: a new delta added on a translation propagates to the default.
   *
   * Reproduces the bug where adding a structural item (new component) inside
   * a non-default translation does not appear after reload. The default
   * translation must gain the full tree for the new delta — otherwise the
   * default-language render misses it entirely and the read-time merge in
   * SourceValueList::getValue() has no default baseline for the new delta.
   */
  public function testNewDeltaInTranslationPropagatesStructureToDefault(): void {
    // Default language starts with only delta 0.
    $this->node->set('field_source', [$this->testSourceTreeData[0]]);
    $this->node->save();

    // Create the German translation mirroring delta 0.
    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_node->save();

    // Add delta 1 only on the German translation — Drupal content-entity
    // translations carry independent delta lists, so the default side is
    // unchanged unless the field type explicitly propagates it.
    $german_node = $this->node->getTranslation('de');
    $german_values = $german_node->get('field_source')->getValue();
    $german_values[] = $this->testSourceTreeData[1];
    $german_node->set('field_source', $german_values);
    $german_node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertNotNull($reloaded);

    // Default translation must now have both deltas with the full tree.
    $english_values = $reloaded->getUntranslated()
      ->get('field_source')
      ->getValue();
    self::assertCount(2, $english_values, 'Default translation must gain the new delta added on the German side.');
    self::assertArrayHasKey(1, $english_values);
    self::assertArrayHasKey('source', $english_values[1]);
    self::assertArrayNotHasKey('translations', $english_values[1]['source'], 'Default translation must store the full tree, not a translation wrapper.');
    self::assertArrayHasKey('component', $english_values[1]['source'], 'Default translation must carry the structured component tree for the new delta.');

    // German read must expose the full merged tree for the new delta.
    $german_merged = $reloaded->getTranslation('de')
      ->get('field_source')
      ->getValue();
    self::assertCount(2, $german_merged, 'German translation must expose the new delta after reload.');
    self::assertArrayHasKey(1, $german_merged);
    self::assertArrayHasKey('component', $german_merged[1]['source'], 'German merged read must expose the structured component tree for the new delta.');
  }

  /**
   * Clearing all items on the default translation also empties translations.
   *
   * When the user removes every component from the default language, no
   * translated leaves can survive — there is nothing left to translate.
   * Reads on every translation must return an empty value, and raw items
   * must not retain a stale `{translations: …}` wrapper.
   */
  public function testEmptySetOnDefaultClearsTranslationsAcrossLanguages(): void {
    // Populate default and create a DE translation with a translated leaf.
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();
    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_values = $german_node->get('field_source')->getValue();
    $german_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'deutsch';
    $german_node->set('field_source', $german_values);
    $german_node->save();

    // Clear default language entirely.
    $this->node->set('field_source', []);
    $this->node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertNotNull($reloaded);

    $english_values = $reloaded->getUntranslated()
      ->get('field_source')
      ->getValue();
    self::assertSame([], $english_values, 'Default translation must be empty after clearing.');

    $german_values = $reloaded->getTranslation('de')
      ->get('field_source')
      ->getValue();
    self::assertSame([], $german_values, 'German translation must also be empty after the default is cleared.');
  }

  /**
   * Clearing all items on a translation keeps the default but drops overrides.
   *
   * When the user removes every component while editing the German side, the
   * default tree stays intact (other translations and the default-language
   * render must keep their structure) but the German raw storage must hold
   * no stale translations wrapper — an empty translation overlay.
   */
  public function testEmptySetOnTranslationClearsTranslationOverlay(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();
    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_values = $german_node->get('field_source')->getValue();
    $german_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'deutsch';
    $german_node->set('field_source', $german_values);
    $german_node->save();

    // Clear the German translation side only.
    $german_node = $this->node->getTranslation('de');
    $german_node->set('field_source', []);
    $german_node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertNotNull($reloaded);

    // Default translation must still hold the full English tree.
    $english_values = $reloaded->getUntranslated()
      ->get('field_source')
      ->getValue();
    self::assertNotEmpty($english_values, 'Default translation must survive a translation-side clear.');
    self::assertArrayHasKey(0, $english_values);
    self::assertArrayHasKey('component', $english_values[0]['source'], 'Default tree must remain structured.');
    self::assertSame(
      'english',
      $english_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'],
      'Default English leaf must be untouched.',
    );

    // German raw side must hold no stale wrapper.
    self::assertSame([], self::sourceList($reloaded->getTranslation('de'))->getTranslations(), 'German translations map must be empty after clearing.');
  }

  /**
   * Removing a delta on a translation propagates the deletion to the default.
   *
   * Structural mutations on a translation must mirror to the default tree
   * so the merge-fill in SourceValueList::getValue() cannot re-introduce
   * a node the user just deleted.
   */
  public function testDeleteOnTranslationPropagatesToDefault(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_node->save();

    $german_node = $this->node->getTranslation('de');
    $german_values = $german_node->get('field_source')->getValue();
    self::assertCount(2, $german_values, 'Fixture should expose both deltas before deletion.');
    unset($german_values[1]);
    $german_values = \array_values($german_values);
    $german_node->set('field_source', $german_values);
    $german_node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertNotNull($reloaded);

    $english_values = $reloaded->getUntranslated()
      ->get('field_source')
      ->getValue();
    self::assertCount(1, $english_values, 'Default translation must lose the deleted delta.');

    $german_values = $reloaded->getTranslation('de')
      ->get('field_source')
      ->getValue();
    self::assertCount(1, $german_values, 'German translation must expose the reduced structure after reload.');
  }

  /**
   * With synchronized_translation = FALSE, trees diverge freely.
   */
  public function testUnsynchronizedTranslationsDivergeFreely(): void {
    $field = FieldConfig::loadByName('node', 'page', 'field_source');
    self::assertInstanceOf(FieldConfig::class, $field);
    $field->setSetting('synchronized_translation', FALSE);
    $field->save();

    $this->node->set('field_source', [$this->testSourceTreeData[0]]);
    $this->node->save();

    // German translation replaces its tree with a structurally different one.
    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_values = $german_node->get('field_source')->getValue();
    $german_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'deutsch';
    $moved = $german_values[0]['source']['component']['slots']['content']['sources'][0];
    $german_values[0]['source']['component']['slots']['content']['sources'] = [];
    $german_values[0]['source']['component']['slots']['image']['sources'][0] = $moved;
    $german_node->set('field_source', $german_values);
    $german_node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertNotNull($reloaded);

    // No merge: DE keeps its own structure and leaf.
    $de_values = $reloaded->getTranslation('de')->get('field_source')->getValue();
    self::assertSame(
      'deutsch',
      $de_values[0]['source']['component']['slots']['image']['sources'][0]['source']['value']['value'],
      'Unsynchronized DE tree stands alone.',
    );

    // No cascade: EN keeps the original structure and leaf.
    $en_values = $reloaded->getUntranslated()->get('field_source')->getValue();
    self::assertSame(
      'english',
      $en_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'],
      'Default tree is untouched by the unsynchronized DE edit.',
    );
    self::assertEmpty(
      $en_values[0]['source']['component']['slots']['image']['sources'] ?? [],
      'Default must not adopt the DE structure.',
    );
  }

  /**
   * Moving a nested node on a translation propagates the move to the default.
   *
   * Move = node_id keeps but its parent path changes. The structural
   * signature differs, so the default tree must adopt the new placement.
   */
  public function testMoveOnTranslationPropagatesToDefault(): void {
    // Build a tree with two slots so we can move a node between them.
    $tree = [
      'source_id' => 'component',
      'node_id' => 'root',
      'source' => [
        'component' => [
          'component_id' => 'olivero:teaser',
          'variant_id' => NULL,
          'slots' => [
            'content' => [
              'sources' => [
                0 => [
                  'source_id' => 'wysiwyg',
                  'node_id' => 'movable',
                  'source' => ['value' => ['value' => 'english', 'format' => 'plain_text']],
                ],
              ],
              'add_more_button' => '',
            ],
            'image' => ['add_more_button' => ''],
            'meta' => ['add_more_button' => ''],
            'prefix' => ['add_more_button' => ''],
            'title' => ['add_more_button' => ''],
          ],
        ],
      ],
    ];
    $this->node->set('field_source', [$tree]);
    $this->node->save();

    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_node->save();

    // Move 'movable' from `content` slot to `image` slot on DE.
    $german_node = $this->node->getTranslation('de');
    $german_values = $german_node->get('field_source')->getValue();
    $moved = $german_values[0]['source']['component']['slots']['content']['sources'][0];
    unset($german_values[0]['source']['component']['slots']['content']['sources'][0]);
    $german_values[0]['source']['component']['slots']['content']['sources'] = \array_values($german_values[0]['source']['component']['slots']['content']['sources']);
    $german_values[0]['source']['component']['slots']['image']['sources'][0] = $moved;
    $german_node->set('field_source', $german_values);
    $german_node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertNotNull($reloaded);

    $english_values = $reloaded->getUntranslated()
      ->get('field_source')
      ->getValue();
    self::assertArrayHasKey(
      0,
      $english_values[0]['source']['component']['slots']['image']['sources'] ?? [],
      'Default translation must adopt the new parent slot for the moved node.',
    );
    self::assertEmpty(
      $english_values[0]['source']['component']['slots']['content']['sources'] ?? [],
      'Default translation must lose the moved node from its previous slot.',
    );
  }

  /**
   * Regression: the raw `source` column on the default translation is a tree.
   *
   * Guards against accidentally writing translation-side artefacts into
   * the original delta — downstream consumers reading the raw column must
   * always see the structured default tree.
   */
  public function testOriginalRawSourceIsTreeNotTranslationsWrapper(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $german_values = $german_node->get('field_source')->getValue();
    $german_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'deutsch';
    $german_node->set('field_source', $german_values);
    $german_node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());

    $raw = self::sourceList($reloaded->getUntranslated())->getRawValues()[0]['source'];

    self::assertIsArray($raw);
    self::assertArrayNotHasKey('translations', $raw, 'Raw source on the default translation must not be a translations wrapper.');
    self::assertArrayHasKey('component', $raw, 'Raw source on the default translation must still contain the component tree.');
  }

}
