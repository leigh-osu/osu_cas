<?php

namespace Drupal\background_image_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Annotation\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the background_image_formatter.
 *
 * @FieldFormatter(
 *  id = "background_image_formatter",
 *  label = @Translation("Background Image"),
 *  field_types = {"image"}
 * )
 */
class BackgroundImageFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'image_style' => '',
      'background_image_output_type' => 'inline',
      'background_image_selector' => '',
      'background_image_link' => FALSE,
      'background_image_link_custom' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $element = [];

    $image_styles = image_style_options(FALSE);

    $element['image_style'] = [
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#options' => $image_styles,
      '#default_value' => $this->getSetting('image_style'),
      '#empty_option' => $this->t('None (original image)'),
      '#description' => $this->t('Select the image style to use.'),
    ];

    $element['background_image_output_type'] = [
      '#title' => $this->t('Output To'),
      '#type' => 'select',
      '#options' => [
        'inline' => $this->t('Write background-image to inline style attribute'),
        'css' => $this->t('Write background-image to CSS selector'),
      ],
      '#default_value' => $this->getSetting('background_image_output_type'),
      '#required' => TRUE,
      '#description' => $this->t('Define how background-image will be printed to the dom.'),
    ];

    $element['background_image_selector'] = [
      '#title' => $this->t('CSS Selector'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('background_image_selector'),
      '#required' => FALSE,
      '#description' => $this->t('CSS selector that image(s) are attached to.'),
    ];

    $element['background_image_link'] = [
      '#title' => $this->t('Link image to content'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('background_image_link'),
      '#states' => [
        'visible' => [
          'select[name*="background_image_output_type"]' => ['value' => 'inline'],
        ],
      ],
    ];

    $element['background_image_link_custom'] = [
      '#title' => $this->t('Custom link'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('background_image_link_custom'),
      '#states' => [
        'visible' => [
          'select[name*="background_image_output_type"]' => ['value' => 'inline'],
          'input[name*="background_image_link"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t("Link to use. Leave empty for the content's URL. Can contain tokens when Token module is enabled."),
    ];

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $element['tokens'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => 'all',
        '#global_types' => TRUE,
        '#click_insert' => TRUE,
        '#show_restricted' => FALSE,
        '#recursion_limit' => 3,
        '#text' => $this->t('Browse available tokens'),
      ];
    }

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $summary = [];

    $image_styles = image_style_options(FALSE);

    unset($image_styles['']);

    $select_style = $this->getSetting('image_style');

    if (isset($image_styles[$select_style])) {
      $summary[] = $this->t('URL for image style: @style', ['@style' => $image_styles[$select_style]]);
    }
    else {
      $summary[] = $this->t('Original image');
    }

    $summary[] = $this->t('Output type: @output_type', ['@output_type' => $this->getSetting('background_image_output_type')]);

    if ($this->getSetting('background_image_link')) {
      $summary[] = $this->t('Linked to content');
      if ($this->getSetting('background_image_link_custom')) {
        $summary[] = $this->t('Custom link: @link', ['@link' => $this->getSetting('background_image_link_custom')]);
      }
    }

    $summary[] = $this->t('The CSS selector <code>@background_image_selector</code> will be created with the image set to the background-image property.', [
      '@background_image_selector' => $this->getSetting('background_image_selector') . '_id',
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $elements = [];

    $image_style = NULL;

    if (!$this->isBackgroundImageDisplay()) {
      return $elements;
    }

    $image_style = $this->getSetting('image_style');

    if (!empty($image_style)) {
      $image_style = ImageStyle::load($image_style);
    }

    $files = $this->getEntitiesToView($items, $langcode);

    foreach ($files as $delta => $entity) {

      if ($files instanceof EntityInterface) {
        continue;
      }

      $image_url = \Drupal::service('file_url_generator')->generateString($entity->getFileUri());
      $id = $entity->id();

      if ($image_style) {
        $image_uri = $entity->getFileUri();

        $image_url = ImageStyle::load($image_style->getName())
          ->buildUrl($image_uri);
      }

      $selector = strip_tags($this->getSetting('background_image_selector'));

      // Only add an id when using inline styles.
      if ($this->getSetting('background_image_output_type') === 'inline') {
        $selector .= '_' . $id;
      }

      $host_entity = $items->getEntity();

      $theme = [
        '#background_image_selector' => $selector,
        '#image_uri' => $image_url,
        '#entity_type' => $host_entity->getEntityTypeId(),
        '#entity_bundle' => $host_entity->bundle(),
        '#entity' => $host_entity,
        '#field_name' => $items->getName(),
        '#delta' => $delta,
      ];

      array_push($elements, $this->renderElements($delta, $theme, $id, $items));
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function isBackgroundImageDisplay() {
    return $this->getPluginId() == 'background_image_formatter';
  }

  /**
   * {@inheritdoc}
   */
  protected function generateCssString($theme) {
    return $theme['#background_image_selector'] . ' {background-image: url("' . $theme['#image_uri'] . '");}';
  }

  /**
   * Render image elements.
   *
   * @param int $delta
   *   If multifield number of element.
   * @param array $theme
   *   Theme render array.
   * @param string $id
   *   Field id.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items being rendered.
   *
   * @return array|null
   *   Rendered array of elements.
   */
  public function renderElements($delta, array $theme, $id, FieldItemListInterface $items) {
    $elements = NULL;
    switch ($this->getSetting('background_image_output_type')) {
      case 'css':

        $data = [
          '#tag' => 'style',
          '#value' => $this->generateCssString($theme),
        ];

        $elements['#attached']['html_head'][] = [
          $data,
          'background_image_formatter_' . $id,
        ];

        break;

      case 'inline':

        if ($url = $this->getBackgroundImageLinkUrl($items)) {
          $theme['#url'] = $url;
        }

        $theme['#theme'] = 'background_image_formatter_inline';

        $elements[$delta] = [
          '#markup' => \Drupal::service('renderer')->render($theme),
        ];

        break;
    }

    return $elements;
  }

  /**
   * Builds the configured link URL for inline background image output.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items being rendered.
   *
   * @return string|null
   *   The URL string, or NULL when no link should be rendered.
   */
  protected function getBackgroundImageLinkUrl(FieldItemListInterface $items) {
    if (!$this->getSetting('background_image_link')) {
      return NULL;
    }

    $entity = $items->getEntity();
    $custom_link = trim((string) $this->getSetting('background_image_link_custom'));

    if ($custom_link !== '') {
      if (\Drupal::moduleHandler()->moduleExists('token')) {
        $custom_link = \Drupal::service('token')->replace($custom_link, [
          $entity->getEntityTypeId() => $entity,
        ]);
      }
      return UrlHelper::stripDangerousProtocols($custom_link);
    }

    if (!$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
      return $entity->toUrl()->toString();
    }

    return NULL;
  }

}
