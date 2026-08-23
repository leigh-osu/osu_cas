<?php

namespace Drupal\toc_js_filter\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for toc_js_filter.
 */
class TocJsFilterHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(
    $route_name,
    RouteMatchInterface $route_match,
  ) {
    switch ($route_name) {
      case 'help.page.toc_js_filter':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Provides a configurable text filter to automatically generate a Toc.js table of contents.') . '</p>';
        return $output;

      default:
    }
  }

}
