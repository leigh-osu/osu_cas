<?php

namespace Drupal\sitewide_alert\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Render\RendererInterface;
use Drupal\sitewide_alert\SitewideAlertManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for callback that loads the alerts visible as JSON object.
 */
class SitewideAlertsController extends ControllerBase {

  /**
   * Constructs a new SitewideAlertsController.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\sitewide_alert\SitewideAlertManager $sitewideAlertManager
   *   The sitewide alert manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The page cache disabling policy.
   */
  public function __construct(
    protected RendererInterface $renderer,
    protected SitewideAlertManager $sitewideAlertManager,
    ConfigFactoryInterface $configFactory,
    protected KillSwitch $killSwitch,
  ) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): SitewideAlertsController {
    return new static(
      $container->get('renderer'),
      $container->get('sitewide_alert.sitewide_alert_manager'),
      $container->get('config.factory'),
      $container->get('page_cache_kill_switch')
    );
  }

  /**
   * Load.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return Hello string.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function load(): JsonResponse {
    $response = new CacheableJsonResponse([]);

    $sitewideAlertsJson = ['sitewideAlerts' => []];

    $sitewideAlerts = $this->sitewideAlertManager->activeVisibleSitewideAlerts();

    $sitewideAlertSettings = $this->configFactory->get('sitewide_alert.settings');

    if ($sitewideAlertSettings->get('display_order') === 'descending') {
      krsort($sitewideAlerts, SORT_NUMERIC);
    }

    $viewBuilder = $this->entityTypeManager()->getViewBuilder('sitewide_alert');

    foreach ($sitewideAlerts as $sitewideAlert) {
      $alertView = $viewBuilder->view($sitewideAlert);

      $sitewideAlertsJson['sitewideAlerts'][] = [
        'uuid' => $sitewideAlert->uuid(),
        'dismissible' => $sitewideAlert->isDismissible(),
        'dismissalIgnoreBefore' => $sitewideAlert->getDismissibleIgnoreBeforeTime(),
        'styleClass' => $sitewideAlert->getStyleClass(),
        'showOnPages' => $sitewideAlert->getPagesToShowOn(),
        'negateShowOnPages' => $sitewideAlert->shouldNegatePagesToShowOn(),
        'renderedAlert' => $this->renderer->renderInIsolation($alertView),
        'changed' => $sitewideAlert->get('changed')->value,
      ];
    }

    // Set response cache, so it's invalidated whenever alerts get updated, or
    // settings are changed.
    $cacheMaxAge = $sitewideAlertSettings->get('cache_max_age') ?: 15;
    $cacheableMetadata = (new CacheableMetadata())
      ->setCacheMaxAge(min([30, $cacheMaxAge]))
      ->addCacheContexts(['languages'])
      ->setCacheTags(['sitewide_alert_list']);
    $cacheableMetadata->addCacheableDependency($sitewideAlertSettings);

    $response->addCacheableDependency($cacheableMetadata);
    $response->setData($sitewideAlertsJson);

    // Set the date this response expires so that Drupal's Page Cache will
    // expire this response when the next scheduled alert will be removed.
    // This is needed because Page Cache ignores max age as it does not respect
    // the cache max age. Note that the cache tags will still invalidate this
    // response in the case that new sitewide alerts are added or changed.
    // See Drupal\page_cache\StackMiddleware:storeResponse().
    if ($expireDate = $this->sitewideAlertManager->nextScheduledChange()) {
      $response->setExpires($expireDate->getPhpDateTime());
    }

    // Prevent the browser and downstream caches from caching for more than the
    // configured cache max age, in seconds.
    $response->setMaxAge($cacheMaxAge);
    $response->setSharedMaxAge($cacheMaxAge);

    // Temporary Drupal core issue workaround where if caching is disabled on
    // the site, then the responses should never be cached.
    // https://www.drupal.org/project/drupal/issues/2835068#comment-13944070
    // @todo Remove once upstream page_cache issue has been addressed.
    if ($this->configFactory->get('system.performance')->get('cache.page.max_age') === 0) {
      $this->killSwitch->trigger();
    }

    return $response;
  }

}
