<?php

namespace Drupal\domain_access_linkit\Plugin\Linkit\Matcher;

use Drupal\domain_access\DomainAccessManager;
use Drupal\linkit\Plugin\Linkit\Matcher\NodeMatcher;

/**
 * Provides specific linkit matchers for the node entity type.
 *
 * @Matcher(
 *   id = "node_assigned_domains",
 *   label = @Translation("Assigned Domains Content"),
 *   target_entity = "node",
 * )
 */
class AssignedDomainsNodeMatcher extends NodeMatcher {

  /**
   * The temporary grants static cache name.
   */
  public const TEMP_ASSIGNED_DOMAINS_NODE_GRANTS =
    'domain_access_linkit_temp_assigned_domains_node_grants';

  /**
   * {@inheritdoc}
   */
  public function execute($string) {
    if ($this->currentUser->hasPermission('link to content on assigned domains')) {
      // Set the temporary grants.
      $temp_grants = &drupal_static(self::TEMP_ASSIGNED_DOMAINS_NODE_GRANTS);
      $temp_grants = [
        'domain_id' => array_values(DomainAccessManager::getAccessValues($this->getCurrentUserEntity())),
      ];
      try {
        return parent::execute($string);
      } finally {
        // Clear it after queries execute.
        drupal_static_reset(self::TEMP_ASSIGNED_DOMAINS_NODE_GRANTS);
      }
    }
    else {
      return parent::execute($string);
    }
  }

  /**
   * Returns the current user entity.
   *
   * @return \Drupal\user\Entity\User|null
   *   The current user entity.
   */
  protected function getCurrentUserEntity() {
    $user_storage = $this->entityTypeManager->getStorage('user');
    return $user_storage->load($this->currentUser->id());
  }

}
