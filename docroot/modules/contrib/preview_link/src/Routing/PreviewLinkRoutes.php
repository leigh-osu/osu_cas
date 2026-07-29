<?php

declare(strict_types=1);

namespace Drupal\preview_link\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\preview_link\PreviewLinkUtility;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters routes to add access checker to canonical entity type routes.
 *
 * Checker is used to redirect users to preview link route.
 */
class PreviewLinkRoutes extends RouteSubscriberBase {

  /**
   * PreviewLinkRoutes constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $entityTypes = $this->entityTypeManager->getDefinitions();
    $entityTypes = array_filter($entityTypes, [
      PreviewLinkUtility::class,
      'isEntityTypeSupported',
    ]);
    foreach ($entityTypes as $entityType) {
      $canonicalPath = $entityType->getLinkTemplate('canonical');
      $route = $this->getRouteForPath($collection, $canonicalPath);
      if ($route === NULL) {
        continue;
      }

      // Find the first parameter name for this entity type, otherwise fall back
      // to entity type ID, e.g. for block_content.
      $entityParameterName = $entityType->id();
      foreach ($route->getOptions()['parameters'] ?? [] as $parameterName => $value) {
        if (($value['type'] ?? NULL) === 'entity:' . $entityType->id()) {
          $entityParameterName = $parameterName;
        }
      }

      if (!str_contains($route->getPath(), sprintf('{%s}', $entityParameterName))) {
        throw new \LogicException(sprintf('Unable to determine parameter name representing an upcast of entity type `%s` in path `%s`', $entityType->id(), $route->getPath()));
      }

      // Adds the parameter name as the value.
      $route->addRequirements([
        '_access_preview_link_canonical_rerouter' => $entityParameterName,
      ]);
    }
  }

  /**
   * Determines the route for a path.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection.
   * @param string $path
   *   A route path.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The route, or NULL if no route found with this path in the collection.
   */
  protected function getRouteForPath(RouteCollection $collection, string $path): ?Route {
    foreach ($collection as $route) {
      if ($route->getPath() === $path) {
        return $route;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RoutingEvents::ALTER => [['onAlterRoutes', -120]],
    ];
  }

}
