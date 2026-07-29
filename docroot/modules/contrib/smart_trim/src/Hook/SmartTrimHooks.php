<?php

namespace Drupal\smart_trim\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Service to provide module hooks.
 */
class SmartTrimHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): string {
    $output = '';
    switch ($route_name) {
      case 'help.page.smart_trim':
        $output = '<h3>' . t('About') . '</h3>';
        $output .= '<p>' . t('Smart Trim implements a new field formatter for text fields (text, text_long, and text_with_summary, if you want to get technical) that improves upon the "Summary or Trimmed" formatter.') . '</p>';
    }
    return $output;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(array $existing, string $type, string $theme, string $path): array {
    return [
      'smart_trim' => [
        'variables' => [
          'output' => NULL,
          'is_trimmed' => NULL,
          'wrap_output' => NULL,
          'wrapper_class' => NULL,
          'more' => NULL,
          'more_wrapper_class' => NULL,
          'field' => NULL,
          'entity_type' => NULL,
          'entity_bundle' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter().
   */
  #[Hook('theme_suggestions_smart_trim_alter')]
  public function themeSuggestionsSmartTrimAlter(array &$suggestions, array $variables) {
    $values = [];
    // Add all variables to array.
    $values[] = $variables['entity_type'] ?? NULL;
    $values[] = $variables['entity_bundle'] ?? NULL;
    $values[] = $variables['field'] ?? NULL;
    // Remove any missing values.
    $values = array_filter($values);
    $results = [[]];
    // Loop through all values to form all combinations.
    foreach ($values as $element) {
      foreach ($results as $combination) {
        array_push($results, array_merge($combination, [$element]));
      }
    }
    // Sort by length so most specific suggestions come last.
    usort($results, fn($a, $b) => count($a) - count($b));
    // Add all discovered combinations to suggestions.
    $suggestions += array_map(fn($array) => 'smart_trim__' . implode('__', $array), array_filter($results));
  }

}
