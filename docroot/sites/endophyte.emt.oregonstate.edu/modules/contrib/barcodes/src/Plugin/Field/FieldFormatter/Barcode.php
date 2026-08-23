<?php

declare(strict_types=1);

namespace Drupal\barcodes\Plugin\Field\FieldFormatter;

use Com\Tecnick\Barcode\Barcode as BarcodeGenerator;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\link\LinkItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'barcode' formatter.
 *
 * @FieldFormatter(
 *   id = "barcode",
 *   label = @Translation("Barcode"),
 *   field_types = {
 *     "email",
 *     "integer",
 *     "link",
 *     "string",
 *     "telephone",
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *     "bigint",
 *     "uuid",
 *   }
 * )
 */
#[FieldFormatter(
  id: 'barcode',
  label: new TranslatableMarkup('Barcode'),
  field_types: [
    'email',
    'integer',
    'link',
    'string',
    'telephone',
    'text',
    'text_long',
    'text_with_summary',
    'bigint',
    'uuid',
  ],
)]
class Barcode extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The token service.
   *
   * @var Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Barcode FieldFormatter constructor.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, Token $token, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->token = $token;
    $this->logger = $logger_factory->get('barcodes');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('token'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'type' => 'QRCODE',
      'format' => 'SVG',
      'color' => '#000000',
      'height' => 100,
      'width' => 100,
      'padding_top' => 0,
      'padding_right' => 0,
      'padding_bottom' => 0,
      'padding_left' => 0,
      'show_value' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Barcode type'),
      '#description' => $this->t('The barcode type.'),
      '#options' => BarcodeGenerator::BARCODETYPES,
      '#default_value' => $this->getSetting('type'),
    ];
    $settings['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Display format'),
      '#description' => $this->t('The display format, e.g. png, svg, jpg.'),
      '#options' => [
        'PNG' => $this->t('PNG Image'),
        'SVG' => $this->t('SVG Image'),
        'HTMLDIV' => $this->t('HTML DIV'),
        'UNICODE' => $this->t('Unicode String'),
        'BINARY' => $this->t('Binary String'),
      ],
      '#default_value' => $this->getSetting('format'),
    ];
    $settings['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#description' => $this->t('The color code.'),
      '#default_value' => $this->getSetting('color'),
    ];
    $settings['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height'),
      '#description' => $this->t('The height in pixels.'),
      '#min' => 0,
      '#size' => 10,
      '#default_value' => $this->getSetting('height'),
    ];
    $settings['width'] = [
      '#type' => 'number',
      '#title' => $this->t('Width'),
      '#description' => $this->t('The width in pixels.'),
      '#min' => 0,
      '#size' => 10,
      '#default_value' => $this->getSetting('width'),
    ];
    $settings['padding_top'] = [
      '#type' => 'number',
      '#title' => $this->t('Top padding'),
      '#description' => $this->t('The top padding in pixels.'),
      '#size' => 4,
      '#maxlength' => 4,
      '#default_value' => $this->getSetting('padding_top'),
    ];
    $settings['padding_right'] = [
      '#type' => 'number',
      '#title' => $this->t('Right padding'),
      '#description' => $this->t('The right padding in pixels.'),
      '#size' => 4,
      '#maxlength' => 4,
      '#default_value' => $this->getSetting('padding_right'),
    ];
    $settings['padding_bottom'] = [
      '#type' => 'number',
      '#title' => $this->t('Bottom padding'),
      '#description' => $this->t('The bottom padding in pixels.'),
      '#size' => 4,
      '#maxlength' => 4,
      '#default_value' => $this->getSetting('padding_bottom'),
    ];
    $settings['padding_left'] = [
      '#type' => 'number',
      '#title' => $this->t('Left padding'),
      '#description' => $this->t('The left padding in pixels.'),
      '#size' => 4,
      '#maxlength' => 4,
      '#default_value' => $this->getSetting('padding_left'),
    ];
    $settings['show_value'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show value'),
      '#description' => $this->t('Show the actual value in addition to the barcode.'),
      '#default_value' => $this->getSetting('show_value'),
    ];
    return $settings + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary[] = $this->t('Type: %type </br> Display format: %format', [
      '%type' => $this->getSetting('type'),
      '%format' => $this->getSetting('format'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $generator = new BarcodeGenerator();
    foreach ($items as $delta => $item) {
      $suffix = str_replace(
        '+', 'plus', strtolower($this->getSetting('type'))
      );

      $tokens = [];
      if ($entity = $items->getEntity()) {
        $tokens[$entity->getEntityTypeId()] = $entity;
      }

      $value = $this->token->replace($this->viewValue($item), $tokens);

      $elements[$delta] = [
        '#theme' => 'barcode__' . $suffix,
        '#attached' => [
          'library' => [
            'barcodes/' . $suffix,
          ],
        ],
        '#type' => $this->getSetting('type'),
        '#value' => $value,
        '#width' => $this->getSetting('width'),
        '#height' => $this->getSetting('height'),
        '#color' => $this->getSetting('color'),
        '#padding_top' => $this->getSetting('padding_top'),
        '#padding_right' => $this->getSetting('padding_right'),
        '#padding_bottom' => $this->getSetting('padding_bottom'),
        '#padding_left' => $this->getSetting('padding_left'),
        '#show_value' => $this->getSetting('show_value'),
      ];

      try {
        $barcode = $generator->getBarcodeObj(
          $this->getSetting('type'),
          $value,
          $this->getSetting('width'),
          $this->getSetting('height'),
          $this->getSetting('color'),
          [
            $this->getSetting('padding-top'),
            $this->getSetting('padding-right'),
            $this->getSetting('padding-bottom'),
            $this->getSetting('padding-left'),
          ]
        );
        $elements[$delta]['#format'] = $this->getSetting('format');
        $elements[$delta]['#svg'] = $barcode->getSvgCode();
        $elements[$delta]['#png'] = "<img alt=\"Embedded Image\" src=\"data:image/png;base64," . base64_encode($barcode->getPngData()) . "\" />";
        $elements[$delta]['#htmldiv'] = $barcode->getHtmlDiv();
        $elements[$delta]['#unicode'] = "<pre style=\"font-family:monospace;line-height:0.61em;font-size:6px;\">" . $barcode->getGrid(json_decode('"\u00A0"'), json_decode('"\u2584"')) . "</pre>";
        $elements[$delta]['#binary'] = "<pre style=\"font-family:monospace;\">" . $barcode->getGrid() . "</pre>";
        $elements[$delta]['#barcode'] = $elements[$delta]['#' . strtolower($this->getSetting('format'))];
        $elements[$delta]['#extended_value'] = $barcode->getExtendedCode();
      }
      catch (\Exception $e) {
        $this->logger->error('Error: @error, given: @value', [
          '@error' => $e->getMessage(),
          '@value' => $this->viewValue($item),
        ]);
      }
    }
    return $elements;
  }

  /**
   * Generates the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    if ($item instanceof LinkItemInterface) {
      // Always want URLs encoded as barcodes to be absolute.
      $item->options += ['absolute' => TRUE];
      $value = $item->getUrl()->toString();
    }
    elseif ($item->mainPropertyName()) {
      $value = $item->__get($item->mainPropertyName());
    }
    else {
      $value = $item->getValue();
    }
    return $value;
  }

}
