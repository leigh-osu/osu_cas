<?php

declare (strict_types=1);

namespace Drupal\layout_builder_iframe_modal\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder_iframe_modal\IframeModalHelper;

/**
 * Theme hook implementations for Layout Builder Iframe Modal.
 */
class ThemeHooks {

  /**
   * Constructs a new ThemeHooks object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\layout_builder_iframe_modal\IframeModalHelper $iframeModalHelper
   *   The iframe modal helper service.
   */
  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly IframeModalHelper $iframeModalHelper,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'lbim_iframe' => [
        'variables' => [
          'iframe_attributes' => NULL,
        ],
      ],
      'lbim_redirect' => [
        'variables' => [],
      ],
    ];
  }

  /**
   * Implements hook_preprocess_html.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $route_name = $this->routeMatch->getRouteName();

    if (!empty($route_name) && $this->iframeModalHelper->isModalRoute($route_name)) {
      // Removes regions and elements not needed for the block edit form.
      unset($variables['page_top']);
      unset($variables['page']['header']);
      unset($variables['page']['pre_content']);
      unset($variables['page']['breadcrumb']);
      unset($variables['page']['footer']);
      unset($variables['page']['#title']);

      // Add class identifying modal so theme CSS can target it.
      $variables['attributes']['class'][] = 'layout-builder-iframe-modal';
    }
  }

}
