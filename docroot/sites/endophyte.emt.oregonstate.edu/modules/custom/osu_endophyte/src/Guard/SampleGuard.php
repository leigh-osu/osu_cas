<?php

namespace Drupal\osu_endophyte\Guard;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine\WorkflowManagerInterface;


/**
 * Class SampleGuard.
 */
class SampleGuard implements GuardInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The workflow manager.
   *
   * @var \Drupal\state_machine\WorkflowManagerInterface
   */
  protected $workflowManager;


  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity) {
//    echo($transition->getId());

//    \Drupal::messenger()->addMessage(t($transition->getId()));
//    if ($transition->getId() == 'cancel') {
//
//    }

//    if ($transition->getId() == 'cancel') {
//      // Do not cancel orders with filled items.
//      foreach ($entity->getItems() as $order_item) {
//        if ($order_item->get('field_fulfillment_state')->first()->getId() == 'filled') {
//          return FALSE;
//        }
//      }
//      return TRUE;
//    }
//    if ($transition->getId() == 'fulfill') {
//      // All items must be filled or backordered. At least one must be filled.
//      $has_filled_item = FALSE;
//      foreach ($entity->getItems() as $order_item) {
//        $inventory_state = $order_item->get('field_fulfillment_state')->first()->getId();
//        if ($inventory_state != 'filled' && $inventory_state != 'backordered') {
//          return FALSE;
//        }
//        $has_filled_item = $inventory_state == 'filled' ? TRUE : $has_filled_item;
//      }
//      return $has_filled_item;
//    }

    // All other transitions.
    return TRUE;
  }

}
