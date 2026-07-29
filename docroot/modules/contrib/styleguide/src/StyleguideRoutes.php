<?php

namespace Drupal\styleguide;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * The Styleguide routers.
 */
class StyleguideRoutes implements ContainerInjectionInterface {

  public function __construct(protected ThemeExtensionList $themeExtensionList) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.theme'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];

    $themes = $this->themeExtensionList->reset()->getList();
    foreach ($themes as &$theme) {
      if (!empty($theme->info['hidden'])) {
        continue;
      }
      if ($theme->status) {
        $name = $theme->getName();
        $routes['styleguide.' . $name] = new Route(
          '/admin/appearance/styleguide/' . $name,
          [
            '_controller' => 'Drupal\styleguide\Controller\StyleguideController::page',
            '_title' => $theme->info['name'],
          ],
          [
            '_permission'  => 'view style guides',
          ],
          [
            '_admin_route' => FALSE,
          ]
        );
        $routes['styleguide.maintenance_page.' . $name] = new Route(
          '/admin/appearance/styleguide/maintenance-page/' . $name,
          [
            '_controller' => 'Drupal\styleguide\Controller\StyleguideMaintenancePageController::page',
            '_title' => $theme->info['name'],
          ],
          [
            '_permission'  => 'view style guides',
          ],
          [
            '_admin_route' => FALSE,
          ]
        );
      }
    }

    return $routes;
  }

}
