<?php

namespace Drupal\group\Plugin\views\argument_default;

/**
 * Default argument plugin to extract a group ID.
 *
 * @ViewsArgumentDefault(
 *   id = "group_id_from_url_or_content",
 *   title = @Translation("Group ID from URL or group content")
 * )
 */
class GroupIdFromUrlOrGroupContent extends GroupIdFromUrl {

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    if (!empty($this->group) && $id = $this->group->id()) {
      return $id;
    }
    else {
      $route_match = \Drupal::routeMatch();
      // If the current route has no parameters, return.
      if (!($route = $route_match->getRouteObject()) || !($parameters = $route->getOption('parameters'))) {
        return;
      }

      foreach ($parameters as $name => $options) {
        // Ensure the current route represents an entity
        if (!isset($options['type']) || strpos($options['type'], 'entity:') !== 0) {
          continue;
        }
        $entity = $route_match->getParameter($name);
        /*
         * Check if the entity is either a user or a node.
         * - User entities could be related to groups through membership.
         * - Node entities could be related to groups through gnode sub-module, if installed.
         * */
        if ($entity instanceof \Drupal\node\NodeInterface || $entity instanceof \Drupal\user\UserInterface) {
          $group_ids = [];
          $group_contents = \Drupal\group\Entity\GroupRelationship::loadByEntity($entity);
          foreach ($group_contents as $group_content) {
            $group_ids[] = $group_content->getGroup()->id();
          }
          // Return group ID(s). If multiple IDs are found, they would be comma-separated which means they are OR'ed.
          return implode(',', $group_ids);
        }
      }
    }
  }

}
