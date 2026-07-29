<?php

namespace Drupal\text_field_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'text_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "text_field_formatter",
 *   label = @Translation("Text field formatter"),
 *   field_types = {
 *     "string",
 *   },
 *   edit = {
 *     "editor" = "form"
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class TextFieldFormatter extends StringFormatter {

  /**
   * The module handler firing the route_link alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a TextFieldFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct($plugin_id,
                              $plugin_definition,
                              FieldDefinitionInterface $field_definition,
                              array $settings,
                              $label,
                              $view_mode,
                              array $third_party_settings,
                              EntityTypeManagerInterface $entity_type_manager,
                              ModuleHandlerInterface $module_handler,
                              MessengerInterface $messenger,
                              Token $token) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $entity_type_manager);

    $this->moduleHandler = $module_handler;
    $this->messenger = $messenger;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('messenger'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'wrap_tag' => '_none',
      'wrap_class' => '',
      'wrap_attributes' => '',
      'override_link_label' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultWrapTagOptions() {
    $wrappers = [
      'div' => $this->t('Div'),
      'h1' => $this->t('H1'),
      'h2' => $this->t('H2'),
      'h3' => $this->t('H3'),
      'h4' => $this->t('H4'),
      'h5' => $this->t('H5'),
      'h6' => $this->t('H6'),
      'span' => $this->t('Span'),
    ];

    $this->moduleHandler->alter('default_wrap_tags', $wrappers);

    if (isset($wrappers['a'])) {
      unset($wrappers['a']);
      $this->messenger->addWarning($this->t('Tag "a" is not allowed here since it can conflict with other functional.'));
    }

    return $wrappers;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['wrap_tag'] = [
      '#title' => $this->t('Field wrapper'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('wrap_tag'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->defaultWrapTagOptions(),
    ];

    $form['wrap_class'] = [
      '#title' => $this->t('Wrapper classes'),
      '#type' => 'textfield',
      '#maxlength' => 128,
      '#default_value' => $this->getSetting('wrap_class'),
      '#description' => $this->t('Separate multiple classes with space or comma. Works only with the selected
      wrapper tag.'),
    ];

    $form['wrap_attributes'] = [
      '#title' => $this->t('Wrapper attributes'),
      '#type' => 'textarea',
      '#default_value' => $this->getSetting('wrap_attributes'),
      '#description' => $this->t('Set attributes for this wrapper. Enter one value per line,
      in the format attribute|value. The value is optional.'),
    ];

    $form['override_link_label'] = [
      '#title' => $this->t('Override link label'),
      '#type' => 'textfield',
      '#maxlength' => 128,
      '#default_value' => $this->getSetting('override_link_label'),
      '#states' => [
        'visible' => [
          'input[name$="[link_to_entity]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $form['token'] = [
        '#type' => 'item',
        '#theme' => 'token_tree_link',
        '#token_types' => 'all',
        '#states' => [
          'visible' => [
            'input[name$="[link_to_entity]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $wrap_tag = $this->getSetting('wrap_tag');
    if ('_none' === $wrap_tag) {
      $summary[] = $this->t('No wrap tag defined.');
    }
    else {
      $summary[] = $this->t('Wrap text with tag: @tag', ['@tag' => $wrap_tag]);
    }

    $class = $this->getSetting('wrap_class');
    $class = $this->prepareClasses($class);
    if ($class) {
      $summary[] = $this->formatPlural(count($class),
        $this->t('Wrapper additional CSS class: @class.', ['@class' => implode('', $class)]),
        $this->t('Wrapper additional CSS classes: @class.', ['@class' => implode(' ', $class)])
      );
    }
    else {
      $summary[] = $this->t('No additional CSS class defined.');
    }

    $attributes = $this->getSetting('wrap_attributes');
    $attributes = $this->prepareAttributes($attributes);
    $additional_attributes = '';

    if ($attributes) {
      foreach ($attributes as $attribute => $value) {
        if ($value) {
          $additional_attributes .= $attribute . '="' . $value . '" ';
        }
        else {
          $additional_attributes .= $attribute;
        }
      }
    }
    else {
      $additional_attributes = $this->t('No additional attributes defined.');
    }

    $summary[] = $this->t('Wrapper additional attributes:<br>@attributes', ['@attributes' => $additional_attributes]);

    $summary[] = $this->getSetting('override_link_label') ?
      $this->t('Link label: @link_label', ['@link_label' => $this->getSetting('override_link_label')]) :
      $this->t('Link label: @link_label', ['@link_label' => 'Default']);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $wrap_tag = $this->getSetting('wrap_tag');
    $class = $this->prepareClasses($this->getSetting('wrap_class'));
    $attributes = $this->prepareAttributes($this->getSetting('wrap_attributes'));

    foreach ($items as $delta => $item) {
      if ($wrap_tag !== '') {
        if ($elements[$delta]["#type"] === 'link') {
          $temp = $elements[$delta]["#title"]["#context"]["value"];
          if ($this->getSetting('link_to_entity') && $this->getSetting('override_link_label')) {
            $entity = $items->getEntity();
            if ($entity instanceof EntityInterface) {
              $replace_tokens = [$entity->getEntityTypeId() => $entity];
            }
            else {
              $replace_tokens = [];
            }
            $temp = $this->token->replace($this->getSetting('override_link_label'), $replace_tokens, ['clear' => TRUE]);
          }
          $elements[$delta] = [
            '#type' => 'html_tag',
            '#tag' => $wrap_tag,
            '#attributes' => [
              'class' => $class,
            ] + $attributes,
            'children' => [$elements[$delta]],
          ];
          unset($temp);
        }
        else {
          $elements[$delta] = [
            '#type' => 'html_tag',
            '#tag' => $wrap_tag,
            '#value' => $item->value,
            '#attributes' => [
              'class' => $class,
            ] + $attributes,
          ];
        }
      }
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return array
   *   The textual output generated as a render array.
   */
  protected function viewValue(FieldItemInterface $item) {
    // The text value has no text format assigned to it, so the user input
    // should equal the output, including newlines.
    return [
      '#type' => 'inline_template',
      '#template' => '{{ value }}',
      '#context' => ['value' => $item->value],
    ];
  }

  /**
   * Build classes.
   *
   * @param string $classes
   *   String of classes.
   *
   * @return array
   *   Return prepared list of classes.
   */
  public function prepareClasses(string $classes) {
    $classes = preg_replace('! !', ',', $classes);
    $classes = explode(',', $classes);
    $prepared = [];
    foreach ($classes as $class) {
      $class = trim($class);
      if ($class) {
        $prepared[] = Html::getClass($class);
      }
    }

    return $prepared;
  }

  /**
   * Build attributes.
   *
   * @param string $attributes
   *   String of attributes.
   *
   * @return array
   *   Return prepared list of attributes.
   */
  public function prepareAttributes(string $attributes) {
    $prepared = [];
    if ($attributes) {
      $attributes = explode("\r\n", $attributes);
      foreach ($attributes as $attribute) {
        $attribute = explode("|", $attribute);
        $prepared[$attribute[0]] = isset($attribute[1]) ? $attribute[1] : '';
      }
    }

    return $prepared;
  }

}
