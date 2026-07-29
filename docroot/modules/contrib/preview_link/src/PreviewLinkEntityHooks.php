<?php

declare(strict_types=1);

namespace Drupal\preview_link;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\preview_link\Access\PreviewLinkAccessCheck;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Responds to entity hooks/events.
 */
final class PreviewLinkEntityHooks implements ContainerInjectionInterface {

  /**
   * Constructs a new PreviewLinkEntityHooks.
   */
  public function __construct(
    protected PreviewLinkAccessCheck $accessCheck,
    protected PreviewLinkHookHelper $hookHelper,
    protected RouteMatchInterface $routeMatch,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('access_check.preview_link'),
      $container->get('preview_link.hook_helper'),
      $container->get('current_route_match'),
    );
  }

  /**
   * Implements \hook_entity_access().
   *
   * @see \preview_link_entity_access()
   */
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'view' || !($entity instanceof ContentEntityInterface)) {
      return AccessResult::neutral();
    }

    $currentRoute = $this->routeMatch->getRouteObject();
    if ($currentRoute === NULL) {
      // In cli contexts, there may be no route.
      return AccessResult::neutral();
    }
    $entityParameterName = $currentRoute->getOption('preview_link.entity_type_id');
    if ($entityParameterName === NULL) {
      // Not on a preview link route; no preview link cache context needed.
      return AccessResult::neutral();
    }

    $neutral = AccessResult::neutral()
      ->addCacheableDependency($entity)
      ->addCacheContexts(['preview_link_route']);

    // Only run our access checks on the entity we're previewing.
    $route_entity = $this->routeMatch->getParameter($entityParameterName);
    if ($this->hookHelper->isPreviewLinkGrantingAccess() && $route_entity instanceof ContentEntityInterface && $route_entity->id() === $entity->id() && $route_entity->getEntityTypeId() === $entity->getEntityTypeId()) {
      return $this->accessCheck->access($entity, $this->routeMatch->getParameter('preview_token'));
    }

    return $neutral;
  }

}
