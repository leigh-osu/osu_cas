<?php

declare(strict_types=1);

namespace Drupal\preview_link\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\preview_link\PreviewLinkUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preview link task generation.
 */
final class PreviewLinkTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Creates a new PreviewLinkTasks.
   *
   * @param string $basePluginId
   *   The base plugin ID.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The translation manager.
   */
  public function __construct(
    protected string $basePluginId,
    protected EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): self {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager'),
      $container->get('string_translation'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$this->supportsPreviewLink($entity_type)) {
        continue;
      }

      $this->derivatives["$entity_type_id.preview_link_generate"] = [
        'route_name' => "entity.$entity_type_id.preview_link_generate",
        'title' => $this->t('Preview Link'),
        'base_route' => "entity.$entity_type_id.canonical",
        'weight' => 30,
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

  /**
   * Check if the entity type is supported.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type we're checking.
   *
   * @return bool
   *   TRUE if it supports previews otherwise FALSE.
   */
  protected function supportsPreviewLink(EntityTypeInterface $entityType): bool {
    return PreviewLinkUtility::isEntityTypeSupported($entityType);
  }

}
