<?php

declare (strict_types=1);

namespace Drupal\layout_builder_iframe_modal\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder_iframe_modal\IframeModalHelper;

/**
 * Link-related hook implementations for Layout Builder Iframe Modal.
 */
class LinkHooks {

  /**
   * Constructs a new LinkHooks instance.
   *
   * @param \Drupal\layout_builder_iframe_modal\IframeModalHelper $iframeModalHelper
   *   The iframe modal helper service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    protected IframeModalHelper $iframeModalHelper,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_contextual_links_alter().
   */
  #[Hook('contextual_links_alter')]
  public function contextualLinksAlter(array &$links, $group, array $route_parameters): void {
    foreach ($links as &$link) {
      if (isset($link['route_name'])
        && !empty($link['localized_options']['attributes']['data-dialog-type'])
        && $this->iframeModalHelper->isModalRoute($link['route_name'])) {
        $link['localized_options']['attributes']['data-dialog-type'] = 'iframe';
        unset($link['localized_options']['attributes']['data-dialog-renderer']);
      }
    }
  }

  /**
   * Implements hook_link_alter().
   */
  #[Hook('link_alter')]
  public function linkAlter(&$variables): void {
    $route_name = $this->routeMatch->getRouteName();

    // Only change links on routes.
    if ($route_name === NULL) {
      return;
    }

    // Only change links that open in a dialog.
    if (empty($variables['options']['attributes']['data-dialog-type'])) {
      return;
    }

    /** @var \Drupal\Core\Url $url */
    $url = $variables['url'];

    if (!$url || !$url->isRouted()) {
      return;
    }

    $link_route_name = $url->getRouteName();
    if ($this->iframeModalHelper->isModalRoute($link_route_name)) {
      $variables['options']['attributes']['data-dialog-type'] = 'iframe';
      unset($variables['options']['attributes']['data-dialog-renderer']);
    }
  }

}
