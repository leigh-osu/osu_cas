<?php

namespace Drupal\domain_sso_admin_toolbar\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Url;
use Drupal\domain\DomainNegotiationContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for switching domains with SSO.
 */
class DomainSwitchController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs a DomainSwitchController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\domain\DomainNegotiationContext $domainNegotiationContext
   *   The domain negotiation context.
   * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface $pathProcessor
   *   The inbound path processor.
   */
  public function __construct(
    protected RequestStack $requestStack,
    protected DomainNegotiationContext $domainNegotiationContext,
    protected InboundPathProcessorInterface $pathProcessor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('domain.negotiation_context'),
      $container->get('path_processor_manager'),
    );
  }

  /**
   * Switches to the specified domain using SSO.
   *
   * @param string $domain
   *   The domain ID to switch to.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the SSO handshake.
   */
  public function switchDomain($domain) {
    $current_user = $this->currentUser();

    // Only authenticated users can switch domains.
    if (!$current_user->isAuthenticated()) {
      $this->messenger()->addError($this->t('You must be logged in to switch domains.'));
      return $this->redirect('user.login');
    }

    // Get the request and referer to preserve the current path context.
    $request = $this->requestStack->getCurrentRequest();
    $referer = $request->headers->get('referer');

    // Verify the target domain exists.
    $domain_storage = $this->entityTypeManager()->getStorage('domain');
    $target_domain = $domain_storage->load($domain);

    if (!$target_domain) {
      $this->messenger()->addError($this->t('Invalid domain specified.'));
      if ($referer) {
        return new RedirectResponse($referer);
      }
      return $this->redirect('<front>');
    }

    // Get the current domain to check if we're already on the target.
    $current_domain = $this->domainNegotiationContext->getDomain();

    if ($current_domain && $current_domain->id() === $domain) {
      // Already on this domain, redirect back to referer if present.
      $this->messenger()->addStatus($this->t('You are already on the @domain domain.', [
        '@domain' => $target_domain->label(),
      ]));
      if ($referer) {
        return new RedirectResponse($referer);
      }
      return $this->redirect('<front>');
    }

    // Extract the internal Drupal path from the referer by
    // running all inbound path processors (strips domain
    // prefix, language prefix, etc.).
    $target_path = '/';
    $query_string = '';

    if ($referer) {
      $referer_parts = parse_url($referer);
      if (!empty($referer_parts['path'])) {
        $base_path = $request->getBasePath();
        $path = $referer_parts['path'];

        if ($base_path && strpos($path, $base_path) === 0) {
          $path = substr($path, strlen($base_path));
        }

        $target_path = $this->pathProcessor
          ->processInbound($path ?: '/', $request);
      }

      if (!empty($referer_parts['query'])) {
        $query_string = $referer_parts['query'];
      }
    }

    // Build the target URL: domain base + target prefix + path.
    $target_url = rtrim($target_domain->getPath(), '/')
      . $target_path;
    if ($query_string) {
      $target_url .= '?' . $query_string;
    }

    // Same hostname: session cookies are shared, no SSO needed.
    if ($current_domain
        && $current_domain->getHostname() === $target_domain->getHostname()) {
      return new RedirectResponse($target_url);
    }

    // Different hostname: go through the SSO handshake.
    $issue_url = Url::fromRoute('domain_sso.handshake.issue', [], [
      'absolute' => TRUE,
      'query' => [
        'domain' => $domain,
        'target' => $target_url,
      ],
    ])->toString();

    return new RedirectResponse($issue_url);
  }

}
