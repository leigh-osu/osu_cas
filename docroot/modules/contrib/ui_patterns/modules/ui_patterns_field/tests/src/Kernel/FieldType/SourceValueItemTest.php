<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_field\Kernel\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_patterns_field\Plugin\Field\FieldType\SourceValueItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the SourceValueItem field type.
 *
 * @internal
 */
#[CoversClass(SourceValueItem::class)]
#[Group('ui_patterns')]
#[Group('ui_patterns_field')]
#[RunTestsInSeparateProcesses]
final class SourceValueItemTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_patterns',
    'ui_patterns_field',
    'field',
    'entity_test',
    'system',
    'user',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['field']);

    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'type' => 'ui_patterns_source',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Tests that mainPropertyName() returns 'source_id'.
   */
  public function testMainPropertyName(): void {
    self::assertEquals('source_id', SourceValueItem::mainPropertyName());
  }

  /**
   * Tests that propertyDefinitions() returns the expected properties.
   */
  public function testPropertyDefinitions(): void {
    $field_storage = FieldStorageConfig::loadByName('entity_test', 'field_test');
    $definitions = SourceValueItem::propertyDefinitions($field_storage);

    self::assertArrayHasKey('node_id', $definitions);
    self::assertArrayHasKey('source_id', $definitions);
    self::assertArrayHasKey('source', $definitions);
    self::assertArrayHasKey('third_party_settings', $definitions);

    self::assertEquals('string', $definitions['node_id']->getDataType());
    self::assertEquals('string', $definitions['source_id']->getDataType());
    self::assertEquals('map', $definitions['source']->getDataType());
    self::assertEquals('map', $definitions['third_party_settings']->getDataType());
  }

  /**
   * Tests that schema() returns the expected columns.
   */
  public function testSchema(): void {
    $field_storage = FieldStorageConfig::loadByName('entity_test', 'field_test');
    $schema = SourceValueItem::schema($field_storage);

    self::assertArrayHasKey('columns', $schema);
    $columns = $schema['columns'];

    self::assertArrayHasKey('node_id', $columns);
    self::assertArrayHasKey('source_id', $columns);
    self::assertArrayHasKey('source', $columns);
    self::assertArrayHasKey('third_party_settings', $columns);

    self::assertEquals('varchar_ascii', $columns['node_id']['type']);
    self::assertEquals('varchar_ascii', $columns['source_id']['type']);
    self::assertEquals('blob', $columns['source']['type']);
    self::assertEquals('blob', $columns['third_party_settings']['type']);
    self::assertTrue($columns['source']['serialize']);
    self::assertTrue($columns['third_party_settings']['serialize']);
  }

  /**
   * Tests that setValue() silently ignores values with an empty source_id.
   */
  public function testSetValueIgnoresEmptySourceId(): void {
    $entity = EntityTest::create();
    $entity->field_test->setValue([
      ['source_id' => '', 'source' => ['some' => 'data']],
    ]);
    self::assertTrue($entity->field_test->isEmpty());
  }

  /**
   * Tests that setValue() stores values when source_id is set.
   */
  public function testSetValueStoresWithSourceId(): void {
    $entity = EntityTest::create();
    $field = $entity->get('field_test');
    $field->setValue([
      'source_id' => 'component',
      'source' => [
        'component' => [
          'component_id' => 'ui_patterns_test:test-component',
        ],
      ],
    ]);

    self::assertInstanceOf(FieldItemListInterface::class, $field);
    self::assertInstanceOf(FieldItemInterface::class, $field[0]);
    self::assertFalse($field->isEmpty());
    self::assertEquals('component', $field->source_id);
  }

  /**
   * Tests isEmpty() after entity save/load, which uses the properties path.
   */
  public function testIsEmptyAfterLoadWithSourceId(): void {
    $entity = EntityTest::create();
    $entity->get('field_test')->setValue([
      'source_id' => 'component',
      'source' => [
        'component' => [
          'component_id' => 'ui_patterns_test:test-component',
        ],
      ],
    ]);
    $entity->save();

    $loaded = EntityTest::load($entity->id());
    self::assertFalse($loaded->get('field_test')->isEmpty());
    self::assertEquals('component', $loaded->get('field_test')->source_id);
  }

}
