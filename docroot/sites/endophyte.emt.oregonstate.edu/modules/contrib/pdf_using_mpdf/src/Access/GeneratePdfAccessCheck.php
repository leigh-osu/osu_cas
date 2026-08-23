<?php

namespace Drupal\pdf_using_mpdf\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

/**
 * Access checks for the PDF generation..
 *
 * @package Drupal\pdf_using_mpdf\Access
 */
class GeneratePdfAccessCheck implements AccessInterface {

  /**
   * Access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account.
   * @param \Drupal\node\Entity\Node|null $node
   *   Processed node.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Access check result.
   */
  public function access(AccountInterface $account, Node $node = NULL) {
    $permission = 'generate ' . $node->getType() . ' pdf';

    return ($account->hasPermission($permission)) ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
