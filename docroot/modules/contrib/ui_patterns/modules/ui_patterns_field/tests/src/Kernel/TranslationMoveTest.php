<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_field\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drupal\Tests\ui_patterns\Kernel\SourceTree\TranslationBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Translations survive node moves — across deltas, slots and languages.
 *
 * All scenarios follow save → storage cache reset → reload from DB →
 * assert, so persistence is proven, not just in-memory state.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns_field')]
#[RunTestsInSeparateProcesses]
final class TranslationMoveTest extends TranslationBase {

  /**
   * Reloads the test node from storage with caches reset.
   */
  private function reloadNode(): NodeInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertNotNull($reloaded);
    self::assertInstanceOf(NodeInterface::class, $reloaded);
    return $reloaded;
  }

  /**
   * Creates a DE translation with the delta-0 wysiwyg leaf translated.
   */
  private function createGermanTranslation(string $leaf_value): void {
    $de = $this->node->addTranslation('de', $this->node->toArray());
    $de->setTitle('deutsch');
    $values = $de->get('field_source')->getValue();
    $values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = $leaf_value;
    $de->set('field_source', $values);
    $de->save();
  }

  /**
   * A node moved to another delta on the default side keeps its translation.
   */
  public function testTranslationSurvivesCrossDeltaMove(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();
    $this->createGermanTranslation('deutsch');

    // Move the wysiwyg node (node-2) from delta 0 into delta 1's content
    // slot on the default language.
    $en_values = $this->node->get('field_source')->getValue();
    $moved = $en_values[0]['source']['component']['slots']['content']['sources'][0];
    $en_values[0]['source']['component']['slots']['content']['sources'] = [];
    $en_values[1]['source']['component']['slots']['content']['sources'][] = $moved;
    $this->node->set('field_source', $en_values);
    $this->node->save();

    $reloaded = $this->reloadNode();
    $de_values = $reloaded->getTranslation('de')->get('field_source')->getValue();
    $delta1_sources = $de_values[1]['source']['component']['slots']['content']['sources'];
    $found = FALSE;
    foreach ($delta1_sources as $source) {
      if (($source['node_id'] ?? '') === 'node-2') {
        $found = TRUE;
        self::assertSame(
          'deutsch',
          $source['source']['value']['value'],
          'Translation survives the cross-delta move.',
        );
      }
    }
    self::assertTrue($found, 'Moved node-2 must appear in delta 1 on the DE read.');
  }

  /**
   * A node moved to another slot within the same delta keeps its translation.
   */
  public function testTranslationSurvivesMoveWithinDelta(): void {
    $this->node->set('field_source', [$this->testSourceTreeData[0]]);
    $this->node->save();
    $this->createGermanTranslation('deutsch');

    // Move the wysiwyg node (node-2) from the content slot to the image
    // slot on the default language.
    $en_values = $this->node->get('field_source')->getValue();
    $moved = $en_values[0]['source']['component']['slots']['content']['sources'][0];
    $en_values[0]['source']['component']['slots']['content']['sources'] = [];
    $en_values[0]['source']['component']['slots']['image']['sources'][0] = $moved;
    $this->node->set('field_source', $en_values);
    $this->node->save();

    $reloaded = $this->reloadNode();
    $de_values = $reloaded->getTranslation('de')->get('field_source')->getValue();
    self::assertSame(
      'deutsch',
      $de_values[0]['source']['component']['slots']['image']['sources'][0]['source']['value']['value'],
      'Translation survives the move into another slot — keys are relative to the node.',
    );
  }

  /**
   * A move on the translation side cascades; default strings survive.
   */
  public function testDefaultLeavesSurviveTranslationSideMove(): void {
    $this->node->set('field_source', [$this->testSourceTreeData[0]]);
    $this->node->save();
    $this->createGermanTranslation('deutsch');

    // Move the wysiwyg node on the DE side from content to image slot.
    $de = $this->node->getTranslation('de');
    $de_values = $de->get('field_source')->getValue();
    $moved = $de_values[0]['source']['component']['slots']['content']['sources'][0];
    $de_values[0]['source']['component']['slots']['content']['sources'] = [];
    $de_values[0]['source']['component']['slots']['image']['sources'][0] = $moved;
    $de->set('field_source', $de_values);
    $de->save();

    $reloaded = $this->reloadNode();

    // Default adopts the structure but keeps its English leaf.
    $en_values = $reloaded->getUntranslated()->get('field_source')->getValue();
    self::assertSame(
      'english',
      $en_values[0]['source']['component']['slots']['image']['sources'][0]['source']['value']['value'],
      'Default leaf string survives the translation-side move.',
    );
    self::assertSame(
      [],
      $en_values[0]['source']['component']['slots']['content']['sources'] ?? 'MISSING',
      'Default loses the node from its previous slot (key must exist and be an empty list).',
    );

    // DE read stays consistent.
    $de_values = $reloaded->getTranslation('de')->get('field_source')->getValue();
    self::assertSame(
      'deutsch',
      $de_values[0]['source']['component']['slots']['image']['sources'][0]['source']['value']['value'],
      'DE read exposes the translated leaf at the new position.',
    );
  }

  /**
   * A default-side move keeps translations across multiple languages.
   */
  public function testMoveSurvivesAcrossMultipleLanguages(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->node->set('field_source', [$this->testSourceTreeData[0]]);
    $this->node->save();
    // Reload the node so the entity's language list reflects the newly
    // installed FR language (the in-memory object was created before FR
    // existed).
    $this->node = $this->reloadNode();
    $this->createGermanTranslation('deutsch');

    $fr = $this->node->addTranslation('fr', $this->node->toArray());
    $fr->setTitle('francais');
    $fr_values = $fr->get('field_source')->getValue();
    $fr_values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'francais';
    $fr->set('field_source', $fr_values);
    $fr->save();

    // Move the node on the default side from content to image slot.
    $en_values = $this->node->get('field_source')->getValue();
    $moved = $en_values[0]['source']['component']['slots']['content']['sources'][0];
    $en_values[0]['source']['component']['slots']['content']['sources'] = [];
    $en_values[0]['source']['component']['slots']['image']['sources'][0] = $moved;
    $this->node->set('field_source', $en_values);
    $this->node->save();

    $reloaded = $this->reloadNode();
    $de_values = $reloaded->getTranslation('de')->get('field_source')->getValue();
    self::assertSame(
      'deutsch',
      $de_values[0]['source']['component']['slots']['image']['sources'][0]['source']['value']['value'],
      'DE translation survives the default-side move.',
    );
    $fr_values = $reloaded->getTranslation('fr')->get('field_source')->getValue();
    self::assertSame(
      'francais',
      $fr_values[0]['source']['component']['slots']['image']['sources'][0]['source']['value']['value'],
      'FR translation survives the default-side move.',
    );
  }

}
