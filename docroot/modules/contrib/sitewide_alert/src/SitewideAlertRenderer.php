<?php

declare(strict_types=1);

namespace Drupal\sitewide_alert;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\sitewide_alert\Entity\SitewideAlertInterface;

/**
 * Sitewide Alert placeholder render array builder service.
 *
 * Used in default page_top display and by block submodule.
 */
class SitewideAlertRenderer implements SitewideAlertRendererInterface {

  use StringTranslationTrait;

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   Admin context service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\sitewide_alert\SitewideAlertManager $sitewideAlertManager
   *   The sitewide alert manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path service.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    protected AdminContext $adminContext,
    protected AccountProxyInterface $currentUser,
    protected SitewideAlertManager $sitewideAlertManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CurrentPathStack $currentPath,
    protected PathMatcherInterface $pathMatcher,
    protected TimeInterface $time,
  ) {
    $this->config = $configFactory->get('sitewide_alert.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function build(bool $adminAware = TRUE): array {
    // Do not show alert on admin pages if we are not configured to do so
    // or when we don't have enough permissions.
    if (!$this->currentUser->hasPermission('view published sitewide alert entities')
      || ($adminAware && !$this->config->get('show_on_admin') && $this->adminContext->isAdminRoute())) {
      // Populate an empty render array with cache-metadata to invalidate when
      // settings change and to add user permissions to the context.
      $build = [];
      $this->getCacheableMetadata()->applyTo($build);
      return $build;
    }

    // Server-side render if enabled.
    if ($this->config->get('server_side_render')) {
      return $this->buildWithServerSideRendering();
    }

    // Client-side only rendering (default behavior).
    return $this->buildClientSideOnly();
  }

  /**
   * Builds the render array for client-side only rendering.
   *
   * @return array
   *   The render array with placeholder for JS to populate.
   */
  protected function buildClientSideOnly(): array {
    $build = $this->getBuild();
    $this->getCacheableMetadata()->applyTo($build);
    return $build;
  }

  /**
   * Builds the render array with server-side rendered alerts.
   *
   * @return array
   *   The render array with alerts rendered inline.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildWithServerSideRendering(): array {
    $cacheMetadata = $this->getCacheableMetadata();

    // Invalidate the cache when any of the alerts change or are added.
    $cacheMetadata->addCacheTags(['sitewide_alert_list']);

    // Add url.path since SSR varies by path, and languages:language_interface
    // since alert content may be translated per language.
    $cacheMetadata->addCacheContexts(['url.path', 'languages:language_interface']);

    // Set max-age based on next scheduled change (alert appearing/expiring).
    if ($nextChange = $this->sitewideAlertManager->nextScheduledChange()) {
      $secondsUntilChange = $nextChange->getTimestamp() - $this->time->getRequestTime();
      if ($secondsUntilChange > 0) {
        $cacheMetadata->setCacheMaxAge($secondsUntilChange);
      }
    }

    $alerts = $this->sitewideAlertManager->activeVisibleSitewideAlerts();
    $currentPath = $this->currentPath->getPath();

    // Filter alerts by current path.
    $alertsToRender = $this->filterAlertsByPath($alerts, $currentPath);

    // Apply display order.
    $displayOrder = $this->config->get('display_order');
    if ($displayOrder === 'descending') {
      krsort($alertsToRender, SORT_NUMERIC);
    }

    // Render each alert.
    $renderedAlerts = [];
    $viewBuilder = $this->entityTypeManager->getViewBuilder('sitewide_alert');
    foreach ($alertsToRender as $alert) {
      $cacheMetadata->addCacheableDependency($alert);
      $renderedAlerts[] = $viewBuilder->view($alert);
    }

    // Build the render array.
    $build = $this->getBuild($renderedAlerts);

    // Add landmark role only when there are alerts to render.
    if (!empty($renderedAlerts)) {
      $build['#attributes']['role'] = 'region';
      $build['#attributes']['aria-label'] = $this->t('Site alerts');
    }

    $cacheMetadata->applyTo($build);
    return $build;
  }

  /**
   * Filters alerts by the current path.
   *
   * @param \Drupal\sitewide_alert\Entity\SitewideAlertInterface[] $alerts
   *   The alerts to filter.
   * @param string $currentPath
   *   The current request path.
   *
   * @return \Drupal\sitewide_alert\Entity\SitewideAlertInterface[]
   *   Alerts that should be shown on the current path.
   */
  protected function filterAlertsByPath(array $alerts, string $currentPath): array {
    return array_filter($alerts,
      fn($alert) => $this->alertShouldShowOnPath($alert, $currentPath));
  }

  /**
   * Determines if an alert should show on the given path.
   *
   * @param \Drupal\sitewide_alert\Entity\SitewideAlertInterface $alert
   *   The alert entity.
   * @param string $currentPath
   *   The current request path.
   *
   * @return bool
   *   TRUE if the alert should show on this path.
   */
  protected function alertShouldShowOnPath(SitewideAlertInterface $alert, string $currentPath): bool {
    $pages = $alert->getPagesToShowOn();

    // No page restrictions means show on all pages.
    if (empty($pages)) {
      return TRUE;
    }

    $negate = $alert->shouldNegatePagesToShowOn();
    $pagesPattern = implode("\n", $pages);
    $pathMatches = $this->pathMatcher->matchPath($currentPath, $pagesPattern);

    return $negate ? !$pathMatches : $pathMatches;
  }

  /**
   * Gets the base cacheable metadata for sitewide alerts.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   Cacheable metadata with config dependencies and user.permissions context.
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return CacheableMetadata::createFromObject($this->config)
      ->addCacheContexts(['user.permissions']);
  }

  /**
   * Builds the base render array for sitewide alerts.
   *
   * @param array|null $renderedAlerts
   *   Optional array of rendered alert elements to include.
   *
   * @return array
   *   The render array with html_tag container and attached library.
   */
  public function getBuild(?array $renderedAlerts = []): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'data-sitewide-alert' => TRUE,
        'aria-live' => 'polite',
      ],
      'alerts' => $renderedAlerts,
      '#attached' => [
        'library' => [
          'sitewide_alert/init',
        ],
        'drupalSettings' => [
          'sitewideAlert' => [
            'refreshInterval' => (int) ($this->config->get('refresh_interval') ?? 15) * 1000,
            'automaticRefresh' => ($this->config->get('automatic_refresh') == 1),
            'showCount' => ($this->config->get('show_count') == 1),
            'serverSideRender' => (bool) $this->config->get('server_side_render'),
          ],
        ],
      ],
    ];
  }

}
