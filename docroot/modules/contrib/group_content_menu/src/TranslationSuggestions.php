<?php

declare(strict_types=1);

namespace Drupal\group_content_menu;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource;

/**
 * Helper class to provide menu item suggestions.
 *
 * @package Drupal\GroupContentMenu
 */
final class TranslationSuggestions {

  public function suggestions(array $items, JobInterface $job): array {
    $suggestions = array();

    foreach ($items as $item) {
      if ($item instanceof JobItemInterface && $item->getPlugin() == 'content') {
        // Load the entity, skip if it can't be loaded.
        $entity = ContentEntitySource::load($item->getItemType(), $item->getItemId(), $job->getSourceLangcode());
        if (!$entity instanceof GroupContentMenuInterface) {
           continue;
        }

        // Load translatable menu items.
        $parameters = new MenuTreeParameters();
        $parameters->onlyEnabledLinks();
        $menu_items = \Drupal::menuTree()->load(GroupContentMenuInterface::MENU_PREFIX . $entity->id(), $parameters);

        $links = $this->extractLinks($menu_items);

        if (!empty($links)) {
          foreach ($links as $link) {
            // Skip if the item has no valid UUID.
            if (!Uuid::isValid($link->getDerivativeId())) {
              continue;
            }
            /** @var \Drupal\menu_link_content\MenuLinkContentInterface $target */
            $target = \Drupal::service('entity.repository')->loadEntityByUuid($link->getBaseId(), $link->getDerivativeId());
            if ($target->hasTranslation($job->getSourceLangcode())) {
              $suggestions[] = [
                'job_item' => tmgmt_job_item_create('content', $link->getBaseId(), $target->id()),
                'reason' => t('Menu item @label', ['@label' => $target->label()]),
                'from_item' => $item->id(),
              ];
            }
          }
        }
      }
    }

    return array_values($suggestions);
  }

  private function extractLinks(array $menu_items): array {
    $links = [];
    foreach ($menu_items as $menu_item) {
      if (!empty($menu_item->subtree)) {
        $links += $this->extractLinks($menu_item->subtree);
      }
      assert($menu_item->link instanceof MenuLinkInterface);
      $links[$menu_item->link->getPluginId()] = $menu_item->link;
    }
    return $links;
  }

}
