<?php

declare(strict_types=1);

namespace Drupal\preview_link\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\preview_link\Exception\PreviewLinkRerouteException;
use Drupal\preview_link\PreviewLinkHostInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reroutes users from a canonical route to preview link route.
 *
 * Does not actually grant access, access checkers are in the right place
 * to interrupt routing and send the user agent elsewhere.
 */
class PreviewLinkCanonicalRerouteAccessCheck implements AccessInterface {

  /**
   * PreviewLinkCanonicalRerouteAccessCheck constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   Private temp store factory.
   * @param \Drupal\preview_link\PreviewLinkHostInterface $previewLinkHost
   *   Preview link host service.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $routeMatch
   *   The current route match.
   */
  public function __construct(
    protected PrivateTempStoreFactory $privateTempStoreFactory,
    protected PreviewLinkHostInterface $previewLinkHost,
    protected CurrentRouteMatch $routeMatch,
  ) {
  }

  /**
   * Checks if an activated preview link token is associated with this entity.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   A \Drupal\Core\Access\AccessInterface value.
   *
   * @throws \Drupal\preview_link\Exception\PreviewLinkRerouteException
   *   When a claimed token grants access to entity for this route match.
   */
  public function access(?Request $request = NULL): AccessResultInterface {
    $cacheability = (new CacheableMetadata())->addCacheContexts(['route']);

    // Dont use argument resolved route match or route, get the real route match
    // from the master request.
    $routeMatch = $this->routeMatch->getMasterRouteMatch();
    $route = $routeMatch->getRouteObject();

    $entityParameterName = $route?->getRequirement('_access_preview_link_canonical_rerouter');
    if (!isset($entityParameterName)) {
      // If the requirement doesnt exist then the master request isn't the
      // canonical route, its probably simulated from something like menu or
      // breadcrumb.
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    if ($request === NULL) {
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    $entity = $routeMatch->getParameter($entityParameterName);
    if (!$entity instanceof EntityInterface) {
      // Entity was not upcast for preview link reroute access check.
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    if (!$this->previewLinkHost->hasPreviewLinks($entity)) {
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    // Only add 'session' context and check the token store if a session is
    // already active. Sessions are only started by claimToken() when a user
    // visits a preview link URL, so isStarted() === false means no tokens
    // have been claimed and the canonical page can be cached without any
    // session variation.
    if (!$request->hasSession() || !$request->getSession()->isStarted()) {
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }
    $cacheability->addCacheContexts(['session']);
    $collection = $this->privateTempStoreFactory->get('preview_link');
    $claimedTokens = $collection->get('keys') ?? [];
    if (count($claimedTokens) === 0) {
      // Session has no claimed tokens.
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    if (!$this->previewLinkHost->isToken($entity, $claimedTokens)) {
      // This session doesnt have an activated preview link tokens matching this
      // entity.
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    // Check if any keys in this session unlock this entity.
    $previewLinks = $this->previewLinkHost->getPreviewLinks($entity);
    // Get the first token that matches this entity.
    foreach ($previewLinks as $previewLink) {
      if (in_array($previewLink->getToken(), $claimedTokens, TRUE)) {
        throw new PreviewLinkRerouteException('', 0, NULL, $entity, $previewLink);
      }
    }

    throw new \LogicException('Shouldnt get here unless there are implementation differences between isToken and getPreviewLinks.');
  }

}
