<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\DefaultsSectionStorageInterface;
use Drupal\layout_builder\LayoutDisplayHelperTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\LayoutTempstoreRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Confirmation form when disabling layouts on an entity with overrides.
 *
 * @internal
 *   Form classes are internal.
 */
class RevertAllConfirmForm extends ConfirmFormBase {

  use LayoutDisplayHelperTrait;
  use LayoutEntityHelperTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepository
   */
  protected $layoutTempstoreRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\DefaultsSectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(LayoutTempstoreRepository $layout_tempstore_repository, EntityTypeManagerInterface $entity_type_manager) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DefaultsSectionStorageInterface $section_storage = NULL) {
    $this->sectionStorage = $section_storage;
    $this->entity = $this->sectionStorage->getContextValue('display');

    // Mark this as an administrative page for JavaScript ("Back to site" link).
    $form['#attached']['drupalSettings']['path']['currentPathIsAdmin'] = TRUE;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to revert all layouts to the default layout for this display?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->sectionStorage->getRedirectUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_revert_all';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $ids = $this->getOverrideQuery($this->entity)->execute();
    $entities = $this->entityTypeManager
      ->getStorage($this->entity->getTargetEntityTypeId())
      ->loadMultiple($ids);

    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Removing Layout Overrides'))
      ->setFinishCallback([$this, 'revertEntityOverrideBatchFinished'])
      ->setInitMessage($this->t('Starting'))
      ->setProgressMessage($this->t('Processed @current out of @total.'))
      ->setErrorMessage($this->t('An error was encountered'));

    foreach ($entities as $entity_with_override) {
      $batch_builder->addOperation([$this, 'revertEntityOverrideBatch'], [$entity_with_override]);
    }

    batch_set($batch_builder->toArray());
  }

  /**
   * Batch process for removing layout overrides.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity_with_override
   *   The entity with override that should be removed.
   */
  public function revertEntityOverrideBatch(FieldableEntityInterface $entity_with_override) {
    $override_section_storage = $this->getSectionStorageForEntity($entity_with_override);
    $override_section_storage->removeAllSections();
    $override_section_storage->save();
    $this->layoutTempstoreRepository->delete($override_section_storage);
  }

  /**
   * When override revert batch process is complete.
   *
   * @param bool $success
   *   Indicates whether the batch process was successful.
   * @param array $results
   *   Results information passed from the processing callback.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response after finishing the batch.
   */
  public function revertEntityOverrideBatchFinished($success, array $results) {
    if ($success) {

      $this->messenger()->addStatus($this->t('All overrides removed.'));
    }
    else {
      $this->messenger()->addWarning($this->t('An error occurred while removing overrides, Layout Builder could not be disabled.'));
    }

    return new RedirectResponse($this->sectionStorage->getRedirectUrl()->setAbsolute()->toString());
  }

}
