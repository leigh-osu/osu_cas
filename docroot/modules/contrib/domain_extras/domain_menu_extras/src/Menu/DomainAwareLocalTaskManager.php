<?php

namespace Drupal\domain_menu_extras\Menu;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainNegotiationContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Local task manager that varies its discovery cache by active domain.
 *
 * Drupal core keys the local task plugin definition cache on language only:
 * "local_task_plugins:LANGCODE". Derivers like
 * Drupal\block\Plugin\Derivative\ThemeLocalTask read overridable config
 * (e.g. system.theme.default) when building their derivatives, so the
 * computed tabs vary per domain — but the cache key does not, and the first
 * domain to populate the cache freezes the answer for everyone else.
 *
 * This subclass appends the active domain id to the cache key
 * ("local_task_plugins:LANGCODE:DOMAIN_ID") so each domain gets its own
 * cache slot. Cache fragmentation is bounded by the number of domains
 * actually visited; per-request overhead is one extra string concatenation.
 *
 * @see https://www.drupal.org/project/domain_extras/issues/3588108
 */
class DomainAwareLocalTaskManager extends LocalTaskManager {

  public function __construct(
    ArgumentResolverInterface $argument_resolver,
    RequestStack $request_stack,
    RouteMatchInterface $route_match,
    RouteProviderInterface $route_provider,
    ModuleHandlerInterface $module_handler,
    CacheBackendInterface $cache,
    LanguageManagerInterface $language_manager,
    AccessManagerInterface $access_manager,
    AccountInterface $account,
    protected DomainNegotiationContext $domainContext,
  ) {
    parent::__construct(
      $argument_resolver,
      $request_stack,
      $route_match,
      $route_provider,
      $module_handler,
      $cache,
      $language_manager,
      $access_manager,
      $account,
    );

    // Re-bind the cache backend with a domain-aware cache key. "und" is the
    // sentinel used elsewhere in the domain module when no domain has been
    // negotiated yet (CLI, install hooks, …) — keeping it stable means
    // those contexts share a single cache slot rather than thrashing.
    $domain_id = $this->domainContext->getDomainId() ?? 'und';
    $this->setCacheBackend(
      $cache,
      'local_task_plugins:' . $language_manager->getCurrentLanguage()->getId() . ':' . $domain_id,
      ['local_task'],
    );
  }

}
