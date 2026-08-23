<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Provides static helpers for working with trashed entities.
 */
final class Trash {

  /**
   * Determines whether an entity is deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   *
   * @return bool
   *   TRUE if the entity is deleted, FALSE otherwise.
   */
  public static function entityIsDeleted(EntityInterface $entity): bool {
    if (!$entity instanceof FieldableEntityInterface) {
      return FALSE;
    }

    return $entity->getFieldDefinition('deleted')?->getFieldStorageDefinition()->getProvider() === 'trash'
      && !$entity->get('deleted')->isEmpty();
  }

  /**
   * Restores the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures, an exception is thrown.
   */
  public static function restoreEntity(EntityInterface $entity): void {
    // Check the entity type only, not the bundle, so restore still works after
    // a bundle is dropped from trash.settings.
    if (!\Drupal::service('trash.manager')->isEntityTypeEnabled($entity->getEntityType())) {
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    $storage->restoreFromTrash([$entity]);
  }

}
