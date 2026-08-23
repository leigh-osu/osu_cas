<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_field\Kernel\Update;

use Drupal\Core\Database\Database;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update hook for ui_patterns_field.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_field')]
#[RunTestsInSeparateProcesses]
final class UpdateHookTest extends KernelTestBase {

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

    require_once \Drupal::service('extension.list.module')->getPath('ui_patterns_field') . '/ui_patterns_field.install';
  }

  /**
   * Tests ui_patterns_field_update_10201().
   */
  public function testUpdate10201(): void {
    // 1. Create a field of type ui_patterns_source.
    $field_name = 'field_source';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'ui_patterns_source',
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Source',
    ])->save();

    // Get the table name.
    $table_name = "entity_test__{$field_name}";
    $schema = Database::getConnection()->schema();

    // 2. Verify initial state.
    self::assertTrue($schema->fieldExists($table_name, "{$field_name}_third_party_settings"), 'Column third_party_settings should initially exist.');
    self::assertTrue($schema->fieldExists($table_name, "{$field_name}_node_id"), 'Column node_id should initially exist.');

    // 3. Simulate pre-update state by dropping the columns.
    $schema->dropField($table_name, "{$field_name}_third_party_settings");
    $schema->dropField($table_name, "{$field_name}_node_id");

    self::assertFalse($schema->fieldExists($table_name, "{$field_name}_third_party_settings"), 'Column third_party_settings should be gone.');
    self::assertFalse($schema->fieldExists($table_name, "{$field_name}_node_id"), 'Column node_id should be gone.');

    // 4. Run the update hook.
    ui_patterns_field_update_10201();

    // 5. Assert that the correct columns were created.
    self::assertTrue(
      $schema->fieldExists($table_name, "{$field_name}_third_party_settings"),
      "The column '{$field_name}_third_party_settings' should exist after update."
    );
    self::assertTrue(
      $schema->fieldExists($table_name, "{$field_name}_node_id"),
      "The column '{$field_name}_node_id' should exist after update."
    );
  }

}
