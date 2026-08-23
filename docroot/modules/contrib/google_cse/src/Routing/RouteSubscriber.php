<?php

namespace Drupal\google_cse\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\search\Entity\SearchPage;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    // The route for Google CSE is dynamically created. Substitute a custom
    // controller that extends Drupal\search\Controller\SearchController.
    $applicable_routes = [];
    $search_implementations = SearchPage::loadMultiple();
    foreach ($search_implementations as $search) {
      if ($search->getPlugin()->getPluginId() !== 'google_cse_search') {
        continue;
      }
      $applicable_routes[] = $search->id();
    }
    foreach ($applicable_routes as $google_cse_search) {
      if ($route = $collection->get('search.view_' . $google_cse_search)) {
        $route->setDefault('_controller', '\Drupal\google_cse\Controller\GoogleCseSearchController::view');
      }
    }
  }

}
