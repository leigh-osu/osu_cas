<?php

namespace Drupal\eck_bundle_permissions;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityAccessControlHandler as EckEntityAccessControlHandlerBase;
use Drupal\user\UserInterface;

/**
 * Access controller for the EckEntity entity, with bundle granularity.
 *
 * @ingroup eck
 *
 * @see \Drupal\eck\Entity\EckEntity.
 */
class EckBundleAccessControlHandler extends EckEntityAccessControlHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, ?AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    $access = parent::createAccess($entity_bundle, $account, $context, TRUE)
      ->orIf($this->checkBundleAccess($entity_bundle, 'create', NULL, $account));

    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return parent::checkAccess($entity, $operation, $account)
      ->orIf($this->checkBundleAccess($entity->bundle(), $operation, $entity->getOwner(), $account));
  }

  /**
   * Performs access checks for an ECK bundle.
   */
  protected function checkBundleAccess(string $bundle, string $operation, ?UserInterface $owner, ?AccountInterface $account = NULL): AccessResultInterface {
    $user = $this->prepareUser($account);
    // Check edit permission on update operation.
    $operation = ($operation === 'update') ? 'edit' : $operation;

    $permissions[] = sprintf('%s any %s entities of bundle %s', $operation, $this->entityTypeId, $bundle);

    if ($owner && $owner->id() === $account->id()) {
      $permissions[] = sprintf('%s own %s entities of bundle %s', $operation, $this->entityTypeId, $bundle);
    }

    return AccessResult::allowedIfHasPermissions($user, $permissions, 'OR');
  }

}
