<?php

namespace Drupal\domain_render_context;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\domain\DomainNegotiatorInterface;
use Psr\Log\LoggerInterface;

/**
 * Default implementation of the domain render context.
 *
 * Uses only API available from Domain 3.0 through 4.x: the negotiation context
 * carries the active domain, and core's router request context carries the
 * scheme, host and port an absolute URL is built from. Nothing here calls a
 * method deprecated for removal in Domain 4.0.
 */
class DomainRenderContext implements DomainRenderContextInterface {

  /**
   * Constructs a DomainRenderContext object.
   *
   * @param \Drupal\domain\DomainNegotiationContext $negotiationContext
   *   The domain negotiation context, holding the active domain.
   * @param \Drupal\domain\DomainNegotiatorInterface $negotiator
   *   The domain negotiator, to re-negotiate on restore when the context held
   *   no domain on entry.
   * @param \Drupal\Core\Routing\RequestContext $requestContext
   *   The router request context, holding the scheme, host and ports.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager, to load a domain from its machine name.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory, to read the render domain's default theme.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager, holding the active theme.
   * @param \Drupal\Core\Theme\ThemeInitializationInterface $themeInitialization
   *   The theme initializer, to build the render domain's active theme.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    protected DomainNegotiationContext $negotiationContext,
    protected DomainNegotiatorInterface $negotiator,
    protected RequestContext $requestContext,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected ThemeManagerInterface $themeManager,
    protected ThemeInitializationInterface $themeInitialization,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function inDomain(DomainInterface|string $domain, callable $callback): mixed {
    $restore = $this->enter($domain);
    try {
      return $callback();
    }
    finally {
      $restore();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function enter(DomainInterface|string $domain): \Closure {
    $target = $this->resolveDomain($domain);
    if ($target === NULL) {
      $this->logger->warning('Cannot render in domain %domain: no such domain record. The current context is kept.', [
        '%domain' => is_string($domain) ? $domain : (string) $domain->id(),
      ]);
      return static function (): void {};
    }

    $previous_domain = $this->negotiationContext->getDomain();
    $previous_negotiated = $this->negotiationContext->isNegotiated();
    $previous_hostname = $this->negotiationContext->getHostname();
    $previous_scheme = $this->requestContext->getScheme();
    $previous_host = $this->requestContext->getHost();
    $previous_http_port = $this->requestContext->getHttpPort();
    $previous_https_port = $this->requestContext->getHttpsPort();
    $previous_complete_base_url = $this->requestContext->getCompleteBaseUrl();

    $hostname = (string) $target->getHostname();
    [$host, $port] = $this->splitHostname($hostname);
    $scheme = $target->getScheme(FALSE);

    $this->negotiationContext->setDomain($target);
    $this->negotiationContext->setHostname($host);
    // Pin the switch: a context that has not negotiated yet (cron, the CLI)
    // would otherwise negotiate on the next read and overwrite the domain.
    $this->negotiationContext->setNegotiated(TRUE);

    $this->requestContext->setScheme($scheme);
    $this->requestContext->setHost($host);
    // Reset both ports, or the port of the request doing the work leaks into
    // the generated URLs.
    $this->requestContext->setHttpPort($scheme === 'http' && $port !== NULL ? $port : 80);
    $this->requestContext->setHttpsPort($scheme === 'https' && $port !== NULL ? $port : 443);
    // Keep any subdirectory Drupal is installed in, which does not vary by
    // domain, and swap only the origin in front of it.
    if (is_string($previous_complete_base_url) && $previous_complete_base_url !== '') {
      $path = parse_url($previous_complete_base_url, PHP_URL_PATH);
      $this->requestContext->setCompleteBaseUrl($scheme . '://' . $hostname . (is_string($path) ? $path : ''));
    }

    // The theme comes last, so it is read through the configuration overrides
    // of the render domain: that is what makes a per-domain theme, however it
    // is stored, end up on the rendered output.
    $previous_theme = $this->themeManager->hasActiveTheme() ? $this->themeManager->getActiveTheme() : NULL;
    $theme_switched = $this->switchTheme($previous_theme);

    $restored = FALSE;
    return function () use (
      &$restored,
      $theme_switched,
      $previous_theme,
      $previous_domain,
      $previous_negotiated,
      $previous_hostname,
      $previous_scheme,
      $previous_host,
      $previous_http_port,
      $previous_https_port,
      $previous_complete_base_url,
    ): void {
      if ($restored) {
        return;
      }
      $restored = TRUE;
      // Restore in reverse order, the theme first: it was chosen through the
      // render domain's configuration, which is restored just below.
      if ($theme_switched) {
        if ($previous_theme !== NULL) {
          $this->themeManager->setActiveTheme($previous_theme);
        }
        else {
          $this->themeManager->resetActiveTheme();
        }
      }
      if ($previous_domain instanceof DomainInterface) {
        $this->negotiationContext->setDomain($previous_domain);
        $this->negotiationContext->setNegotiated($previous_negotiated);
      }
      else {
        // The context held no domain yet, which is the normal state of a CLI
        // or cron run before anything asks. setDomain() cannot take NULL, and
        // leaving the render domain in place would keep applying its
        // configuration overrides to everything that follows, so negotiate
        // from the current request instead: that is the state the next read
        // would have produced had this service never been called.
        $this->negotiationContext->setNegotiated(FALSE);
        $this->negotiator->getActiveDomain(TRUE);
      }
      $this->negotiationContext->setHostname($previous_hostname);
      $this->requestContext->setScheme($previous_scheme);
      $this->requestContext->setHost($previous_host);
      $this->requestContext->setHttpPort($previous_http_port);
      $this->requestContext->setHttpsPort($previous_https_port);
      if (is_string($previous_complete_base_url) && $previous_complete_base_url !== '') {
        $this->requestContext->setCompleteBaseUrl($previous_complete_base_url);
      }
    };
  }

  /**
   * Makes the render domain's default theme the active one.
   *
   * Read from configuration, so it follows the override the site stores its
   * per-domain theme in, whether that is domain_config directly or a module
   * writing the same system.theme override. A theme that fails to initialize
   * is logged and the current one kept: a broken theme must not stop a
   * notification going out.
   *
   * @param \Drupal\Core\Theme\ActiveTheme|null $previous_theme
   *   The active theme before the switch, or NULL when none was initialized.
   *
   * @return bool
   *   TRUE when the active theme was changed and has to be restored.
   */
  protected function switchTheme(?ActiveTheme $previous_theme): bool {
    $theme_name = (string) $this->configFactory->get('system.theme')->get('default');
    if ($theme_name === '' || ($previous_theme !== NULL && $previous_theme->getName() === $theme_name)) {
      return FALSE;
    }
    try {
      $this->themeManager->setActiveTheme($this->themeInitialization->initTheme($theme_name));
      return TRUE;
    }
    catch (\Throwable $e) {
      $this->logger->warning('Cannot activate the %theme theme of the render domain: @message. The current theme is kept.', [
        '%theme' => $theme_name,
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Resolves the domain to render in.
   *
   * @param \Drupal\domain\DomainInterface|string $domain
   *   A domain, or its machine name.
   *
   * @return \Drupal\domain\DomainInterface|null
   *   The domain, or NULL when the machine name matches no domain record.
   */
  protected function resolveDomain(DomainInterface|string $domain): ?DomainInterface {
    if ($domain instanceof DomainInterface) {
      return $domain;
    }
    $loaded = $this->entityTypeManager->getStorage('domain')->load($domain);
    return $loaded instanceof DomainInterface ? $loaded : NULL;
  }

  /**
   * Splits a domain hostname into its host and its explicit port, if any.
   *
   * @param string $hostname
   *   The domain hostname, which may carry a port (example.com:8080).
   *
   * @return array
   *   The host, and the port as an integer or NULL when the hostname carries
   *   none.
   */
  protected function splitHostname(string $hostname): array {
    $parts = explode(':', $hostname, 2);
    $port = isset($parts[1]) && $parts[1] !== '' ? (int) $parts[1] : NULL;
    return [$parts[0], $port];
  }

}
