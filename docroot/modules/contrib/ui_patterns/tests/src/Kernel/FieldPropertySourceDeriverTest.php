<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Tests\ui_patterns\Traits\TestContentCreationTrait;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldPropertySource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests UI patterns field properties plugin deriver.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class FieldPropertySourceDeriverTest extends SourcePluginsTestBase {

  use TestContentCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ui_patterns', 'ui_patterns_test'];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = TRUE;

  /**
   * The source plugin manager.
   *
   * @var \Drupal\ui_patterns\SourcePluginManager
   */
  protected $sourceManager;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManager
   */
  protected $fieldTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The ui patterns prop type plugin manager.
   *
   * @var \Drupal\ui_patterns\PropTypePluginManager
   */
  protected $propTypePluginManager;

  /**
   * The bundle.
   *
   * @var string
   */
  protected $bundle = 'page';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fieldTypeManager = $this->container->get('plugin.manager.field.field_type');
    $this->sourceManager = $this->container->get('plugin.manager.ui_patterns_source');
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->propTypePluginManager = $this->container->get('plugin.manager.ui_patterns_prop_type');
    $this->bundle = $this->randomMachineName();
    $this->createTestContentContentType($this->bundle);
  }

  /**
   * Tests creating fields of all types on a content type.
   */
  public function testDerivedPluginPerFieldType() {
    $field_maps = $this->entityFieldManager->getFieldMap();
    $this->sourceManager->clearCachedDefinitions();
    $definitions = $this->sourceManager->getDefinitions();

    foreach ($field_maps as $entity_type_id => $field_map) {
      $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);

      foreach (\array_keys($field_map) as $field_name) {
        if (!\array_key_exists($field_name, $field_storage_definitions)) {
          continue;
        }
        $field_storage_definition = $field_storage_definitions[$field_name];

        foreach ($field_storage_definition->getPropertyDefinitions() as $property_id => $property_definition) {
          $prop_types = $this->propTypePluginManager->getAllPropTypeByTypedData($property_definition->getDataType());

          if (\count($prop_types) === 0) {
            continue;
          }
          $plugin_id = \implode(PluginBase::DERIVATIVE_SEPARATOR, [
            'field_property',
            $entity_type_id,
            $field_name,
            $property_id,
          ]);
          self::assertContains($plugin_id, \array_keys($definitions), \implode("\n", \array_keys($definitions)));
          self::assertTrue($this->sourceManager->hasDefinition($plugin_id));
          $plugin = $this->sourceManager->getDefinition($plugin_id);
          self::assertEquals(FieldPropertySource::class, $plugin['class']);
          self::assertIsArray($plugin['prop_types']);
          $prop_types = $plugin['prop_types'];
          self::assertTrue(\count($prop_types) > 0);
          self::assertIsArray($plugin['context_definitions']);
          $context_definitions = $plugin['context_definitions'];
          self::assertArrayHasKey('entity', $context_definitions);
          self::assertArrayHasKey('bundle', $context_definitions);
          self::assertArrayHasKey('field_name', $context_definitions);
          self::assertArrayHasKey('context_requirements', $context_definitions);
          self::assertCount(4, $context_definitions);
          $entity_context = $context_definitions['entity'];
          self::assertInstanceOf(EntityContextDefinition::class, $entity_context);
          /** @var \Drupal\Core\Plugin\Context\ContextDefinition $bundle_context */
          $bundle_context = $context_definitions['bundle'];
          $constraints = $bundle_context->getConstraints();
          self::assertArrayHasKey('AllowedValues', $constraints);
          self::assertContains('', $constraints['AllowedValues']);
          $field_name_context = $context_definitions['field_name'];
          self::assertInstanceOf(ContextDefinition::class, $field_name_context);
          self::assertArrayHasKey('AllowedValues', $field_name_context->getConstraints());
          self::assertContains($field_name, $field_name_context->getConstraints()['AllowedValues']);
          self::assertIsArray($plugin['metadata']);
          $metadata = $plugin['metadata'];
          self::assertArrayHasKey('field', $metadata);
          self::assertArrayHasKey('field_name', $metadata);
        }
      }
    }
  }

}
