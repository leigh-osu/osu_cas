<?php

namespace Drupal\domain_alias_extras\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for domain_alias_extras.
 */
class DomainAliasExtrasHooks {

  /**
   * Implements hook_domain_config_ui_disallowed_routes_alter().
   */
  #[Hook('domain_config_ui_disallowed_routes_alter')]
  public function domainConfigUiDisallowedRoutesAlter(
    array &$disallowed,
  ): void {
    $disallowed[] = 'domain_alias_extras.settings';
  }

}
