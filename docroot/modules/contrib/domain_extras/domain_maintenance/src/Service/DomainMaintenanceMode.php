<?php

namespace Drupal\domain_maintenance\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Site\MaintenanceMode;
use Drupal\Core\State\StateInterface;
use Drupal\domain\DomainNegotiationContext;

/**
 * Extends core maintenance mode service class.
 *
 * It mainly differs by checking the configuration setting instead of the state
 * value.
 */
class DomainMaintenanceMode extends MaintenanceMode {

  /**
   * Constructs a new maintenance mode service.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\domain\DomainNegotiationContext $domainContext
   *   The domain negotiation context.
   */
  public function __construct(
    StateInterface $state,
    ConfigFactoryInterface $config_factory,
    protected DomainNegotiationContext $domainContext,
  ) {
    parent::__construct($state, $config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {

    if (!$this->state->get('system.maintenance_mode')) {
      $domain_id = $this->domainContext->getDomainId();
      if (empty($domain_id)) {
        return FALSE;
      }
      else {
        if (!$this->state->get(static::getStateName($domain_id))) {
          return FALSE;
        }
      }
    }

    if ($route = $route_match->getRouteObject()) {
      if ($route->getOption('_maintenance_access')) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get the state name for the maintenance mode for a domain.
   *
   * @param string $domain_id
   *   The domain id.
   *
   * @return string
   *   The state name.
   */
  public static function getStateName($domain_id): string {
    return 'domain.' . $domain_id . '.system.maintenance_mode';
  }

}
