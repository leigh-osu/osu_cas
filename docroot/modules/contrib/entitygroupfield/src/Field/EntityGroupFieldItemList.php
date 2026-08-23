<?php

namespace Drupal\entitygroupfield\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\group\Entity\GroupRelationship;

/**
 * A computed property for the related groups.
 */
class EntityGroupFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    // No value will exist if the entity has not been created, so exit early.
    if ($this->getEntity()->isNew()) {
      return NULL;
    }

    // If this entity/bundle has no group relation type plugins enabled,
    // there's no way there could be any group associations, so exit early.
    if (!entitygroupfield_get_group_relation_type_plugin_ids($this->getEntity()->getEntityTypeId(), $this->getEntity()->bundle())) {
      return NULL;
    }

    $group_relationships = GroupRelationship::loadByEntity($this->getEntity());
    if (empty($group_relationships)) {
      return NULL;
    }

    $this->list = [];
    foreach ($group_relationships as $delta => $group_relationship) {
      $this->list[] = $this->createItem($delta, [
        'target_id' => $group_relationship->id(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   *
   * We need to override the presave to avoid saving without the host entity id
   * generated.
   */
  public function preSave() {
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    if ($this->valueComputed) {
      $host_entity = $this->getEntity();
      $group_relationship_ids = [];
      foreach ($this->getIterator() as $item) {
        $group_relationship_entity = $item->entity;
        $group_relationship_entity->entity_id = $host_entity->id();
        // Saving entities.
        if (isset($item->needs_save)) {
          $group_relationship_entity->save();
        }
        $group_relationship_ids[] = $group_relationship_entity->id();
      }
      // Deleting entities.
      $group_relationships = GroupRelationship::loadByEntity($host_entity);
      foreach ($group_relationships as $group_relationship_id => $group_relationship_entity) {
        if (!in_array($group_relationship_id, $group_relationship_ids)) {
          $group_relationship_entity->delete();
        }
      }
    }
    return parent::postSave($update);
  }

}
