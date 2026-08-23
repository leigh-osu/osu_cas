<?php

declare(strict_types=1);

namespace Drupal\entityreference_filter\Service;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\views\Views;

/**
 * Builds the option list of a reference (entity_reference) view display.
 */
final class ReferenceViewOptionsBuilder {

  /**
   * Logger channel.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs a ReferenceViewOptionsBuilder object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(
    protected RendererInterface $renderer,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('entityreference_filter');
  }

  /**
   * Builds the options of a reference view display.
   *
   * @param string $reference_display
   *   The reference display in "view_name:display_id" format.
   * @param array $args
   *   The contextual arguments to pass to the reference view.
   *
   * @return array|null
   *   Ordered entity_id => label options, or NULL when the view is missing.
   */
  public function buildOptions(string $reference_display, array $args): ?array {
    if ($reference_display === '' || !str_contains($reference_display, ':')) {
      return [];
    }

    [$view_name, $display_id] = explode(':', $reference_display, 2);

    $view = Views::getView($view_name);

    if (!$view) {
      $this->logger->warning('The view %view_name is no longer eligible for the filter.', ['%view_name' => $view_name]);
      return NULL;
    }

    if (!$view->access($display_id)) {
      return NULL;
    }

    $view->setDisplay($display_id);
    $view->setItemsPerPage(0);

    // Set `entity_reference_options` for the new EntityReference view.
    $view->displayHandlers->get($display_id)->setOption('entity_reference_options', ['limit' => NULL]);

    $results = $view->executeDisplay($display_id, $args);

    $options = [];
    foreach ($results ?? [] as $renderable) {
      $entity = $renderable['#row']->_entity;
      if ($entity instanceof EntityBase) {
        $option = (string) $this->renderer->renderInIsolation($renderable);
        $options[$entity->id()] = trim(PlainTextOutput::renderFromHtml($option));
      }
    }

    return $options;
  }

}
