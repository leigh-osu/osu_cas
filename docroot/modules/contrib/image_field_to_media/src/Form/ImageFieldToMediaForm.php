<?php

namespace Drupal\image_field_to_media\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\field_ui\FieldUI;

/**
 * Clone Image field to Media image field and update all entities of the bundle.
 */
class ImageFieldToMediaForm extends FormBase {

  /**
   * The name of the entity type.
   *
   * @var string
   */
  protected string $entityTypeId;

  /**
   * Bundles of the entity type that has the image field.
   *
   * @var array
   */
  protected array $bundles;

  /**
   * The bundle of the entity on  which we selected the image field.
   *
   * @var string
   */
  protected string $targetBundle;

  /**
   * The name of the Image field.
   *
   * @var string
   */
  protected string $imageFieldName;

  /**
   * The cardinality.
   *
   * @var int
   */
  protected int $cardinality;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Entity\EntityTypeBundleInfo definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * Drupal\Core\Entity\EntityFieldManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Drupal\field\FieldConfigInterface definition.
   *
   * @var \Drupal\field\FieldConfigInterface
   */
  protected $fieldConfig;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * State storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Extension.list.module service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    $instance->fieldTypePluginManager = $container->get('plugin.manager.field.field_type');
    $instance->configFactory = $container->get('config.factory');
    $instance->state = $container->get('state');
    $instance->moduleExtensionList = $container->get('extension.list.module');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'image_field_to_media_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?FieldConfigInterface $field_config = NULL): array {
    $form['#id'] = 'image-field-to-media-form';

    $this->entityTypeId = $field_config->getTargetEntityTypeId();
    $this->imageFieldName = $field_config->getName();
    $this->cardinality = $field_config->getFieldStorageDefinition()->getCardinality();

    // Get bundles of the entity type that has the image field.
    /** @var \Drupal\field\Entity\FieldStorageConfig $fieldStorage */
    $fieldStorage = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load($this->entityTypeId . '.' . $this->imageFieldName);
    $this->bundles = array_values($fieldStorage->getBundles());

    // We need this to redirect a user back to the "Manage Display" page
    // after the conversion are complete.
    $this->targetBundle = $field_config->getTargetBundle();

    $form['create_or_reuse'] = [
      '#type' => 'radios',
      '#options' => [
        'crate_new_media_field' => $this->t('Create a new Media Image field'),
        'reuse_existing_media_field' => $this->t('Reuse an existing Media field'),
      ],
      '#ajax' => [
        'callback' => '::ajaxUpdateForm',
        'wrapper' => $form['#id'],
      ],
    ];

