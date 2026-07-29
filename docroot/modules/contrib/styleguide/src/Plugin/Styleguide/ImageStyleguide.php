<?php

namespace Drupal\styleguide\Plugin\Styleguide;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\File\FileExists;
use Drupal\image\Entity\ImageStyle;
use Drupal\styleguide\Plugin\StyleguidePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Image styles Styleguide items implementation.
 *
 * @Plugin(
 *   id = "image_styleguide",
 *   label = @Translation("Image styles Styleguide elements"),
 *   provider = "image",
 * )
 */
class ImageStyleguide extends StyleguidePluginBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileSystem = $container->get('file_system');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function items() {
    $items = [];

    // Get the sample file provided by the module.
    $preview_img_path = 'public://styleguide-preview.jpg';
    if ($this->fileSystem->getDestinationFilename($preview_img_path, FileExists::Error) !== FALSE) {
      // Move the image so that styles may be applied.
      $this->fileSystem->copy($this->generator->image('vertical'), $preview_img_path, FileExists::Error);
    }

    // Iterate through the image styles on the site.
    foreach (ImageStyle::loadMultiple() as $stylename => $style) {
      $details = [];
      foreach ($style->getEffects() as $effect) {
        $summary = $effect->getSummary();
        $summary = $this->renderer->render($summary);
        $label = $effect->label();
        if ($summary) {
          $details[] = new FormattableMarkup('%label: @summary', [
            '%label' => $label,
            '@summary' => $summary,
          ]);
        }
        else {
          $details[] = new FormattableMarkup('%label', [
            '%label' => $label,
          ]);
        }
      }

      $title = $this->t('Image style, @stylename', ['@stylename' => $style->get('label')]);
      $items['image_' . $stylename] = [
        'title' => $title,
        'description' => [
          '#theme' => 'item_list',
          '#items' => $details,
        ],
        'content' => [
          '#theme' => 'image_style',
          '#uri' => $preview_img_path,
          '#style_name' => $stylename,
          '#alt' => $title,
          '#title' => $title,
        ],
        'group' => $this->t('Media'),
      ];
    }

    return $items;
  }

}
