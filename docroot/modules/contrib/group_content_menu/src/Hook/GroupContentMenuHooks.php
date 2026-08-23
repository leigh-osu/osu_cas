<?php

declare(strict_types=1);

namespace Drupal\group_content_menu\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\GroupRelationship;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for group_content_menu.
 */
final class GroupContentMenuHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly MenuLinkManagerInterface $menuLinkManager,
  ) {}

  /**
   * Implements hook_entity_predelete().
   */
  #[Hook(hook: 'entity_predelete', order: new OrderAfter(['menu_link_content', 'redirect']))]
  public function entityPredelete(EntityInterface $entity): void {
    if ($entity instanceof GroupContentMenuInterface) {
      $this->menuLinkManager->deleteLinksInMenu(GroupContentMenuInterface::MENU_PREFIX . $entity->id());
    }
    // Remove any group contents related to this menu before removing the menu.
    if ($entity instanceof ContentEntityInterface) {
      if ($group_relationships = GroupRelationship::loadByEntity($entity)) {
        foreach ($group_relationships as $group_relationship) {
          $group_relationship->delete();
        }
      }
    }
  }

}
