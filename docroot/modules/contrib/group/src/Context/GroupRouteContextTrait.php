<?php

namespace Drupal\group\Context;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Trait to get the group entity from the current route.
 *
 * Using this trait will add the getGroupFromRoute() method to the class.
 *
 * If the class is capable of injecting services from the container, it should
 * inject the 'current_route_match' and 'entity_type.manager' services and
 * assign them to the currentRouteMatch and entityTypeManager properties.
 */
trait GroupRouteContextTrait {

  /**
   * The current route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Gets the current route match object.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The current route match object.
   */
  protected function getCurrentRouteMatch() {
    if (!$this->currentRouteMatch) {
      $this->currentRouteMatch = \Drupal::service('current_route_match');
    }
    return $this->currentRouteMatch;
  }

  /**
   * Gets the entity type manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager service.
   */
  protected function getEntityTypeManager() {
    if (!$this->entityTypeManager) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * Retrieves the group entity from the current route.
   *
   * This will try to load the group entity from the route if present. If we are
   * on the group add form, it will return a new group entity with the group
   * type set.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   A group entity if one could be found or created, NULL otherwise.
   */
  public function getGroupFromRoute() {
    $route_match = $this->getCurrentRouteMatch();

    // See if the route has a group parameter and try to retrieve it.
    $group = $this->getCurrentRouteMatch()->getParameter('group');

    // Regular permissions will have an integer set.
    if ($group && is_numeric($group)) {
      return $this->getEntityTypeManager()->getStorage('group')->load($group);
    }
    // Group permissions will have an object set.
    elseif ($group instanceof GroupInterface) {
      return $group;
    }
    // Create a new group to use as context if on the group add form.
    elseif ($route_match->getRouteName() == 'entity.group.add_form') {
      $group_type = $route_match->getParameter('group_type');
      return $this->getEntityTypeManager()->getStorage('group')->create(['type' => $group_type->id()]);
    }

    return NULL;
  }

  /**
   * Retrieves the group entity from the current route.
   *
   * Expands $this->getGroupFromRoute() to also return a Group based if
   * interacting with Group Content such as nodes assigned to a Group.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   A group entity if one could be found or created, NULL otherwise.
   */
  public function getBestCandidate() {
    if ($group = $this->getGroupFromRoute()) {
      return $group;
    }

    if (!empty($this->getCurrentRouteMatch()->getParameters())) {
      $entities = array_filter(iterator_to_array($this->getCurrentRouteMatch()->getParameters()), static function ($parameter) {
        return $parameter instanceof EntityInterface;
      });
      if (!empty($entities)) {
        foreach ($entities as $entity) {
          /** @var \Drupal\group\Entity\Storage\GroupRelationshipStorageInterface $group_content_storage */
          $group_content_storage = $this->getEntityTypeManager()->getStorage('group_content');
          foreach ($group_content_storage->loadByEntity($entity) as $group_relationship) {
            return $group_relationship->getGroup();
          }
        }
      }
    }

    return NULL;
  }

}
