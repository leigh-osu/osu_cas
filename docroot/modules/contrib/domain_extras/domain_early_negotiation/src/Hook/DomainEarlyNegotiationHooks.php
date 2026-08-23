<?php

namespace Drupal\domain_early_negotiation\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for domain_early_negotiation.
 */
class DomainEarlyNegotiationHooks {

  /**
   * Implements hook_domain_config_ui_disallowed_configurations_alter().
   */
  #[Hook('domain_config_ui_disallowed_configurations_alter')]
  public function disallowedConfigurationsAlter(array &$disallowed): void {
    $disallowed[] = 'domain_early_negotiation.settings';
  }

}
