<?php

namespace Drupal\toc_js_filter\Plugin\Filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\toc_js\Service\TocJsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Replaces [toc] with the rendered toc_js block using filter-level settings.
 *
 * @Filter(
 *   id = "toc_js_filter",
 *   title = @Translation("TOC.js shortcode: [toc]"),
 *   description = @Translation("Provides a filter that replaces [toc] with a
 *   table of contents"), type =
 *   \Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE
 * )
 */
final class TocJsFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * TocJsFilter constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The current route match service.
   * @param \Drupal\toc_js\Service\TocJsService $tocjsService
   *   The TocJs service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly RendererInterface $renderer,
    private readonly TocJsService $tocjsService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer') instanceof RendererInterface
        ? $container->get('renderer')
        : throw new \InvalidArgumentException('Renderer service not found.'),
      $container->get('toc_js.service') instanceof TocJsService
        ? $container->get('toc_js.service')
        : throw new \InvalidArgumentException('TocJs service not found.')
    );
  }

  /**
   * Settings form (mirrors the toc_js block form).
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The settings form array.
   */
  public function settingsForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $settings = $this->settings + $this->tocjsService->defaultSettings();
    $this->tocjsService->getTocForm($form, $settings, $form['#parents']);
    return $form;
  }

  /**
   * Replace [toc] with a rendered toc_js block configured from filter settings.
   *
   * @param string $text
   *   The text to process.
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\filter\FilterProcessResult
   *   The processed result.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException|\Exception
   *   If something goes wrong.
   */
  public function process($text, $langcode): FilterProcessResult {
    if (stripos($text, '[toc]') === FALSE) {
      return new FilterProcessResult($text);
    }
    $result = new FilterProcessResult($text);
    $settings = $this->settings + $this->tocjsService->defaultSettings();
    $pattern = '/\[toc]/i';
    $processed = preg_replace_callback(
      $pattern,
      function () use ($result, $settings) {
        $build = $this->tocjsService->buildToc($this->pluginId, $settings);
        $render_context = new RenderContext();
        $html = $this->renderer->executeInRenderContext(
          $render_context,
          function () use (&$build) {
            return (string) $this->renderer->render($build);
          }
        );
        if (!$render_context->isEmpty()) {
          $metadata = $render_context->pop();
          $result->addCacheableDependency($metadata);
          $result->addCacheTags($this->tocjsService->getTocCacheTags());
          $result->addCacheContexts($this->tocjsService->getTocCacheContexts());
          $existing = $result->getAttachments();
          $result->setAttachments([
            'library' => array_unique(array_merge(
              $existing['library'] ?? [],
              $metadata->getAttachments()['library'] ?? []
            )),
            'drupalSettings' => array_replace_recursive(
              $existing['drupalSettings'] ?? [],
              $metadata->getAttachments()['drupalSettings'] ?? []
            ),
          ]);
        }
        return $html;
      },
      $text,
      -1
    );
    $result->setProcessedText($processed);
    return $result;
  }

}
