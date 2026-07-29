<?php

namespace Drupal\entitygroupfield\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Provides specific access control for group entities.
 *
 * @EntityReferenceSelection(
 *   id = "group:entitygroupfield",
 *   label = @Translation("Groups"),
 *   entity_types = {"group"},
 *   group = "group",
 *   weight = 1
 * )
 */
class EntityGroupFieldSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $configuration = $this->getConfiguration();
    $target_type = $configuration['target_type'];
    $entity_type = $this->entityTypeManager->getDefinition($target_type);

    $query = parent::buildEntityQuery($match, $match_operator);
    if (!empty($configuration['excluded_groups'])) {
      $query->condition($entity_type->getKey('id'), $configuration['excluded_groups'], 'NOT IN');
    }
    // @todo It would be better to filter out groups the user does not have
    // permission to add group relationship entities to.
    // @see https://www.drupal.org/project/entitygroupfield/issues/3556732
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids) {
    // Since the autocomplete will show all groups the user can view, not
    // restricted to the groups where they have permission to add group
    // relationships, we might need to do some extra validation here.
    // @todo Remove this and use a better solution in self::buildEntityQuery().
    // @see https://www.drupal.org/project/entitygroupfield/issues/3556732
    $configuration = $this->getConfiguration();
    $entity_plugin_id = $configuration['entity_plugin_id'] ?? '';

    // If no 'entity_plugin_id' was specified for this widget, do not attempt
    // any further access checking or validation.
    if (!$entity_plugin_id) {
      return parent::validateReferenceableEntities($ids);
    }

    // Ensure the current user can add relationships for this entity plugin.
    $result = [];
    if ($ids) {
      $account = $this->currentUser->getAccount();
      $groups = $this->entityTypeManager->getStorage('group')->loadMultiple($ids);
      $add_permission = $entity_plugin_id === 'group_membership'
        ? 'administer members' : "create $entity_plugin_id relationship";
      foreach ($groups as $group) {
        if ($group->hasPermission($add_permission, $account)) {
          $result[] = $group->id();
        }
      }
    }

    return $result;
  }

}
