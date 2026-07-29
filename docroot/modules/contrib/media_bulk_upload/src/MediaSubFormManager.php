<?php

namespace Drupal\media_bulk_upload;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Environment;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\media\MediaTypeInterface;
use Drupal\media_bulk_upload\Entity\MediaBulkConfigInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function in_array;

/**
 * Class MediaSubFormManager.
 *
 * @package Drupal\media_bulk_upload
 */
class MediaSubFormManager implements ContainerInjectionInterface, MediaSubFormManagerInterface {

  /**
   * Default max file size.
   *
   * @var string
   */
  protected $defaultMaxFileSize = '32MB';

  /**
   * Media Type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaTypeStorage;

  /**
   * Entity Form Display storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityFormDisplayStorage;

  /**
   * Media entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * Media Type Manager.
   *
   * @var \Drupal\media_bulk_upload\MediaTypeManager
   */
  protected $mediaTypeManager;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * File system interface.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * BulkMediaUploadForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\media_bulk_upload\MediaTypeManager $mediaTypeManager
   *   Media Type Manager.
   * @param \Drupal\Core\Utility\Token $token
   *   Token service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, MediaTypeManager $mediaTypeManager, Token $token, FileSystemInterface $fileSystem) {
    $this->mediaTypeManager = $mediaTypeManager;
    $this->mediaTypeStorage = $entityTypeManager->getStorage('media_type');
    $this->mediaStorage = $entityTypeManager->getStorage('media');
    $this->entityFormDisplayStorage = $entityTypeManager->getStorage('entity_form_display');
    $this->token = $token;
    $this->defaultMaxFileSize = ByteSizeMarkup::create(Environment::getUploadMaxSize(), LanguageInterface::LANGCODE_NOT_SPECIFIED);
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('media_bulk_upload.media_type_manager'),
      $container->get('token'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetFieldDirectory(MediaTypeInterface $mediaType) {
    $targetFieldSettings = $this->mediaTypeManager->getTargetFieldSettings($mediaType);
    $fileDirectory = trim($targetFieldSettings['file_directory'], '/');
    $fileDirectory = PlainTextOutput::renderFromHtml($this->token->replace($fileDirectory));
    $targetDirectory = $targetFieldSettings['uri_scheme'] . '://' . $fileDirectory;
    $this->fileSystem->prepareDirectory($targetDirectory, FileSystemInterface::CREATE_DIRECTORY);
    return $targetDirectory;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildMediaSubForm(array &$form, FormStateInterface $form_state, MediaBulkConfigInterface $mediaBulkConfig) {
    $mediaTypes = $this->mediaTypeManager->getBulkMediaTypes($mediaBulkConfig);
    $mediaType = reset($mediaTypes);

    /** @var \Drupal\media\MediaInterface $dummyMedia */
    $dummyMedia = $this->mediaStorage->create(['bundle' => $mediaType->id()]);
    $mediaFormDisplay = $this->getMediaFormDisplay($mediaBulkConfig, $mediaType);
    $mediaFormDisplay->buildForm($dummyMedia, $form, $form_state);

    $targetFieldName = $this->mediaTypeManager->getTargetFieldName($mediaType);
    unset($form[$targetFieldName]);

    $fields = $this->getFields($mediaBulkConfig);
    if (empty($fields)) {
      return $this;
    }

    $this->configureSharedFields($form, $fields);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMediaFormDisplay(MediaBulkConfigInterface $mediaBulkConfig, MediaTypeInterface $mediaType) {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $mediaFormDisplay */
    $mediaFormDisplay = $this->entityFormDisplayStorage->load('media.' . $mediaType->id() . '.' . $mediaBulkConfig->get('form_mode'));
    if ($mediaFormDisplay === NULL) {
      $mediaFormDisplay = $this->entityFormDisplayStorage->load('media.' . $mediaType->id() . '.default');
    }
    return $mediaFormDisplay;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getFields(MediaBulkConfigInterface $mediaBulkConfig) {
    $mediaTypes = $this->mediaTypeManager->getBulkMediaTypes($mediaBulkConfig);
    $fields = $this->getMediaEntityFieldComponents($mediaBulkConfig, array_shift($mediaTypes));

    foreach ($mediaTypes as $mediaType) {
      $fields = array_intersect($fields, $this->getMediaEntityFieldComponents($mediaBulkConfig, $mediaType));
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getMediaEntityFieldComponents(MediaBulkConfigInterface $mediaBulkConfig, MediaTypeInterface $mediaType) {
    $mediaFormDisplay = $this->getMediaFormDisplay($mediaBulkConfig, $mediaType);
    $fieldComponents = $mediaFormDisplay->getComponents();
    return array_keys($fieldComponents);
  }

  /**
   * {@inheritdoc}
   */
  public function configureSharedFields(array &$elements, array $allowedFields) {
    $children = Element::children($elements);
    foreach ($children as $child) {
      if (!in_array($child, $allowedFields, TRUE)) {
        unset($elements[$child]);
        continue;
      }

      $this->forceFieldsAsOptional($elements[$child]);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function forceFieldsAsOptional(array &$elements) {
    if (isset($elements['#required'])) {
      $elements['#required'] = FALSE;
    }
    $children = Element::children($elements);
    foreach ($children as $child) {
      $this->forceFieldsAsOptional($elements[$child]);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function validateMediaFormDisplayUse(MediaBulkConfigInterface $mediaBulkConfig) {
    $formMode = $mediaBulkConfig->get('form_mode');
    if (!empty($formMode)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMediaTypeManager() {
    return $this->mediaTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultMaxFileSize() {
    return $this->defaultMaxFileSize;
  }
}
