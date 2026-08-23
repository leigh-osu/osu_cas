<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_field\Kernel;

use Drupal\Tests\ui_patterns\Kernel\SourceTree\TranslationBase;
use Drupal\ui_patterns_field\Field\SourceValueList;
use Drupal\ui_patterns_field\Plugin\Field\FieldType\SourceValueItem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the raw source accessors on SourceValueItem / SourceValueList.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns_field')]
#[RunTestsInSeparateProcesses]
final class SourceValueListTest extends TranslationBase {

  /**
   * Tests getRawSources() on the default translation returns the full tree.
   */
  public function testGetRawSourcesOnDefaultReturnsFullTree(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $list = $this->node->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $list);

    $raw = $list->getRawSources()[0]['source'];
    self::assertArrayHasKey('component', $raw, 'Default-Translation carries the structured tree.');
  }

  /**
   * Tests getRawSources() on a non-default translation returns the full tree.
   */
  public function testGetRawSourcesOnNonDefaultReturnsFullTree(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();
    $de = $this->node->addTranslation('de', $this->node->toArray());
    $values = $de->get('field_source')->getValue();
    $values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'deutsch';
    $de->set('field_source', $values);
    $de->save();

    $de = $this->node->getTranslation('de');
    $list = $de->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $list);
    $raw = $list->getRawSources()[0]['source'];
    self::assertArrayHasKey('component', $raw, 'Non-default translation stores the full structured tree.');
    self::assertSame(
      'deutsch',
      $raw['component']['slots']['content']['sources'][0]['source']['value']['value'],
      'Raw DE tree carries the translated leaf.',
    );
  }

  /**
   * Tests getRawSources() iterates over all deltas.
   */
  public function testGetRawSourcesIteratesAllDeltas(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $list = $this->node->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $list);
    $rawSources = $list->getRawSources();

    self::assertCount(\count($list), $rawSources, 'Result has one entry per delta.');
    foreach ($rawSources as $delta => $entry) {
      self::assertIsInt($delta);
      self::assertArrayHasKey('source', $entry);
    }
  }

  /**
   * Tests getTranslations() returns [] on the default translation.
   */
  public function testGetTranslationsEmptyOnDefault(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();

    $list = $this->node->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $list);

    self::assertSame([], $list->getTranslations(), 'Default translation has no translations.');
  }

  /**
   * Tests getTranslations() aggregates translations across all deltas.
   */
  public function testGetTranslationsAggregatesDeltas(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();
    $de = $this->node->addTranslation('de', $this->node->toArray());
    $values = $de->get('field_source')->getValue();
    // Translate one leaf in delta 0 and one leaf deep inside delta 1.
    $values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'delta0-de';
    $values[1]['source']['component']['slots']['content']['sources'][0]['source']['component']['slots']['content']['sources'][0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'delta1-de';
    $de->set('field_source', $values);
    $de->save();

    $de = $this->node->getTranslation('de');
    $list = $de->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $list);

    $translations = $list->getTranslations();
    self::assertNotEmpty($translations, 'Aggregated translations map must not be empty.');
    self::assertContains('delta0-de', $translations, 'Delta 0 translation is aggregated.');
    self::assertContains('delta1-de', $translations, 'Delta 1 translation is aggregated.');
    foreach (\array_keys($translations) as $key) {
      self::assertIsString($key);
      self::assertStringContainsString(':', (string) $key, 'Keys carry the node_id:path shape.');
    }
  }

  /**
   * Tests buildTranslationsMap() aggregates leaves across deltas (field-wide).
   */
  public function testBuildTranslationsMapFieldWide(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();
    $de = $this->node->addTranslation('de', $this->node->toArray());
    $values = $de->get('field_source')->getValue();
    $values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'delta0-de';
    $values[1]['source']['component']['slots']['content']['sources'][0]['source']['component']['slots']['content']['sources'][0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'delta1-de';
    $de->set('field_source', $values);

    $list = $de->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $list);
    $map = $list->buildTranslationsMap();

    self::assertContains('delta0-de', $map, 'Delta 0 leaf is in the field-wide map.');
    self::assertContains('delta1-de', $map, 'Deep delta 1 leaf is in the field-wide map.');
    foreach (\array_keys($map) as $key) {
      self::assertIsString($key);
      self::assertStringContainsString(':', (string) $key, 'Keys carry the node_id:path shape.');
    }
  }

  /**
   * The memoized map self-invalidates when raw values change.
   */
  public function testBuildTranslationsMapMemoInvalidatesOnChange(): void {
    $this->node->set('field_source', $this->testSourceTreeData);
    $this->node->save();
    $de = $this->node->addTranslation('de', $this->node->toArray());
    $values = $de->get('field_source')->getValue();
    $values[0]['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'erste fassung';
    $de->set('field_source', $values);

    $list = $de->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $list);
    self::assertContains('erste fassung', $list->buildTranslationsMap());

    // Mutate one item directly — no list-level setValue involved.
    $item = $list->get(0);
    self::assertInstanceOf(SourceValueItem::class, $item);
    $raw = $item->getValue();
    $raw['source']['component']['slots']['content']['sources'][0]['source']['value']['value'] = 'zweite fassung';
    $item->setValue($raw);

    $map = $list->buildTranslationsMap();
    self::assertContains('zweite fassung', $map, 'Fingerprint memo rebuilds after a raw mutation.');
    self::assertNotContains('erste fassung', $map, 'Stale value must be gone.');
  }

}
