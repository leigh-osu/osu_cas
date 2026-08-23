<?php

declare(strict_types=1);

namespace Drupal\barcodes\Plugin\Block;

use Com\Tecnick\Barcode\Barcode as BarcodeGenerator;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to display data in Barcode form.
 *
 * @Block(
 *  id = "barcode",
 *  admin_label = @Translation("Barcode"),
 * )
 */
#[Block(
  id: "barcode",
  admin_label: new TranslatableMarkup("Barcode"),
)]
class Barcode extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The currently active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The currently active route match object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, ModuleHandlerInterface $module_handler, Token $token, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
    $this->token = $token;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.barcodes'),
      $container->get('module_handler'),
      $container->get('token'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'type' => 'QRCODE',
      'format' => 'SVG',
      'value' => '',
      'color' => '#000000',
      'height' => 100,
      'width' => 100,
      'padding_top' => 0,
      'padding_right' => 0,
      'padding_bottom' => 0,
      'padding_left' => 0,
      'show_value' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#description' => $this->t('The value to display as a barcode. This field supports tokens.'),
      '#default_value' => $this->configuration['value'],
    ];
    if ($this->moduleHandler->moduleExists('token')) {
      $form['value']['#description'] = [
        '#theme' => 'token_tree_link',
        '#prefix' => $this->t('The value to display as a barcode. This field supports tokens.'),
        '#token_types' => ['node'],
      ];
      $form['value'] += [
        '#element_validate' => ['token_element_validate'],
        '#token_types' => ['node'],
      ];
    }

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Barcode type'),
      '#description' => $this->t('The barcode type.'),
      '#options' => BarcodeGenerator::BARCODETYPES,
      '#default_value' => $this->configuration['type'],
    ];

    $form['format'] = [
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
      '#default_value' => $this->configuration['format'],
    ];
    $form['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#description' => $this->t('The color code.'),
      '#default_value' => $this->configuration['color'],
    ];
    $form['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height'),
      '#description' => $this->t('The height in pixels.'),
      '#min' => 0,
      '#size' => 10,
      '#default_value' => $this->configuration['height'],
    ];
    $form['width'] = [
      '#type' => 'number',
      '#title' => $this->t('Width'),
      '#description' => $this->t('The width in pixels.'),
      '#min' => 0,
      '#size' => 10,
      '#default_value' => $this->configuration['width'],
    ];
    $form['padding_top'] = [
      '#type' => 'number',
      '#title' => $this->t('Top padding'),
      '#description' => $this->t('The top padding in pixels.'),
      '#size' => 4,
      '#maxlength' => 4,
      '#default_value' => $this->configuration['padding_top'],
    ];
    $form['padding_right'] = [
      '#type' => 'number',
      '#title' => $this->t('Right padding'),
      '#description' => $this->t('The right padding in pixels.'),
      '#size' => 4,
      '#maxlength' => 4,
      '#default_value' => $this->configuration['padding_right'],
    ];
    $form['padding_bottom'] = [
      '#type' => 'number',
      '#title' => $this->t('Bottom padding'),
      '#description' => $this->t('The bottom padding in pixels.'),
      '#size' => 4,
      '#maxlength' => 4,
      '#default_value' => $this->configuration['padding_bottom'],
    ];
    $form['padding_left'] = [
      '#type' => 'number',
      '#title' => $this->t('Left padding'),
      '#description' => $this->t('The left padding in pixels.'),
      '#size' => 4,
      '#maxlength' => 4,
      '#default_value' => $this->configuration['padding_left'],
    ];
    $form['show_value'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show value'),
      '#description' => $this->t('Show the actual value in addition to the barcode.'),
      '#default_value' => $this->configuration['show_value'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $generator = new BarcodeGenerator();
    $suffix = str_replace(
      '+', 'plus', strtolower($this->configuration['type'])
    );

    $tokens = [];
    $parameters = $this->routeMatch->getParameters();
    foreach ($parameters as $parameter) {
      if ($parameter instanceof EntityInterface) {
        $tokens[$parameter->getEntityTypeId()] = $parameter;
      }
    }

    $value = $this->token->replace($this->configuration['value'], $tokens);

    $build['barcode'] = [
      '#theme' => 'barcode__' . $suffix,
      '#attached' => [
        'library' => [
          'barcodes/' . $suffix,
        ],
      ],
      '#type' => $this->configuration['type'],
      '#value' => $value,
      '#width' => $this->configuration['width'],
      '#height' => $this->configuration['height'],
      '#color' => $this->configuration['color'],
      '#padding_top' => $this->configuration['padding_top'],
      '#padding_right' => $this->configuration['padding_right'],
      '#padding_bottom' => $this->configuration['padding_bottom'],
      '#padding_left' => $this->configuration['padding_left'],
      '#show_value' => $this->configuration['show_value'],
    ];

    try {
      $barcode = $generator->getBarcodeObj(
        $this->configuration['type'],
        $value,
        $this->configuration['width'],
        $this->configuration['height'],
        $this->configuration['color'],
        [
          $this->configuration['padding_top'],
          $this->configuration['padding_right'],
          $this->configuration['padding_bottom'],
          $this->configuration['padding_left'],
        ]
      );
      $build['barcode']['#format'] = $this->configuration['format'];
      $build['barcode']['#svg'] = $barcode->getSvgCode();
      $build['barcode']['#png'] = "<img alt=\"Embedded Image\" src=\"data:image/png;base64," . base64_encode($barcode->getPngData()) . "\" />";
      $build['barcode']['#htmldiv'] = $barcode->getHtmlDiv();
      $build['barcode']['#unicode'] = "<pre style=\"font-family:monospace;line-height:0.61em;font-size:6px;\">" . $barcode->getGrid(json_decode('"\u00A0"'), json_decode('"\u2584"')) . "</pre>";
      $build['barcode']['#binary'] = "<pre style=\"font-family:monospace;\">" . $barcode->getGrid() . "</pre>";
      $build['barcode']['#barcode'] = $build['barcode']['#' . strtolower($this->configuration['format'])];
      $build['barcode']['#extended_value'] = $barcode->getExtendedCode();
    }
    catch (\Exception $e) {
      $this->logger->error('Error: @error, given: @value', [
        '@error' => $e->getMessage(),
        '@value' => $this->configuration['value'],
      ]);
    }
    return $build;
  }

}
