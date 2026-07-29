<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\node\Entity\NodeType;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldFormatterSource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests UI patterns field formatters plugin deriver.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class SourcesDeriverTest extends SourcePluginsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fieldTypeManager = $this->container->get('plugin.manager.field.field_type');
    $this->sourceManager = $this->container->get('plugin.manager.ui_patterns_source');
  }

  /**
   * Tests creating fields of all types on a content type.
   */
  public function testDerivedPluginPerFieldType() {
    $field_types = $this->fieldTypeManager->getDefinitions();
    $entityTypes = [NodeType::load('page')];

    foreach (\array_keys($field_types) as $field_type_id) {
      if ($field_type_id === 'uuid') {
        continue;
      }
      $field_name = 'field_' . $field_type_id;

      foreach ($entityTypes as $oneEntityType) {
        $bundle = $oneEntityType->id();
        $entity_type_id = $oneEntityType->getEntityType()->getBundleOf();
        // Load the field and check if it was created.
        $field = \Drupal::entityTypeManager()
          ->getStorage('field_config')
          ->load($entity_type_id . '.' . $bundle . '.' . $field_name);

        self::assertNotNull($field, "Field of type {$field_type_id} is missing on {$entity_type_id} {$bundle}");

        // Ensure the reusable block content is provided as a derivative block
        // plugin.
        $this->sourceManager->clearCachedDefinitions();
        $definitions = $this->sourceManager->getDefinitions();
        $plugin_id = \implode(PluginBase::DERIVATIVE_SEPARATOR, [
          'field_formatter',
          $entity_type_id,
          $bundle,
          $field_name,
        ]);
        $plugin_id_storage = \implode(PluginBase::DERIVATIVE_SEPARATOR, [
          'field_formatter',
          $entity_type_id,
          '',
          $field_name,
        ]);
        self::assertContains($plugin_id, \array_keys($definitions), \implode("\n", \array_keys($definitions)));
        self::assertContains($plugin_id_storage, \array_keys($definitions), \implode("\n", \array_keys($definitions)));
        self::assertTrue($this->sourceManager->hasDefinition($plugin_id));
        self::assertTrue($this->sourceManager->hasDefinition($plugin_id_storage));
        $plugin_bundle = $this->sourceManager->getDefinition($plugin_id);
        $plugin_storage = $this->sourceManager->getDefinition($plugin_id_storage);

        foreach ([$plugin_bundle, $plugin_storage] as $plugin) {
          self::assertEquals(FieldFormatterSource::class, $plugin['class']);
          self::assertIsArray($plugin['prop_types']);
          $prop_types = $plugin['prop_types'];
          self::assertContains('slot', $prop_types);
          self::assertCount(1, $prop_types);
          self::assertIsArray($plugin['context_definitions']);
          $context_definitions = $plugin['context_definitions'];
          self::assertArrayHasKey('entity', $context_definitions);
          self::assertArrayHasKey('bundle', $context_definitions);
          self::assertArrayHasKey('field_name', $context_definitions);
          self::assertCount(3, $context_definitions);
          $entity_context = $context_definitions['entity'];
          self::assertInstanceOf(EntityContextDefinition::class, $entity_context);
          /** @var \Drupal\Core\Plugin\Context\ContextDefinition $bundle_context */
          $bundle_context = $context_definitions['bundle'];
          $constraints = $bundle_context->getConstraints();
          self::assertArrayHasKey('AllowedValues', $constraints);
          self::assertContains(($plugin === $plugin_bundle) ? $bundle : '', $constraints['AllowedValues']);
          $field_name_context = $context_definitions['field_name'];
          self::assertInstanceOf(ContextDefinition::class, $field_name_context);
          self::assertArrayHasKey('AllowedValues', $field_name_context->getConstraints());
          self::assertContains($field_name, $field_name_context->getConstraints()['AllowedValues']);
          self::assertIsArray($plugin['metadata']);
          $metadata = $plugin['metadata'];
          self::assertArrayHasKey('field', $metadata);
          self::assertArrayHasKey('field_name', $metadata);
          self::assertArrayHasKey('field_formatter', $metadata);
        }
      }
    }
  }

}
