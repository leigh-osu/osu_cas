<?php

declare(strict_types=1);

namespace Drupal\group_content_menu\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\content_translation\ContentTranslationHandler;
use Drupal\group\Access\GroupAccessResult;
use Drupal\group\Entity\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GroupMenuItemTranslateAccessHandler extends ContentTranslationHandler {

  protected ContextProviderInterface $contextProvider;
  protected RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->contextProvider = $container->get('group.group_route_context');
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  public function getTranslationAccess(EntityInterface $entity, $op): AccessResultInterface {
    $context = $this->contextProvider->getRuntimeContexts(['group'])['group'];
    assert($context instanceof ContextInterface);
    $group = $context->getContextValue();
    if ($group instanceof Group) {
      $groupAccess = GroupAccessResult::allowedIfHasGroupPermission($group, $this->currentUser, 'manage group_content_menu menu item translations');
      if ($groupAccess->isAllowed()) {
        return AccessResult::allowed();
      }
    }
    return parent::getTranslationAccess($entity, $op);
  }

  /**
   * Form submission handler for GroupMenuTranslationHandler::entityFormAlter().
   *
   * Get the entity delete form route url.
   */
  protected function entityFormDeleteTranslationUrl(EntityInterface $entity, $form_langcode): Url {
    if ($entity->access('delete') && $this->entityType->hasLinkTemplate('delete-form')) {
      return parent::entityFormDeleteTranslationUrl($entity, $form_langcode);
    }

    $options = [];
    $options['query']['destination'] = Url::fromRoute('<current>')->toString();
    $group_content_menu = $this->routeMatch->getParameter('group_content_menu');
    return $group_content_menu->toUrl('translate-menu-delete-link', $options)
      ->setRouteParameter('language', $form_langcode)
      ->setRouteParameter('menu_link_content', $entity->id());
  }

}
