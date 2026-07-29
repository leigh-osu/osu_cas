<?php

declare(strict_types=1);

namespace Drupal\layout_builder_iframe_modal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller to render the iframe redirect page.
 */
class RedirectController extends ControllerBase {

  /**
   * RedirectController constructor.
   *
   * @param \Drupal\Core\Render\AttachmentsResponseProcessorInterface $attachmentProcessor
   *   The Container.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    protected AttachmentsResponseProcessorInterface $attachmentProcessor,
    protected RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RedirectController {
    return new static(
      $container->get('html_response.attachments_processor'),
      $container->get('renderer')
    );
  }

  /**
   * Renders and returns the redirect page.
   *
   * @return \Drupal\Core\Render\AttachmentsInterface
   *   The response.
   */
  public function content(): AttachmentsInterface {
    $build = [
      '#theme' => 'html',
      'page' => [
        '#theme' => 'lbim_redirect',
        '#title' => 'Redirecting...',
      ],
    ];
    $this->renderer->renderRoot($build);

    $response = new HtmlResponse();
    $response->setContent($build);
    return $this->attachmentProcessor->processAttachments($response);
  }

}
