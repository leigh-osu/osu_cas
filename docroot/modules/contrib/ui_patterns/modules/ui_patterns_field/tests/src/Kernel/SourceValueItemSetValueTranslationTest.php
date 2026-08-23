<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_field\Kernel;

use Drupal\Tests\ui_patterns\Kernel\SourceTree\TranslationBase;
use Drupal\ui_patterns_field\Field\SourceValueList;
use Drupal\ui_patterns_field\Plugin\Field\FieldType\SourceValueItem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests SourceValueItem::setValue() singular flow with translations.
 *
 * Covers the per-item setValue path (as opposed to the field-list-level
 * $entity->set(...)) and verifies it produces the right storage shape on
 * default and non-default translations, plus the input normalization
 * branches (serialized string, field item instance).
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns_field')]
#[RunTestsInSeparateProcesses]
final class SourceValueItemSetValueTranslationTest extends TranslationBase {

  /**
   * Singular setValue on the default translation stores the full tree.
   */
  public function testSetValueOnDefaultStoresFullTree(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $item = $this->node->get('field_source')->get(0);
    self::assertInstanceOf(SourceValueItem::class, $item);
    $values = $item->getValue();
    $values['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'changed english';
    $item->setValue($values);
    $this->node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    $raw = self::sourceList($reloaded->getUntranslated())
      ->getRawValues()[0]['source'];

    self::assertArrayHasKey('component', $raw, 'Default translation must keep the structured tree.');
    self::assertArrayNotHasKey('translations', $raw, 'Default translation must not carry the translations wrapper.');
    self::assertSame(
      'changed english',
      $raw['component']['slots']['content']['sources'][0]['source']['value']['value'],
    );
  }

  /**
   * Singular setValue on a non-default translation stores the full tree.
   */
  public function testSetValueOnNonDefaultStoresFullTree(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $de = $this->node->addTranslation('de', $this->node->toArray());
    $de->setTitle('deutsch');
    $de->save();

    $de = $this->node->getTranslation('de');
    $item = $de->get('field_source')->get(0);
    self::assertInstanceOf(SourceValueItem::class, $item);
    $values = $item->getValue();
    $values['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'deutsch';
    $item->setValue($values);
    $de->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());

    $de_raw = self::sourceList($reloaded->getTranslation('de'))
      ->getRawValues()[0]['source'];
    self::assertArrayHasKey('component', $de_raw, 'DE translation stores the full structured tree.');
    self::assertSame(
      'deutsch',
      $de_raw['component']['slots']['content']['sources'][0]['source']['value']['value'],
      'DE raw tree carries the translated leaf as entered.',
    );

    $en_raw = self::sourceList($reloaded->getUntranslated())
      ->getRawValues()[0]['source'];
    self::assertArrayHasKey('component', $en_raw, 'Default translation must keep the structured tree.');
    self::assertSame(
      'english',
      $en_raw['component']['slots']['content']['sources'][0]['source']['value']['value'],
      'Default translation leaf value must survive the DE write.',
    );

    $de_merged = $reloaded->getTranslation('de')
      ->get('field_source')
      ->getValue()[0];
    self::assertSame(
      'deutsch',
      $de_merged['source']['component']['slots']['content']['sources'][0]['source']['value']['value'],
      'DE merged view returns the translated leaf.',
    );
  }

  /**
   * Singular setValue accepts a serialized string and stores the tree.
   */
  public function testSetValueWithSerializedString(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $item = $this->node->get('field_source')->get(0);
    self::assertInstanceOf(SourceValueItem::class, $item);
    $values = $item->getValue();
    $values['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'via serialized';
    // normalizeToArray() supports serialized payloads even though the public
    // signature is array|null — the runtime contract is intentionally wider.
    // @phpstan-ignore-next-line argument.type
    $item->setValue(\serialize($values));
    $this->node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    $raw = self::sourceList($reloaded->getUntranslated())
      ->getRawValues()[0]['source'];
    self::assertSame(
      'via serialized',
      $raw['component']['slots']['content']['sources'][0]['source']['value']['value'],
    );
  }

  /**
   * Asserts getTranslations() returns [] on the default-translation list.
   */
  public function testGetTranslationsReturnsEmptyOnDefault(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $list = $this->node->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $list);

    self::assertSame([], $list->getTranslations(), 'Default translation has no translations wrapper.');
  }

  /**
   * Asserts getTranslations() returns the flat map on a translated list.
   */
  public function testGetTranslationsReturnsMapOnNonDefault(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $de = $this->node->addTranslation('de', $this->node->toArray());
    $de->setTitle('deutsch');
    $de->save();
    $values = $de->get('field_source')->getValue();
    $values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'deutsch';
    $de->set('field_source', $values);
    $de->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());

    $de_list = $reloaded->getTranslation('de')->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $de_list);

    $translations = $de_list->getTranslations();
    self::assertNotEmpty($translations, 'Non-default translation exposes its translations map.');
    self::assertContains(
      'deutsch',
      $translations,
      'Translated leaf value appears in the translations map.',
    );
  }

  /**
   * Singular setValue accepts another field item instance.
   */
  public function testSetValueWithFieldItemInstance(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $item = $this->node->get('field_source')->get(0);
    $other = $this->node->get('field_source')->get(1);
    self::assertInstanceOf(SourceValueItem::class, $item);
    self::assertInstanceOf(SourceValueItem::class, $other);

    // normalizeToArray() unwraps a FieldItemInterface input; widen the
    // declared type to match the runtime contract.
    // @phpstan-ignore-next-line argument.type
    $item->setValue($other);

    $current = $item->getValue();
    self::assertArrayHasKey('source', $current);
    self::assertSame(
      'node-3',
      $current['node_id'],
      'setValue() must adopt the other item value, including its node_id.',
    );
  }

}
