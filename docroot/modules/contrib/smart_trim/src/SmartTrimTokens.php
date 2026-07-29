<?php

namespace Drupal\smart_trim;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\token\TokenEntityMapperInterface;

/**
 * Service for smart_trim tokens.
 */
class SmartTrimTokens {

  use StringTranslationTrait;

  /**
   * Constructs a new SmartTrimTokens object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected TokenEntityMapperInterface $tokenEntityMapper,
    protected EntityRepositoryInterface $entityRepository,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected FormatterPluginManager $formatterPluginManager,
    protected RendererInterface $renderer,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
  ) {
  }

  /**
   * Implements hook_token_info_alter().
   */
  public function tokenInfoAlter(array &$info): void {
    // Attach smart trim tokens to their respective entity tokens.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }

      // Make sure a token type exists for this entity.
      $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($entity_type_id);
      if (empty($token_type) || !isset($info['types'][$token_type])) {
        continue;
      }

      $fields = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      foreach ($fields as $field_name => $field) {
        assert($field instanceof FieldStorageDefinitionInterface);
        $labels = $this->getFieldLabels($entity_type_id, $field_name);
        $label = array_shift($labels);

        if ($field->getType() === 'text_with_summary') {
          $info['tokens'][$token_type][$field_name . '-smart-trim'] = [
            'name' => $this->t('@label (Smart trim summary)', ['@label' => $label]),
            'description' => $this->t('Smart trimmed version of the field or the summary.'),
          ];
        }
      }
    }
  }

  /**
   * Implements hook_tokens().
   */
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];

    $langcode = $options['langcode'] ?? NULL;
    // Entity tokens.
    if ($type === 'entity' && !empty($data['entity_type']) && !empty($data['entity']) && !empty($data['token_type'])) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $data['entity'];
      if (!($entity instanceof ContentEntityInterface)) {
        return $replacements;
      }

      if (!isset($options['langcode'])) {
        // Set the active language in $options so that it is passed along.
        $langcode = $options['langcode'] = $entity->language()->getId();
      }
      // Get the entity with the correct language.
      $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);

      foreach ($tokens as $name => $original) {
        $field_name = str_replace('-smart-trim', '', $name);
        if (!str_contains($name, '-smart-trim')) {
          continue;
        }
        if (!$entity->hasField($field_name)) {
          continue;
        }

        // If a token view mode is set up, use its display settings. Otherwise,
        // fallback to defaults.
        $display_options = $this->entityDisplayRepository
          ->getViewDisplay($data['entity_type'], $entity->bundle(), 'token')
          ->getComponent($field_name);
        if (empty($display_options['type']) || $display_options['type'] !== 'smart_trim') {
          $display_options = [
            'type' => 'smart_trim',
            'label' => 'hidden',
            'settings' => $this->formatterPluginManager->getDefaultSettings('smart_trim'),
          ];
        }
        $field_output = $this->entityTypeManager
          ->getViewBuilder($data['entity_type'])
          ->viewField($entity->get($field_name), $display_options);
        $field_output['#token_options'] = $options;

        // Use renderInIsolation as renderPlain is deprecated in 10.3 and
        // removed in D12.
        $field_output_renderer = $this->renderer->renderInIsolation($field_output);

        $replacements[$original] = $field_output_renderer;
      }
    }

    return $replacements;
  }

  /**
   * Returns the label of a certain field.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   An array of labels.
   */
  protected function getFieldLabels(string $entity_type, string $field_name): array {
    if (method_exists($this->entityFieldManager, 'getFieldLabels')) {
      return $this->entityFieldManager->getFieldLabels($entity_type, $field_name);
    }

    $labels = [];
    foreach (array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type)) as $bundle) {
      $bundle_instances = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
      if (isset($bundle_instances[$field_name])) {
        $instance = $bundle_instances[$field_name];
        $label = (string) $instance->getLabel();
        $labels[$label] = isset($labels[$label]) ? ++$labels[$label] : 1;
      }
    }

    if (empty($labels)) {
      return [$field_name];
    }

    arsort($labels);
    return array_keys($labels);
  }

}