    if ($form_state->getValue('create_or_reuse', NULL) === 'crate_new_media_field') {

      // Field label and field_name.
      $form['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#description' => $this->t('Label of the Media image field to be created.'),
        '#size' => 15,
        '#default_value' => $field_config->getLabel() . ' media',
        '#required' => TRUE,
      ];

      $field_prefix = $this->config('field_ui.settings')->get('field_prefix');

      $form['field_name'] = [
        '#type' => 'machine_name',
        '#field_prefix' => $field_prefix,
        '#size' => 15,
        '#description' => $this->t('A unique machine-readable name containing letters, numbers, and underscores.'),
        // Calculate characters depending on the length of the field prefix
        // setting. Maximum length is 32.
        '#maxlength' => FieldStorageConfig::NAME_MAX_LENGTH - strlen($field_prefix),
        '#machine_name' => [
          'source' => ['label'],
          'exists' => [$this, 'fieldNameExists'],
        ],
        '#required' => FALSE,
      ];

      // Place the 'translatable' property as an explicit value so that contrib
      // modules can form_alter() the value for newly created fields.
      // By default, we create field storage as translatable, so it will be
      // possible to enable translation at field level.
      $form['translatable'] = [
        '#type' => 'value',
        '#value' => TRUE,
      ];

      $form['#attached']['library'][] = 'field_ui/drupal.field_ui';
    }
    elseif ($form_state->getValue('create_or_reuse', NULL) === 'reuse_existing_media_field') {
      $existingMediaFields = [];
      $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($this->entityTypeId, $this->targetBundle);

      foreach ($fieldDefinitions as $fieldName => $fieldDefinition) {
        if ($fieldDefinition->getItemDefinition()
          ->getSetting('target_type') === 'media') {
          $settings = $fieldDefinition->getItemDefinition()->getSettings();
          $targetBundles = $settings['handler_settings']['target_bundles'];
          if (array_key_exists('image', $targetBundles)) {
            $existingMediaFields[$fieldName] = $fieldDefinition->getLabel();
          }
        }
      }

      $form['existing_media_field'] = [
        '#type' => 'select',
        '#description' => $this->t('If selected, images will be added to the existing media field and no new media field will be created.'),
        '#options' => $existingMediaFields,
        '#empty_value' => '',
        '#empty_option' => $this->t('- Select an existing media field -'),
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Proceed'),
      '#button_type' => 'primary',
      '#states' => [
        'visible' => [
          [
            'input[name="create_or_reuse"]' => ['value' => 'crate_new_media_field'],
          ],
          [
            'input[name="create_or_reuse"]' => ['value' => 'reuse_existing_media_field'],
            'select[name="existing_media_field"]' => ['!value' => ''],
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Ajax update the form.
   */
  public function ajaxUpdateForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * Checks if a field machine name is taken.
   *
   * @param string $value
   *   The machine name, not prefixed.
   * @param array $element
   *   An array containing the structure of the 'field_name' element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   Whether the field machine name is taken.
   */
  public function fieldNameExists($value, array $element, FormStateInterface $form_state): bool {
    // Add the field prefix.
    $field_name = $this->configFactory->get('field_ui.settings')->get('field_prefix') . $value;

    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($this->entityTypeId);
    return isset($field_storage_definitions[$field_name]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Detect if we should to create a new Media field or to reuse the existing.
    if ($form_state->getValue('create_or_reuse') === 'crate_new_media_field') {
      $media_field_label = $form_state->getValue('label');
      $media_field_prefix = $this->configFactory->get('field_ui.settings')
        ->get('field_prefix');
      $media_field_name = $media_field_prefix . $form_state->getValue('field_name');

      $this->createMediaField($media_field_label, $media_field_name);
      foreach ($this->bundles as $bundle) {
        $this->setDisplaySettings($media_field_name, $bundle);
      }
    }
    else {
      // Get the name of the existing Media field to which we will add images.
      $media_field_name = $form_state->getValue('existing_media_field');
    }

    $operations[] = [
      'image_field_to_media_populate_media_field',
      [
        $this->entityTypeId,
        $this->bundles,
        $this->imageFieldName,
        $media_field_name,
      ],
    ];

    $batch = [
      'operations' => $operations,
      'finished' => 'image_field_to_media_batch_finished',
      'init_message' => $this->t('Cloning is starting.'),
      'progress_message' => '',
      'error_message' => $this->t('Cloning has encountered an error.'),
      'file' => $this->moduleExtensionList->getPath('image_field_to_media') . '/image_field_to_media.batch.inc',
    ];

    batch_set($batch);
    // Return to the "Manage Fields" form.
    $form_state->setRedirectUrl(FieldUI::getOverviewRouteInfo($this->entityTypeId, $this->targetBundle));
  }

  /**
   * Create Entity reference field for storing the Image Media type.
   *
   * @param string $field_label
   *   The label of the field.
   * @param string $field_name
   *   The name of the field.
   */
  private function createMediaField(string $field_label, string $field_name): void {
    // Create field storage.
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $this->entityTypeId,
      'type' => 'entity_reference',
      'cardinality' => $this->cardinality,
      // Optional to target entity types.
      'settings' => [
        'target_type' => 'media',
      ],
    ])->save();

    // Create field.
    foreach ($this->bundles as $bundle) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $this->entityTypeId,
        'bundle' => $bundle,
        'label' => $field_label,
        'cardinality' => $this->cardinality,
        'settings' => [
          'handler' => 'default:media',
          'handler_settings' => [
            'target_bundles' => ['image' => 'image'],
            'sort' => [
              'field' => '_none',
              'direction' => 'ASC',
            ],
            'auto_create' => FALSE,
            'auto_create_bundle' => '',
          ],
        ],
      ])->save();
    }

    // Get the preconfigured field options of the media field.
    $options = $this->fieldTypePluginManager->getPreconfiguredOptions('entity_reference');
    $field_options = $options['media'];

    $widget_id = $field_options['entity_form_display']['type'] ?? NULL;
    $widget_settings = $field_options['entity_form_display']['settings'] ?? [];
    $formatter_id = $field_options['entity_view_display']['type'] ?? NULL;
    $formatter_settings = $field_options['entity_view_display']['settings'] ?? [];

    $form_display_options = [];
    if ($widget_id) {
      $form_display_options['type'] = $widget_id;
      if (!empty($widget_settings)) {
        $form_display_options['settings'] = $widget_settings;
      }
    }
    // Make sure the field is displayed in the 'default' form mode (using
    // default widget and settings). It stays hidden for other form modes
    // until it is explicitly configured.
    foreach ($this->bundles as $bundle) {
      $this->entityDisplayRepository->getFormDisplay($this->entityTypeId, $bundle, 'default')
        ->setComponent($field_name, $form_display_options)
        ->save();
    }

    $view_display_options = [];
    if ($formatter_id) {
      $view_display_options['type'] = $formatter_id;
      if (!empty($formatter_settings)) {
        $view_display_options['settings'] = $formatter_settings;
      }
    }
    // Make sure the field is displayed in the 'default' view mode (using
    // default formatter and settings). It stays hidden for other view
    // modes until it is explicitly configured.
    foreach ($this->bundles as $bundle) {
      $this->entityDisplayRepository->getViewDisplay($this->entityTypeId, $bundle)
        ->setComponent($field_name, $view_display_options)
        ->save();
    }
  }

  /**
   * Set same weight and label settings for a Media field as an Image field has.
   *
   * @param string $media_field_name
   *   The name of the created Media field.
   * @param string $bundle
   *   A bundle of the entity type that has the image field.
   */
  private function setDisplaySettings(string $media_field_name, string $bundle): void {
    $entity_type = $this->entityTypeId;
    $image_field_name = $this->imageFieldName;

    // ---------- Set for View displays ------------------------.
    $view_modes = $this->entityDisplayRepository->getViewModeOptionsByBundle($entity_type, $bundle);
    $storage = $this->entityTypeManager->getStorage('entity_view_display');

    foreach (array_keys($view_modes) as $view_mode) {
      $view_display = $storage->load($entity_type . '.' . $bundle . '.' . $view_mode);
      $image_component = $view_display->getComponent($image_field_name);

      // A view display like a "Teaser" may not have the image field. In this
      // case the "$image_component" will be NULL. So, we should check it out.
      if ($image_component) {
        $media_component = $view_display->getComponent($media_field_name);
        $media_component['weight'] = $image_component['weight'] ?? 0;
        $media_component['label'] = $image_component['label'];
        // If the image field has the "Image" format then set a similar format
        // for the media field. Also, copy the settings of the "Image" format.
        // (As the settings of other formats are different we will not to
        // synchronize them here. Users can do it from UI).
        if ($image_component['type'] === 'image') {
          $media_component['type'] = 'media_thumbnail';
          $media_component['settings'] = $image_component['settings'];
        }
        $view_display->setComponent($media_field_name, $media_component)->save();
      }
    }

    // ---------- Set for Form displays ------------------------.
    $form_modes = $this->entityDisplayRepository->getFormModeOptionsByBundle($entity_type, $bundle);

    foreach (array_keys($form_modes) as $form_mode) {
      $storage = $this->entityTypeManager->getStorage('entity_form_display');
      $form_display = $storage->load($entity_type . '.' . $bundle . '.' . $form_mode);

      $image_component = $form_display->getComponent($image_field_name);
      $media_component = $form_display->getComponent($media_field_name);
      $media_component['weight'] = $image_component['weight'];
      $form_display->setComponent($media_field_name, $media_component)->save();
    }
  }

}
