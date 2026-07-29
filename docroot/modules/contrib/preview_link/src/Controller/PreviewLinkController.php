<?php

declare(strict_types=1);

namespace Drupal\preview_link\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\preview_link\PreviewLinkHookHelper;
use Drupal\preview_link\PreviewLinkMessageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preview link controller to view any entity.
 */
class PreviewLinkController extends ControllerBase {

  /**
   * PreviewLinkController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   The tempstore factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\preview_link\PreviewLinkMessageInterface $previewLinkMessages
   *   Provides common messenger functionality.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\preview_link\PreviewLinkHookHelper $hookHelper
   *   Provides service tasks for hooks.
   */
  final public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    protected PrivateTempStoreFactory $privateTempStoreFactory,
    ConfigFactoryInterface $configFactory,
    protected PreviewLinkMessageInterface $previewLinkMessages,
    MessengerInterface $messenger,
    protected PreviewLinkHookHelper $hookHelper,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('config.factory'),
      $container->get('preview_link.message'),
      $container->get('messenger'),
      $container->get('preview_link.hook_helper')
    );
  }

  /**
   * Preview any entity with the default view mode.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param string $preview_token
   *   A validated Preview Link token.
   *
   * @return array
   *   A render array for previewing the entity.
   */
  public function preview(RouteMatchInterface $routeMatch, string $preview_token): array {
    // Accessing the controller will bind the Preview Link token to the session.
    $this->claimToken($preview_token);

    $entity = $this->resolveEntity($routeMatch);

    $config = $this->configFactory->get('preview_link.settings');
    if (in_array($config->get('display_message'), ['always'], TRUE)) {
      // Reset static cache so our hook_entity_access is always re-evaluated.
      $this->entityTypeManager->getAccessControlHandler($entity->getEntityTypeId())->resetCache();
      // Temporarily disable so we can get whether the canonical route is really
      // accessible.
      $this->hookHelper->setPreviewLinkGrantingAccess(FALSE);

      $this->messenger()->addMessage($this->previewLinkMessages->getGrantMessage($entity->toUrl()));

      $this->entityTypeManager->getAccessControlHandler($entity->getEntityTypeId())->resetCache();
      $this->hookHelper->setPreviewLinkGrantingAccess(TRUE);
    }

    $view = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity);
    // Subsequent [cached] requests to the page need to be able to activate
    // links.
    CacheableMetadata::createFromRenderArray($view)
      ->addCacheContexts(['session'])
      ->applyTo($view);
    return $view;
  }

  /**
   * Preview page title.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   *
   * @return string|null
   *   The title of the entity.
   */
  public function title(RouteMatchInterface $routeMatch): ?string {
    return $this->resolveEntity($routeMatch)->label();
  }

  /**
   * Resolve the entity being previewed.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function resolveEntity(RouteMatchInterface $routeMatch): EntityInterface {
    $entityParameterName = $routeMatch->getRouteObject()->getOption('preview_link.entity_type_id');
    return $routeMatch->getParameter($entityParameterName);
  }

  /**
   * Claim a Preview Link token to the session.
   *
   * @param string $preview_token
   *   A validated Preview Link token.
   */
  protected function claimToken(string $preview_token): void {
    $collection = $this->privateTempStoreFactory->get('preview_link');
    $currentKeys = $collection->get('keys') ?? [];
    if (!in_array($preview_token, $currentKeys, TRUE)) {
      $currentKeys[] = $preview_token;
      // Writing the value will start a session if one doesnt exist.
      $collection->set('keys', $currentKeys);
    }
  }

}
