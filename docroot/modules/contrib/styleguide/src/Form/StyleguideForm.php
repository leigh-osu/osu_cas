<?php

namespace Drupal\styleguide\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\styleguide\GeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form builder to display form controls in style guide.
 */
class StyleguideForm extends FormBase {

  /**
   * The styleguide generator.
   *
   * @var \Drupal\styleguide\GeneratorInterface
   */
  protected $generator;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new StyleguideForm object.
   *
   * @param \Drupal\styleguide\GeneratorInterface $styleguide_generator
   *   The styleguide generator.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(GeneratorInterface $styleguide_generator, ModuleHandlerInterface $module_handler) {
    $this->generator = $styleguide_generator;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('styleguide.generator'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'styleguide_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $form_keys = []) {
    // @todo Use element names and defaults from element_info service.
    $form = [];
    $options = [];
    $list = $this->generator->wordList();
    foreach ($list as $item) {
      $options[$item] = $item;
    }
    $form['select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select'),
      '#options' => $options,
      '#description' => $this->generator->sentence(),
    ];
    $form['checkbox'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Checkbox'),
      '#value' => 1,
      '#default_value' => 1,
      '#description' => $this->generator->sentence(),
    ];
    $form['checkboxes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Checkboxes'),
      '#options' => $options,
      '#description' => $this->generator->sentence(),
    ];
    $form['radios'] = [
      '#type' => 'radios',
      '#title' => $this->t('Radios'),
      '#options' => $options,
      '#description' => $this->generator->sentence(),
    ];
    $form['textfield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Textfield'),
      '#default_value' => $this->generator->words(3, 'ucfirst'),
      '#description' => $this->generator->sentence(),
    ];
    $form['entity_autocomplete'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Node entity autocomplete'),
      '#description' => $this->generator->sentence(),
      '#target_type' => 'node',
    ];
    $form['textfield-machine'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Textfield, with machine name'),
      '#default_value' => $this->generator->words(3, 'ucfirst'),
      '#description' => $this->generator->sentence(),
    ];
    $form['machine_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#machine_name' => [
        'exists' => 'styleguide_machine_name_exists',
        'source' => ['textfield-machine'],
      ],
      '#description' => $this->generator->sentence(),
    ];
    $form['textarea'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Textarea'),
      '#default_value' => $this->generator->paragraphs(5, TRUE),
      '#description' => $this->generator->sentence(),
    ];
    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#description' => $this->generator->sentence(),
    ];
    $form['file'] = [
      '#type' => 'file',
      '#title' => $this->t('File'),
      '#description' => $this->generator->sentence(),
    ];
    $form['managed_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Managed file'),
      '#description' => $this->generator->sentence(),
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#default_value' => $this->generator->words(),
      '#description' => $this->generator->sentence(),
    ];
    $form['password_confirm'] = [
      '#type' => 'password_confirm',
      '#title' => $this->t('Password confirm'),
    ];
    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#delta' => 10,
      '#description' => $this->generator->sentence(),
    ];
    $form['image_button'] = [
      '#type' => 'image_button',
      '#src' => 'core/misc/druplicon.png',
      '#attributes' => ['height' => 40],
      '#title' => $this->t('Image button'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    $form['submit_disabled'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit - disabled'),
      '#disabled' => TRUE,
    ];
    $form['button'] = [
      '#type' => 'button',
      '#value' => $this->t('Button'),
    ];
    $form['button_disabled'] = [
      '#type' => 'button',
      '#value' => $this->t('Button - disabled'),
      '#disabled' => TRUE,
    ];
    $form['button_link'] = [
      '#type' => 'link',
      '#url' => Url::fromUri('internal:#'),
      '#title' => $this->t('Link button'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];
    $form['fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fieldset'),
      '#description' => $this->generator->sentence(),
    ];
    $form['fieldset']['child1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fieldset Child 1'),
      '#description' => $this->generator->sentence(),
    ];
    $form['fieldset']['child2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fieldset Child 2'),
      '#description' => $this->generator->sentence(),
    ];
    $form['vertical_tabs']['elements'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'tab1',
    ];
    for ($count = 1; $count < 4; $count++) {
      $form['vertical_tabs']['tab' . $count] = [
        '#type' => 'details',
        '#title' => $this->t('Tab @count', ['@count' => $count]),
        '#description' => $this->generator->sentence(),
        '#group' => 'elements',
      ];
      $form['vertical_tabs']['tab' . $count]['content'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Tab @count child', ['@count' => $count]),
        '#description' => $this->generator->sentence(),
      ];
    }
    $form['markup'] = [
      '#markup' => $this->t('<p><em>Markup</em>: Note that markup does not allow titles or descriptions. Use "item" for those options.</p>') . $this->generator->paragraphs(1, TRUE),
    ];
    $form['item'] = [
      '#type' => 'item',
      '#title' => $this->t('Item'),
      '#markup' => $this->generator->paragraphs(1, TRUE),
      '#description' => $this->generator->sentence(),
    ];

    if ($this->moduleHandler->moduleExists('filter')) {
      $form['text_format'] = [
        '#title' => $this->generator->sentence(),
        '#type' => 'text_format',
        '#default_value' => $this->generator->paragraphs(5, TRUE),
        '#format' => filter_default_format(),
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['button-primary'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit - primary'),
      '#button_type' => 'primary',
    ];
    $form['actions']['button-submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Button'),
    ];
    $form['actions']['button-link'] = [
      '#type' => 'link',
      '#url' => Url::fromUri('internal:#'),
      '#title' => $this->t('Link button'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];
    $form['actions']['button-danger'] = [
      '#type' => 'button',
      '#value' => $this->t('Button - danger'),
      '#button_type' => 'danger',
    ];

    if (!empty($form_keys)) {
      $items = [];
      foreach ($form_keys as $key) {
        if (isset($form[$key])) {
          $items[$key] = $form[$key];
        }
      }
      return $items;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement submitForm() method.
  }

}
