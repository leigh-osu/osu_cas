<?php

namespace Drupal\media_bulk_upload\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MediaBulkConfigForm.
 */
class MediaBulkConfigForm extends EntityForm implements ContainerInjectionInterface {

  /**
   * Entity Display Repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * MediaBulkConfigForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   Entity Display repository.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(EntityDisplayRepositoryInterface $entityDisplayRepository, MessengerInterface $messenger) {
    $this->entityDisplayRepository = $entityDisplayRepository;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_display.repository'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\media_bulk_upload\Entity\MediaBulkConfigInterface $mediaBulkConfig */
    $mediaBulkConfig = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $mediaBulkConfig->label(),
      '#description' => $this->t("Label for the Media Bulk Config."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $mediaBulkConfig->id(),
      '#machine_name' => [
        'exists' => '\Drupal\media_bulk_upload\Entity\MediaBulkConfig::load',
      ],
      '#disabled' => !$mediaBulkConfig->isNew(),
    ];

    $media_types = $mediaBulkConfig->get('media_types');
    $form['media_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Media Types'),
      '#description' => $this->t('Choose the media types that will be
        used to create new media entities based on matching extensions. Please be
        aware that if file extensions overlap between the media types that are
        chosen, that the media entity will be assigned automatically to one of
        these types.'),
      '#options' => $this->getMediaTypeOptions(),
      '#default_value' => isset($media_types) ? $media_types : [],
      '#size' => 20,
      '#multiple' => TRUE,
      '#required' => TRUE,
    ];

    $form['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form Mode'),
      '#description' => $this->t('Based on the form mode the upload form
        can be enriched with fields that are available, improving the speed and
        usability to add (meta)data to your media entities.'),
      '#options' => $this->entityDisplayRepository->getFormModeOptions('media'),
      "#empty_option" => t('- None -'),
      '#default_value' => $mediaBulkConfig->get('form_mode'),
    ];

    $form['upload_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload location'),
      '#description' => $this->t('Location to initially upload the files before they are moved to the determined
      location in the media types.'),
      '#default_value' => $mediaBulkConfig->get('upload_location') ?: 'temporary://media-bulk-upload',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Get the available media type options.
   */
  private function getMediaTypeOptions() {
    $mediaTypeStorage = $this->entityTypeManager->getStorage('media_type');
    $mediaTypes = $mediaTypeStorage->loadMultiple();

    foreach ($mediaTypes as $mediaType) {
      $mediaTypeOptions[$mediaType->id()] = $mediaType->label();
    }
    natsort($mediaTypeOptions);

    return $mediaTypeOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $upload_location = $form_state->getValue('upload_location');
    $upload_location_ok = FALSE;
    $upload_location_error = NULL;

    // Check for file system and schema issues before using
    // FileSystemInterface::prepareDirectory() to create/check the directory.
    try {
      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');
      /** @var \Drupal\Core\StreamWrapper\StreamWrapperManager $stream_wrapper_manager */
      $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');

      if (empty($upload_location)) {
        $upload_location_error = t('No upload location provided.');
      }
      elseif (str_starts_with($upload_location, 'private://') && !PrivateStream::basePath()) {
        $upload_location_error = t('You need to configure and set up your private files directory before you can use it for Media Bulk Uploads.');
      }
      elseif (str_starts_with($upload_location, 'temporary://')) {
        if (!$file_system->getTempDirectory()) {
          $upload_location_error = t('You need to configure and set up your temporary files directory before you can use it for Media Bulk Uploads.');
        }
      }
      elseif ($stream_wrapper_manager::getScheme($upload_location) && !$stream_wrapper_manager->isValidScheme($stream_wrapper_manager::getScheme($upload_location))) {
        $upload_location_error = t('Invalid scheme provided. Standard schemes include public:// , private:// and temporary://');
      }

      // Create the directory and give it the permissions needed.
      if (empty($upload_location_error)) {
        $upload_location_ok = $file_system->prepareDirectory($upload_location, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      }
    }
    catch (\Exception $e) {
      $form_state->setError($form['upload_location'], 'Media upload location does not exist, or could not be created. [ Error: ' . $e->getMessage() . ']');
    }

    // Check the result.
    if (!$upload_location_ok) {
      $form_state->setError($form['upload_location'], $upload_location_error ?? 'Media upload location does not exist, or could not be created.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $media_bulk_config = $this->entity;
    $status = $media_bulk_config->save();

    $save_message = $this->t('Saved the %label Media Bulk Config.', [
      '%label' => $media_bulk_config->label(),
    ]);

    if ($status == SAVED_NEW) {
      $save_message = $this->t('Created the %label Media Bulk Config.', [
        '%label' => $media_bulk_config->label(),
      ]);
    }

    $this->messenger->addMessage($save_message);
    $form_state->setRedirectUrl($media_bulk_config->toUrl('collection'));
  }

}
