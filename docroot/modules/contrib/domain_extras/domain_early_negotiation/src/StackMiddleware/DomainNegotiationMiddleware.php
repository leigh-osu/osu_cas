<?php

namespace Drupal\domain_early_negotiation\StackMiddleware;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Negotiates the active domain early in the middleware stack.
 *
 * Always active when the module is installed. Runs at a
 * configurable priority (default 220) -- after ReverseProxy
 * (300) so HTTP_HOST is already corrected, and before any
 * contributed module middleware that reads Drupal config
 * (e.g. CleanTalk) so domain_config overrides are available.
 *
 * The default priority (220) runs before page cache (200),
 * so negotiation executes on every request -- even cached
 * ones. On Drupal < 11.1 this also forces loadAll().
 * Consider lowering the priority below 200 if you do not
 * need domain_config overrides before the page cache.
 */
class DomainNegotiationMiddleware implements HttpKernelInterface {

  public function __construct(
    protected HttpKernelInterface $httpKernel,
    protected ModuleHandlerInterface $moduleHandler,
    protected RequestStack $requestStack,
    protected DomainNegotiatorInterface $negotiator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function handle(
    Request $request,
    int $type = self::MAIN_REQUEST,
    bool $catch = TRUE,
  ): Response {
    if ($type === self::MAIN_REQUEST) {
      // Push the request so DomainNegotiator reads the
      // proxy-corrected hostname via RequestStack.
      $this->requestStack->push($request);
      try {
        $this->negotiateDomain();
      }
      finally {
        // Pop to avoid a double entry when HttpKernel pushes
        // the request again later.
        $this->requestStack->pop();
      }
    }
    return $this->httpKernel->handle($request, $type, $catch);
  }

  /**
   * Negotiates the active domain, loading modules if needed.
   *
   * Before Drupal 11.1, #[LegacyHook] procedural functions
   * are the registered hook implementations, so .module files
   * must be loaded before negotiation for hooks like
   * hook_domain_request_alter to fire. From 11.1+, OOP hooks
   * are dispatched via the container without .module files;
   * loadAll() is only needed on cold entity type cache (e.g.
   * update.php) for hook_entity_type_build.
   */
  protected function negotiateDomain(): void {
    if (version_compare(\Drupal::VERSION, '11.1', '<')) {
      // Before 11.1: procedural hooks need .module files.
      $this->moduleHandler->loadAll();
      $this->negotiator->getActiveDomain();
      return;
    }
    // Drupal 11+: OOP hooks work without .module files.
    // Only fall back to loadAll() on cold entity type cache.
    try {
      $this->negotiator->getActiveDomain();
    }
    catch (\Throwable $e) {
      if ($this->moduleHandler->isLoaded()) {
        throw $e;
      }
      $this->moduleHandler->loadAll();
      $this->negotiator->getActiveDomain();
    }
  }

}
