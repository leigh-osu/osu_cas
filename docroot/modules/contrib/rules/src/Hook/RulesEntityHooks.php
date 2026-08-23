<?php

declare(strict_types=1);

namespace Drupal\rules\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\rules\Event\EntityEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Hook implementations used to create and dispatch Entity Events.
 */
final class RulesEntityHooks {

  /**
   * Constructs a new RulesEntityHooks service.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event_dispatcher service.
   */
  public function __construct(
    protected EventDispatcherInterface $dispatcher,
  ) {}

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode): void {
    // Only handle content entities and ignore config entities.
    if ($entity instanceof ContentEntityInterface) {
      $entity_type_id = $entity->getEntityTypeId();
      if ($this->dispatcher->hasListeners("rules_entity_view:$entity_type_id")) {
        $event = new EntityEvent($entity, [$entity_type_id => $entity]);
        $this->dispatcher->dispatch($event, "rules_entity_view:$entity_type_id");
      }
    }
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    // Only handle content entities and ignore config entities.
    if ($entity instanceof ContentEntityInterface) {
      $entity_type_id = $entity->getEntityTypeId();
      if ($this->dispatcher->hasListeners("rules_entity_presave:$entity_type_id")) {
        $event = new EntityEvent($entity, [
          $entity_type_id => $entity,
          $entity_type_id . '_unchanged' => $entity->original,
        ]);
        $this->dispatcher->dispatch($event, "rules_entity_presave:$entity_type_id");
      }
    }
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    // Only handle content entities and ignore config entities.
    if ($entity instanceof ContentEntityInterface) {
      $entity_type_id = $entity->getEntityTypeId();
      if ($this->dispatcher->hasListeners("rules_entity_delete:$entity_type_id")) {
        $event = new EntityEvent($entity, [$entity_type_id => $entity]);
        $this->dispatcher->dispatch($event, "rules_entity_delete:$entity_type_id");
      }
    }
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    // Only handle content entities and ignore config entities.
    if ($entity instanceof ContentEntityInterface) {
      $entity_type_id = $entity->getEntityTypeId();
      if ($this->dispatcher->hasListeners("rules_entity_insert:$entity_type_id")) {
        $event = new EntityEvent($entity, [$entity_type_id => $entity]);
        $this->dispatcher->dispatch($event, "rules_entity_insert:$entity_type_id");
      }
    }
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    // Only handle content entities and ignore config entities.
    if ($entity instanceof ContentEntityInterface) {
      $entity_type_id = $entity->getEntityTypeId();
      if ($this->dispatcher->hasListeners("rules_entity_update:$entity_type_id")) {
        $event = new EntityEvent($entity, [
          $entity_type_id => $entity,
          $entity_type_id . '_unchanged' => $entity->original,
        ]);
        $this->dispatcher->dispatch($event, "rules_entity_update:$entity_type_id");
      }
    }
  }

}
